<?php

namespace App\Observers;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Services\Atadores\AtaDevolucionesEstatusSyncService;

/**
 * Al cambiar Estatus en AtaMontadoTelas, propaga el mismo valor a AtaDevoluciones (RefId).
 */
class AtaMontadoTelasObserver
{
    public function __construct(
        private AtaDevolucionesEstatusSyncService $syncService
    ) {}

    public function updated(AtaMontadoTelasModel $montado): void
    {
        if (!$montado->wasChanged('Estatus')) {
            return;
        }

        $this->syncService->syncFromRefId(
            (int) $montado->Id,
            (string) $montado->Estatus
        );
    }
}
