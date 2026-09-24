<?php

namespace Tests\Feature\Planeacion\Concerns;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;

/**
 * Fixtures mínimos de Programa y Muestras en sqlite (PT-01, 01.4).
 *
 * Diferencia con createTablaDesdeModelo(): MuestrasPrograma se crea SIN las columnas
 * que live no tiene (config planeacion.superficies.muestras.columnas_ausentes), para
 * que la suite vea los mismos fallos que SQL Server. Sqlite no aplica longitudes de
 * varchar, así que la divergencia de longitudes se caracteriza por StringTruncator.
 *
 * Los Id se solapan a propósito (1..5 en ambas tablas): una escritura cruzada por Id
 * cae en una fila real de la otra superficie y el test la detecta.
 */
trait ProgramaTejidoFixtures
{
    use UsesSqlsrvSqlite;

    protected function prepararSuperficies(): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->usarSuperficie('programa');

        foreach (config('planeacion.superficies') as $superficie) {
            $this->crearCabecera($superficie['tabla'], $superficie['columnas_ausentes']);
            $this->crearLineas($superficie['tabla_lineas']);
        }

        $this->createTablaDesdeModelo(CatCodificados::class);
    }

    /**
     * Lo mismo que hace ProgramaTejidoContext para una URI de Muestras.
     */
    protected function usarSuperficie(string $superficie): void
    {
        config()->set('planeacion.programa_tejido_table', config("planeacion.superficies.{$superficie}.tabla"));
        config()->set('planeacion.programa_tejido_line_table', config("planeacion.superficies.{$superficie}.tabla_lineas"));
    }

    /**
     * Cinco filas por superficie: orden activa, fila sin orden (Ultimo), grupo compartido
     * líder + miembro en otro telar, y una fila casi toda null. Valores distintos por
     * superficie para distinguir de dónde viene cada lectura.
     */
    protected function sembrarFixtures(): void
    {
        foreach (['programa' => 30000, 'muestras' => 90000] as $superficie => $base) {
            $tabla = config("planeacion.superficies.{$superficie}.tabla");
            $filas = [
                [
                    'Id' => 1, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 1, 'EnProceso' => 1,
                    'NoProduccion' => (string) ($base + 1), 'NombreProducto' => "PRODUCTO {$superficie}",
                    'TotalPedido' => 1000, 'SaldoPedido' => 800, 'Produccion' => 200, 'PesoCrudo' => 450,
                    'NoTiras' => 2, 'LargoCrudo' => 70, 'CalendarioId' => 'Calendario Tej1',
                    'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-03 18:00:00', 'Ultimo' => '0',
                    'Prioridad' => 'SALDAR 123',
                ],
                [
                    'Id' => 2, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 2, 'EnProceso' => 0,
                    'NoProduccion' => null, 'TotalPedido' => 500, 'SaldoPedido' => 500,
                    'FechaInicio' => '2026-09-03 18:00:00', 'FechaFinal' => '2026-09-05 06:00:00', 'Ultimo' => '1',
                ],
                [
                    'Id' => 3, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Posicion' => 1, 'EnProceso' => 1,
                    'NoProduccion' => (string) ($base + 3), 'OrdCompartida' => 7, 'OrdCompartidaLider' => 1,
                    'TotalPedido' => 600, 'SaldoPedido' => 600,
                    'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-02 06:30:00', 'Ultimo' => '1',
                ],
                [
                    'Id' => 4, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '203', 'Posicion' => 1, 'EnProceso' => 1,
                    'NoProduccion' => (string) ($base + 3), 'OrdCompartida' => 7, 'OrdCompartidaLider' => 0,
                    'TotalPedido' => 400, 'SaldoPedido' => 400,
                    'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-01 22:30:00', 'Ultimo' => '1',
                ],
                ['Id' => 5, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '204', 'Posicion' => 1],
            ];

            foreach ($filas as $fila) {
                DB::table($tabla)->insert($fila);
            }
        }

        // Líneas pre-existentes: las regenera el observer; aquí solo fijan el "antes".
        foreach (['programa', 'muestras'] as $superficie) {
            $lineas = config("planeacion.superficies.{$superficie}.tabla_lineas");
            foreach ([1, 2, 3, 4] as $programaId) {
                DB::table($lineas)->insert(['ProgramaId' => $programaId, 'Fecha' => '2026-09-01', 'Cantidad' => $programaId * 10]);
            }
        }

        // CatCodificados solo conoce las órdenes de Programa (Muestras no libera hoy).
        DB::table((new CatCodificados)->getTable())->insert([
            ['OrdenTejido' => '30001', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 1000],
            ['OrdenTejido' => '30003', 'TelarId' => 202, 'Departamento' => 'JACQUARD', 'Pedido' => 600],
            ['OrdenTejido' => '30003', 'TelarId' => 203, 'Departamento' => 'JACQUARD', 'Pedido' => 400],
        ]);
    }

    /**
     * Foto completa de las 4 tablas de superficie + CatCodificados, para comparar antes/después.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function fotoSuperficies(): array
    {
        $foto = [];
        foreach (config('planeacion.superficies') as $superficie) {
            foreach ([$superficie['tabla'], $superficie['tabla_lineas']] as $tabla) {
                $foto[$tabla] = DB::table($tabla)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
            }
        }
        $cat = (new CatCodificados)->getTable();
        $foto[$cat] = DB::table($cat)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();

        return $foto;
    }

    private function crearCabecera(string $tabla, array $ausentes): void
    {
        $modelo = new ReqProgramaTejido;
        $casts = $modelo->getCasts();
        // ProduccionMarbetes existe en live (Programa) aunque el modelo no la tenga en $fillable.
        $columnas = array_diff(array_unique([...$modelo->getFillable(), 'ProduccionMarbetes']), $ausentes, ['Id']);

        Schema::create($tabla, function (Blueprint $table) use ($columnas, $casts) {
            $table->increments('Id');
            foreach ($columnas as $columna) {
                $this->columnaSegunCast($table, $columna, $casts[$columna] ?? 'string');
            }
        });
    }

    private function crearLineas(string $tabla): void
    {
        $modelo = new ReqProgramaTejidoLine;
        $casts = $modelo->getCasts();

        Schema::create($tabla, function (Blueprint $table) use ($modelo, $casts) {
            $table->increments('Id');
            foreach ($modelo->getFillable() as $columna) {
                $this->columnaSegunCast($table, $columna, $casts[$columna] ?? 'string');
            }
        });
    }

    private function columnaSegunCast(Blueprint $table, string $columna, string $cast): void
    {
        match (strtok($cast, ':')) {
            'int', 'integer', 'bool', 'boolean' => $table->integer($columna)->nullable(),
            'float', 'double', 'real', 'decimal' => $table->float($columna)->nullable(),
            'date', 'datetime', 'immutable_date', 'immutable_datetime' => $table->dateTime($columna)->nullable(),
            default => $table->text($columna)->nullable(),
        };
    }
}
