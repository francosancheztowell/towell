<?php

namespace App\Console\Commands;

use App\Services\Atadores\AtaDevolucionesEstatusSyncService;
use Illuminate\Console\Command;

class SincronizarEstatusAtaDevolucionesCommand extends Command
{
    protected $signature = 'atadores:sincronizar-estatus-devoluciones
                            {--dry-run : Solo cuenta desfasados sin corregir}';

    protected $description = 'Alinea AtaDevoluciones.Estatus con AtaMontadoTelas.Estatus (por RefId)';

    public function handle(AtaDevolucionesEstatusSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $desfasados = $syncService->contarDesfasados();

        $this->info('=== Sincronizar Estatus AtaDevoluciones ← AtaMontadoTelas ===');
        $this->line("Devoluciones desfasadas: {$desfasados}");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: no se aplicaron cambios.');

            return Command::SUCCESS;
        }

        if ($desfasados === 0) {
            $this->info('Nada que corregir.');

            return Command::SUCCESS;
        }

        $actualizados = $syncService->syncDesfasados();
        $this->info("Filas actualizadas: {$actualizados}");

        return Command::SUCCESS;
    }
}
