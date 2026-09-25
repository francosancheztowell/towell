<?php

namespace App\Services\Monitoreo;

use Illuminate\Support\Facades\Auth;

/**
 * Acciones del panel /admin → SYSMonAcceso tipo admin_accion con ActorId (MON-28).
 * El cierre remoto ya se audita dentro de CierreRemotoService::solicitar().
 */
class AuditoriaAdmin
{
    public function __construct(private readonly AccesoService $accesos) {}

    public function registrar(string $accion, ?int $usuarioId = null, ?int $dispositivoId = null): void
    {
        $this->accesos->registrar('admin_accion', [
            'UsuarioId' => $usuarioId,
            'DispositivoId' => $dispositivoId,
            'ActorId' => (int) Auth::id(),
            'Motivo' => $accion,
        ]);
    }
}
