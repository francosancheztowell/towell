<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Services\ProgramaUrdEng\InsercionEnBloques;
use App\Support\ColumnasDeTabla;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Líneas diarias de un programa de tejido (extraído de ReqProgramaTejidoObserver):
 *  1. persiste en la cabecera las fórmulas de eficiencia que le pasan (fuera de transacción
 *     propia: si quien llama está en una, revierte con ella);
 *  2. reparte las piezas por día entre FechaInicio y FechaFinal con sus consumos;
 *  3. DELETE + INSERT de las líneas en una transacción, en bloques que respetan los 2 100
 *     parámetros de SQL Server.
 *
 * Lanza los errores: quien llama decide si relanza o reporta.
 */
final class GeneradorLineasDiarias
{
    /** Columnas de eficiencia que se escriben aunque el modelo no las tenga en $fillable. */
    private const COLUMNAS_EFICIENCIA = ['StdToaHra', 'PesoGRM2', 'DiasEficiencia', 'StdDia', 'ProdKgDia', 'StdHrsEfect', 'ProdKgDia2', 'HorasProd', 'DiasJornada'];

    /** @var array<string, ReqAplicaciones|null> ReqAplicaciones por AplicacionId (también los null) */
    private static array $aplicacionesCache = [];

    /** @var array<string, ReqMatrizHilos|null> ReqMatrizHilos por Hilo (también los null) */
    private static array $matrizHilosCache = [];

    private readonly ConsumosLineaDiaria $consumos;

    public function __construct(
        private readonly FormulasProgramaTejido $formulas = new FormulasProgramaTejido,
        private readonly MetrosHiloLineaDiaria $metros = new MetrosHiloLineaDiaria,
    ) {
        $this->consumos = new ConsumosLineaDiaria($this->metros);
    }

    public static function flushCaches(): void
    {
        self::$aplicacionesCache = [];
        self::$matrizHilosCache = [];
    }

    /**
     * @param  array<string, mixed>  $formulasEficiencia  resultado de FormulasEficiencia::calcularFormulasEficiencia
     */
    public function generar(ReqProgramaTejido $programa, array $formulasEficiencia): void
    {
        $this->persistirEficiencia($programa, $formulasEficiencia);

        $rango = $this->rango($programa);
        if ($rango === null) {
            return;
        }
        [$inicio, $fin] = $rango;

        $ritmo = $this->formulas->ritmo($programa, $inicio, $fin);
        if ($ritmo === null) {
            return;
        }

        $factorAplicacion = $this->factorAplicacion($programa);
        $matrizRizo = $this->matrizRizo($programa);

        // Una fila por día con horas > 0, en orden de fecha.
        $lineas = [];
        foreach ($this->formulas->horasPorDia($inicio, $fin) as $fecha => $horasDia) {
            if ($horasDia > 0) {
                $lineas[] = ['ProgramaId' => (int) $programa->Id, 'Fecha' => $fecha]
                    + $this->consumos->delDia($programa, $horasDia, $ritmo, $factorAplicacion, $matrizRizo);
            }
        }

        $conexion = $this->conexionParaInsertar($programa);
        if ($conexion === null) {
            return;
        }

        $this->reemplazarLineas($conexion, $programa, $lineas);
    }

