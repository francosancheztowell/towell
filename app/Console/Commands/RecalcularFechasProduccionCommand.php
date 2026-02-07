<?php

namespace App\Console\Commands;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Console\Command;
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
}
