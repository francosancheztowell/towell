<?php

namespace App\Services\Monitoreo;

use App\Listeners\Monitoreo\RegistrarLogout;
use App\Models\Sistema\Monitoreo\MonDispositivo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre remoto de sesión por dispositivo (contrato §5).
 *
 * solicitar() lo usa el panel /admin (fase 13); aplicarSiCorresponde() lo usa el
 * middleware AplicarCierreRemoto y el latido responde `cerrar` con pendiente().
 */
class CierreRemotoService
{
    public const MENSAJE = 'Un administrador cerró la sesión de este equipo.';

    public function __construct(private readonly AccesoService $accesos) {}

    public static function llave(string $uuid): string
    {
        return 'mon:cerrar:'.$uuid;
    }

    public function solicitar(MonDispositivo $dispositivo, Authenticatable $admin): void
    {
        $adminId = (int) $admin->getAuthIdentifier();

        $dispositivo->forceFill([
            'CierreSolicitadoEn' => now(),
            'CierreSolicitadoPor' => $adminId,
        ])->save();

        Cache::put(self::llave(strtolower((string) $dispositivo->Uuid)), true, now()->addDay());

        $this->accesos->registrar('admin_accion', [
            'UsuarioId' => $dispositivo->UltimoUsuarioId,
            'DispositivoId' => (int) $dispositivo->Id,
            'ActorId' => $adminId,
            'Motivo' => 'cierre_remoto',
        ]);
    }

    public function pendiente(?string $uuid): bool
    {
        return $uuid !== null && Cache::has(self::llave($uuid));
    }

    /**
     * Desloguea este dispositivo si tiene un cierre pendiente. Devuelve la
     * respuesta a enviar, o null para seguir con la request.
     */
    public function aplicarSiCorresponde(Request $request): ?Response
    {
        // El latido debe poder contestar `cerrar: true` en lugar de un 401.
        if ($request->routeIs('telemetria.*')) {
            return null;
        }

        $uuid = DispositivoService::uuid($request);
        if (! $this->pendiente($uuid) || ! Auth::check()) {
            return null;
        }

        $request->attributes->set(RegistrarLogout::MOTIVO, 'remoto');
        Auth::guard()->logoutCurrentDevice();
        Cache::forget(self::llave($uuid));

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => self::MENSAJE, 'code' => 'cierre_remoto'], 401);
        }

        return redirect()->route('login')->with('error', self::MENSAJE);
    }
}