    /**
     * @param  array<string, mixed>  $formulas
     */
    private function persistirEficiencia(ReqProgramaTejido $programa, array $formulas): void
    {
        if (empty($formulas)) {
            return;
        }

        $formulasParaGuardar = [];
        foreach ($formulas as $key => $value) {
            $programa->{$key} = $value;
            if (in_array($key, $programa->getFillable()) || in_array($key, self::COLUMNAS_EFICIENCIA)) {
                $formulasParaGuardar[$key] = ($value !== null && is_numeric($value)) ? (float) $value : $value;
            }
        }

        // Mismo criterio que recalcularFormulasProduccion: solo columnas físicas de la
        // superficie. Un fallo aquí se relanza (hallazgo 4), así que una columna que la
        // tabla no tiene no debe tumbar la regeneración de líneas.
        $columnasFormulas = ColumnasDeTabla::de(ReqProgramaTejido::tableName());
        if ($columnasFormulas !== []) {
            $formulasParaGuardar = array_intersect_key($formulasParaGuardar, array_flip($columnasFormulas));
        }
        if (! empty($formulasParaGuardar)) {
            $programa->getConnection()->table(ReqProgramaTejido::tableName())
                ->where('Id', $programa->Id)
                ->update($formulasParaGuardar);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function rango(ReqProgramaTejido $programa): ?array
    {
        $inicio = null;
        $fin = null;

        try {
            if (! empty($programa->FechaInicio)) {
                $inicio = Carbon::parse($programa->FechaInicio);
            }
            if (! empty($programa->FechaFinal)) {
                $fin = Carbon::parse($programa->FechaFinal);
            }
        } catch (Throwable) {
            return null;
        }

        if (! $inicio || ! $fin || $fin->lte($inicio)) {
            return null;
        }

        return [$inicio, $fin];
    }

    private function factorAplicacion(ReqProgramaTejido $programa): ?float
    {
        $valor = $programa->getAttribute('AplicacionId');
        if (! $valor) {
            return null;
        }

        $aplicacionId = (string) $valor;
        if (! isset(self::$aplicacionesCache[$aplicacionId])) {
            self::$aplicacionesCache[$aplicacionId] = ReqAplicaciones::where('AplicacionId', $aplicacionId)->first();
        }
        $aplicacion = self::$aplicacionesCache[$aplicacionId];

        return $aplicacion ? (float) $aplicacion->Factor : null;
    }

    /** Matriz del hilo de rizo; null si no aplica o no se puede leer (MtsRizo queda en null). */
    private function matrizRizo(ReqProgramaTejido $programa): ?ReqMatrizHilos
    {
        if (! $this->metros->necesitaMatrizRizo($programa)) {
            return null;
        }

        $hilo = $this->metros->hiloRizo($programa);
        try {
            if (! isset(self::$matrizHilosCache[$hilo])) {
                self::$matrizHilosCache[$hilo] = ReqMatrizHilos::where('Hilo', $hilo)->first();
            }
        } catch (Throwable) {
            // Igual que antes en calcularMtsRizo: sin matriz legible, MtsRizo va en null.
            return null;
        }

        return self::$matrizHilosCache[$hilo];
    }

    /**
     * pdo_sqlsrv/ODBC puede no ver registros recién insertados en la misma sesión (incluso
     * dentro de la misma transacción). Si el modelo existe se confía en él; si no, se revisa
     * que el padre sea visible y, si solo lo ve la conexión por defecto, se usa esa.
     * Null = padre no visible: no se generan líneas.
     */
    private function conexionParaInsertar(ReqProgramaTejido $programa): ?ConnectionInterface
    {
        $connection = $programa->getConnection();
        if ($programa->exists) {
            return $connection;
        }

        $tabla = ReqProgramaTejido::tableName();
        if ($connection->table($tabla)->where('Id', $programa->Id)->exists()) {
            return $connection;
        }
        if (DB::table($tabla)->where('Id', $programa->Id)->exists()) {
            return DB::connection();
        }

        Log::warning('ReqProgramaTejidoObserver::generarLineasDiarias: registro padre no visible, omitiendo líneas', [
            'programa_id' => $programa->Id,
        ]);

        return null;
    }

    /**
     * DELETE + INSERT atómicos: cualquier fallo entre el DELETE y el último bloque revierte todo
     * (antes, si el proceso moría tras el DELETE, el programa quedaba con 0 líneas).
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function reemplazarLineas(ConnectionInterface $conexion, ReqProgramaTejido $programa, array $lineas): void
    {
        $tableLine = ReqProgramaTejidoLine::tableName();

        $conexion->transaction(function () use ($conexion, $tableLine, $programa, $lineas): void {
            $conexion->table($tableLine)->where('ProgramaId', $programa->Id)->delete();

            if (empty($lineas)) {
                return;
            }
            foreach (array_chunk($lineas, InsercionEnBloques::filasPorBloque(count($lineas[0]))) as $chunk) {
                $conexion->table($tableLine)->insert($chunk);
            }
        });
    }
}
