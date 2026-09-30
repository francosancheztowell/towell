<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Calendarios;

use App\Imports\ReqCalendarioLineImport;
use App\Imports\ReqCalendarioTabImport;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqCalendarioTab;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Catalogos\ResultadoCatalogo;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Catálogo de Calendarios (antes, todo en CalendarioController): calendarios, líneas, edición
 * masiva por plantilla de turnos, carga de Excel y el recálculo de programas que dispara
 * cada cambio de líneas.
 */
final class CalendarioService
{
    /** Filas por INSERT: 5 columnas × 400 = 2 000 parámetros (SQL Server admite 2 100). */
    private const LOTE_INSERT = 400;

    public function __construct(
        private readonly TurnosCalendario $turnos,
        private readonly RecalcularProgramasCalendario $recalcular,
    ) {}

    /**
     * @param  array{CalendarioId: string, Nombre: string, FechaInicial?: string, FechaFinal?: string, Turnos?: array<int|string, mixed>}  $datos
     * @return array{calendario: ReqCalendarioTab, lineas: int}
     */
    public function crear(array $datos): array
    {
        return DB::transaction(function () use ($datos): array {
            $calendario = ReqCalendarioTab::create(['CalendarioId' => $datos['CalendarioId'], 'Nombre' => $datos['Nombre']]);
            $lineas = isset($datos['Turnos'])
                ? $this->insertarLineas($this->turnos->lineas($calendario->CalendarioId, $datos['FechaInicial'] ?? '', $datos['FechaFinal'] ?? '', $datos['Turnos']))
                : 0;

            return ['calendario' => $calendario, 'lineas' => $lineas];
        });
    }

    /**
     * Renombra y reemplaza las líneas que tocan el rango por las de la plantilla.
     *
     * @param  array{Nombre: string, FechaInicial: string, FechaFinal: string, Turnos: array<int|string, mixed>}  $datos
     */
    public function actualizarMasivo(ReqCalendarioTab $calendario, array $datos): int
    {
        return DB::transaction(function () use ($calendario, $datos): int {
            $calendario->update(['Nombre' => $datos['Nombre']]);
            $desde = Carbon::parse($datos['FechaInicial'])->startOfDay()->format('Y-m-d H:i:s');
            $hasta = Carbon::parse($datos['FechaFinal'])->endOfDay()->format('Y-m-d H:i:s');

            // Líneas que empiezan o terminan dentro del rango, o que lo contienen completo.
            ReqCalendarioLine::where('CalendarioId', $calendario->CalendarioId)
                ->where(fn ($q) => $q->whereBetween('FechaInicio', [$desde, $hasta])
                    ->orWhereBetween('FechaFin', [$desde, $hasta])
                    ->orWhere(fn ($c) => $c->where('FechaInicio', '<=', $desde)->where('FechaFin', '>=', $hasta)))
                ->delete();

            return $this->insertarLineas($this->turnos->lineas($calendario->CalendarioId, $datos['FechaInicial'], $datos['FechaFinal'], $datos['Turnos']));
        });
    }

    public function eliminar(ReqCalendarioTab $calendario): ResultadoCatalogo
    {
        $enUso = ReqProgramaTejido::where('CalendarioId', $calendario->CalendarioId)->count();
        if ($enUso > 0) {
            return ResultadoCatalogo::rechazo("No se puede eliminar el calendario porque esta siendo utilizado por {$enUso} programa(s) de tejido.");
        }

        DB::transaction(function () use ($calendario): void {
            ReqCalendarioLine::where('CalendarioId', $calendario->CalendarioId)->delete();
            $calendario->delete();
        });

        return ResultadoCatalogo::ok('Calendario y sus lineas eliminados exitosamente');
    }

