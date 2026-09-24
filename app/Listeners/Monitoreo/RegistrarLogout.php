<?php

namespace App\Listeners\Monitoreo;

use App\Services\Monitoreo\AccesoService;
use App\Services\Monitoreo\Monitoreo;
use App\Services\Monitoreo\SesionService;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Cierra la SYSMonSesion en Logout y CurrentDeviceLogout.
 *
 * El cierre remoto marca la request con el atributo MOTIVO antes de desloguear,
 * para que aquí quede como `remoto` / `logout_remoto` en vez de `logout`.
 */
class RegistrarLogout
{
    public const MOTIVO = 'mon_motivo_fin';

    public function __construct(
        private readonly SesionService $sesiones,
        private readonly AccesoService $accesos,
    ) {}

    public function handle(Logout|CurrentDeviceLogout $event): void
    {
        // El tipo dice Authenticatable, pero SessionGuard::logout() despacha el evento
        // aunque no haya usuario (sesión ya expirada): ahí llega null.
        /** @var Authenticatable|null $usuario */
        $usuario = $event->user;
        if (! Monitoreo::activo() || $usuario === null) {
            return;
        }

        Monitoreo::seguro('listener Logout', function () use ($usuario): void {
            $request = request();
            $remoto = $request->attributes->get(self::MOTIVO) === 'remoto';
            $sesionId = $request->hasSession() ? $request->session()->pull(SesionService::LLAVE_SESION) : null;

            $this->sesiones->cerrar($sesionId, $remoto ? 'remoto' : 'logout');

            $this->accesos->registrar($remoto ? 'logout_remoto' : 'logout', [
                'UsuarioId' => (int) $usuario->getAuthIdentifier(),
                'NumeroEmpleado' => $usuario->numero_empleado ?? null,
            ], $request);
        });
    }
}
