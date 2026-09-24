<?php

namespace App\Listeners\Monitoreo;

use App\Services\Monitoreo\AccesoService;
use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\Monitoreo;
use App\Services\Monitoreo\SesionService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Abre la SYSMonSesion y registra el acceso.
 *
 * SessionGuard dispara Login tanto en Auth::login() como al restaurar la sesión
 * con la cookie "recordarme"; se distinguen por la request: solo el POST /login
 * es un login tecleado.
 */
class RegistrarLogin
{
    public function __construct(
        private readonly DispositivoService $dispositivos,
        private readonly SesionService $sesiones,
        private readonly AccesoService $accesos,
    ) {}

    public function handle(Login $event): void
    {
        if (! Monitoreo::activo()) {
            return;
        }

        Monitoreo::seguro('listener Login', function () use ($event): void {
            /** @var Request $request */
            $request = request();
            $origen = $request->isMethod('POST') && $request->is('login') ? 'login' : 'recordarme';
            $usuarioId = (int) $event->user->getAuthIdentifier();

            [$uuid] = $this->dispositivos->asegurarUuid($request);
            $dispositivoId = $this->dispositivos->idPorUuid($uuid, $request);

            $sesionId = $this->sesiones->abrir($usuarioId, $dispositivoId, $origen, getClientIpv4());
            if ($sesionId !== null && $request->hasSession()) {
                $request->session()->put(SesionService::LLAVE_SESION, $sesionId);
            }

            $this->accesos->registrar($origen, [
                'UsuarioId' => $usuarioId,
                'DispositivoId' => $dispositivoId,
                'NumeroEmpleado' => $event->user->numero_empleado ?? null,
            ], $request);
        });
    }
}
