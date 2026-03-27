<?php

namespace App\Console\Commands;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalcularFechasProduccionCommand extends Command
{
    protected $signature = 'programa-tejido:recalcular-fechas-produccion
                            {--all : Procesar todos los registros (por defecto solo EnProceso=1 o UpdatedAt reciente)}
                            {--hours=2 : Ventana en horas para UpdatedAt cuando no se usa --all}';

    protected $description = 'Recalcula fechas de ProgramaTejido cuando Produccion/SaldoPedido fueron actualizados vía SQL externo. EnProceso=1 usa now() como inicio.';

    public function handle(): int
    {
        set_time_limit(300);
        $all = $this->option('all');
        $hours = max(1, (int) $this->option('hours'));

        $query = ReqProgramaTejido::query()
            ->whereNotNull('FechaInicio')
            ->where('FechaInicio', '!=', '');

        if ($all) {
            $registros = $query->orderBy('SalonTejidoId')->orderBy('NoTelarId')->orderBy('FechaInicio')->get();
        } else {
            $desde = now()->subHours($hours);
            $registros = $query
                ->where(function ($q) use ($desde) {
                    $q->where('EnProceso', 1)
                        ->orWhere('UpdatedAt', '>=', $desde);
                })
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->orderBy('FechaInicio')
                ->get();
        }

        if ($registros->isEmpty()) {
            $this->info('No hay registros para recalcular.');

            return self::SUCCESS;
        }

        // Detectar telares sin EnProceso=1 y asignar el primero (todos los telares, no solo los del filtro)
        $idsNuevosEnProceso = $this->asignarEnProcesoSiFalta();

        // Agregar los recién asignados al set si no estaban ya
        $idsYaEnSet = $registros->pluck('Id')->flip();
        foreach ($idsNuevosEnProceso as $nuevoId) {
            if (!$idsYaEnSet->has($nuevoId)) {
                $nuevo = ReqProgramaTejido::find($nuevoId);
                if ($nuevo) {
                    $registros->push($nuevo);
                }
            }
        }

        $this->info('Procesando ' . $registros->count() . ' registro(s)...');
        $ok = 0;
        $fail = 0;

        ReqProgramaTejido::unsetEventDispatcher();
        try {
            foreach ($registros as $r) {
                try {
                    $refreshed = ReqProgramaTejido::find($r->Id);
                    if (!$refreshed) {
                        continue;
                    }
                    if (BalancearTejido::recalcularRegistroPorProduccion($refreshed)) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                } catch (\Throwable $e) {
                    $fail++;
                    Log::error('RecalcularFechasProduccion error', [
                        'id' => $r->Id ?? null,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
        }

        $this->info("Listo: {$ok} recalculados, {$fail} omitidos/error.");

        return self::SUCCESS;
    }

    private function asignarEnProcesoSiFalta(): array
    {
        $idsAsignados = [];

        // Escanear TODOS los telares con registros (no solo los del filtro)
        $telares = ReqProgramaTejido::query()
            ->whereNotNull('FechaInicio')
            ->where('FechaInicio', '!=', '')
            ->select('SalonTejidoId', 'NoTelarId')
            ->distinct()
            ->get()
            ->map(fn($r) => $r->SalonTejidoId . '|' . $r->NoTelarId)
            ->unique()
            ->values();

        foreach ($telares as $par) {
            [$salon, $telar] = explode('|', $par);

            // Obtener todos los registros del telar ordenados por Posicion
            $todos = ReqProgramaTejido::query()
                ->where('SalonTejidoId', $salon)
                ->where('NoTelarId', $telar)
                ->whereNotNull('FechaInicio')
                ->where('FechaInicio', '!=', '')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->get(['Id', 'Posicion', 'EnProceso']);

            if ($todos->isEmpty()) {
                continue;
            }

            $primero   = $todos->first();
            $enProceso = $todos->firstWhere('EnProceso', 1);

            // Correcto: el primero ya tiene EnProceso=1
            if ($enProceso && $enProceso->Id === $primero->Id) {
                continue;
            }

            // Incorrecto o ausente: asignar EnProceso=1 al primero y limpiar el resto
            $idsOtros = $todos->where('Id', '!=', $primero->Id)->pluck('Id')->toArray();
            if ($idsOtros) {
                DB::table(ReqProgramaTejido::tableName())
                    ->whereIn('Id', $idsOtros)
                    ->update(['EnProceso' => 0, 'UpdatedAt' => now()]);
            }

            DB::table(ReqProgramaTejido::tableName())
                ->where('Id', $primero->Id)
                ->update(['EnProceso' => 1, 'UpdatedAt' => now()]);

            $idsAsignados[] = $primero->Id;

            $motivo = $enProceso ? "estaba en Pos={$enProceso->Posicion} (Id={$enProceso->Id})" : 'no había ninguno';
            $this->info("Telar {$salon}/{$telar}: EnProceso corregido → Id={$primero->Id} (Pos={$primero->Posicion}). Motivo: {$motivo}");

            Log::info('RecalcularFechasProduccion: asignado EnProceso=1', [
                'salon'          => $salon,
                'telar'          => $telar,
                'id'             => $primero->Id,
                'posicion'       => $primero->Posicion,
                'anterior_id'    => $enProceso?->Id,
                'anterior_pos'   => $enProceso?->Posicion,
            ]);
        }

        return $idsAsignados;
    }
}
