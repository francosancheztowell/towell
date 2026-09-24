<?php

namespace App\Services\Monitoreo;

use Illuminate\Support\Facades\DB;

/**
 * Sesiones de monitoreo (SYSMonSesion). Una por login o restauración "recordarme".
 */
class SesionService
{
    /** Llave en la sesión Laravel; sobrevive a session()->regenerate(). */
    public const LLAVE_SESION = 'mon_sesion_id';

    private const TABLA = 'SYSMonSesion';

    /**
     * Abre una sesión y cierra como `reemplazada` las abiertas del mismo dispositivo.
     */
    public function abrir(int $usuarioId, ?int $dispositivoId, string $origen, string $ip): ?int
    {
        if ($dispositivoId === null) {
            return null;
        }

        return Monitoreo::seguro('abrir sesión', function () use ($usuarioId, $dispositivoId, $origen, $ip): int {
            $ahora = now();
            $db = DB::connection('sqlsrv');

            $db->table(self::TABLA)
                ->where('DispositivoId', $dispositivoId)
                ->whereNull('Fin')
                ->update(['Fin' => $ahora, 'MotivoFin' => 'reemplazada']);

            $id = (int) $db->table(self::TABLA)->insertGetId([
                'DispositivoId' => $dispositivoId,
                'UsuarioId' => $usuarioId,
                'Origen' => $origen,
                'Ip' => mb_substr($ip, 0, 45),
                'Inicio' => $ahora,
                'UltimaActividad' => $ahora,
            ], 'Id');

            $db->table('SYSMonDispositivo')->where('Id', $dispositivoId)->update([
                'UltimaSesionId' => $id,
                'UltimoUsuarioId' => $usuarioId,
                'UltimaActividad' => $ahora,
                'UltimaIp' => mb_substr($ip, 0, 45),
            ]);

            return $id;
        });
    }

    public function cerrar(mixed $sesionId, string $motivo): void
    {
        if (! is_numeric($sesionId)) {
            return;
        }

        Monitoreo::seguro('cerrar sesión', function () use ($sesionId, $motivo): void {
            DB::connection('sqlsrv')->table(self::TABLA)
                ->where('Id', (int) $sesionId)
                ->whereNull('Fin')
                ->update(['Fin' => now(), 'MotivoFin' => $motivo]);
        });
    }

    /** Cierra como `expirada` las sesiones sin actividad en sesion_expira_min. Lo corre el scheduler. */
    public function cerrarExpiradas(): int
    {
        $limite = now()->subMinutes((int) config('monitoreo.sesion_expira_min', 120));

        return (int) DB::connection('sqlsrv')->table(self::TABLA)
            ->whereNull('Fin')
            ->where('UltimaActividad', '<', $limite)
            ->update(['Fin' => DB::raw('UltimaActividad'), 'MotivoFin' => 'expirada']);
    }
}