    /**
     * @param  array{CalendarioId: string, FechaInicio: string, FechaFin: string, HorasTurno: float|int|string, Turno: int|string}  $datos
     * @return array{linea: ReqCalendarioLine, recalculo: array<string, int|float>}
     */
    public function crearLinea(array $datos): array
    {
        $linea = ReqCalendarioLine::create($datos);

        // Solo fechas (no líneas del programa) para no pasar el tiempo límite.
        return ['linea' => $linea, 'recalculo' => $this->recalcular->recalcular(
            $datos['CalendarioId'], Carbon::parse($datos['FechaInicio']), Carbon::parse($datos['FechaFin']),
        )];
    }

    /**
     * @param  array{FechaInicio: string, FechaFin: string, HorasTurno: float|int|string, Turno: int|string}  $datos
     * @return array<string, int|float>
     */
    public function actualizarLinea(ReqCalendarioLine $linea, array $datos): array
    {
        $antes = [Carbon::parse($linea->FechaInicio), Carbon::parse($linea->FechaFin)];
        $despues = [Carbon::parse($datos['FechaInicio']), Carbon::parse($datos['FechaFin'])];
        $linea->update($datos);

        // El rango afectado cubre la posición vieja y la nueva de la línea.
        return $this->recalcular->recalcular(
            $linea->CalendarioId,
            $antes[0]->lt($despues[0]) ? $antes[0] : $despues[0],
            $antes[1]->gt($despues[1]) ? $antes[1] : $despues[1],
        );
    }

    /** @return array<string, int|float> */
    public function eliminarLinea(ReqCalendarioLine $linea): array
    {
        [$calendarioId, $ini, $fin] = [$linea->CalendarioId, Carbon::parse($linea->FechaInicio), Carbon::parse($linea->FechaFin)];
        $linea->delete();

        return $this->recalcular->recalcular($calendarioId, $ini, $fin);
    }

    /**
     * @param  list<int>  $turnos
     * @return array{eliminadas: int, recalculo?: array<string, int|float>}
     */
    public function eliminarLineasPorRango(string $calendarioId, string $fechaInicio, string $fechaFin, array $turnos): array
    {
        $desde = Carbon::parse($fechaInicio)->startOfDay();
        $hasta = Carbon::parse($fechaFin)->endOfDay();

        return DB::transaction(function () use ($calendarioId, $desde, $hasta, $turnos): array {
            $eliminadas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->whereIn('Turno', $turnos)
                ->whereBetween('FechaInicio', [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')])
                ->delete();

            return $eliminadas === 0
                ? ['eliminadas' => 0]
                : ['eliminadas' => $eliminadas, 'recalculo' => $this->recalcular->recalcular($calendarioId, $desde, $hasta)];
        });
    }

    /**
     * Reemplaza TODO con el Excel: 'calendarios' borra calendarios y líneas; 'lineas', solo líneas.
     *
     * @return array{registros_procesados: int, registros_creados: int, registros_actualizados: int, total_errores: int, errores: list<string>}
     */
    public function importarExcel(string $tipo, UploadedFile $archivo): array
    {
        $stats = DB::transaction(function () use ($tipo, $archivo): array {
            ReqCalendarioLine::query()->delete();
            if ($tipo !== 'lineas') {
                ReqCalendarioTab::query()->delete();
            }
            $import = $tipo === 'lineas' ? new ReqCalendarioLineImport : new ReqCalendarioTabImport;
            Excel::import($import, $archivo);

            return $import->getStats();
        });
        $errores = array_values(array_map('strval', (array) ($stats['errores'] ?? [])));

        return [
            'registros_procesados' => (int) ($stats['procesados'] ?? 0),
            'registros_creados' => (int) ($stats['creados'] ?? 0),
            'registros_actualizados' => (int) ($stats['actualizados'] ?? 0),
            'total_errores' => count($errores),
            'errores' => array_slice($errores, 0, 10),
        ];
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function insertarLineas(array $lineas): int
    {
        foreach (array_chunk($lineas, self::LOTE_INSERT) as $lote) {
            ReqCalendarioLine::insert($lote);
        }

        return count($lineas);
    }
}
