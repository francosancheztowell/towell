<?php

namespace App\Services\Monitoreo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de accesos (SYSMonAcceso). Nunca lanza.
 */
class AccesoService
{
    public function __construct(private readonly DispositivoService $dispositivos) {}

    /**
     * @param  array{NumeroEmpleado?: mixed, UsuarioId?: ?int, DispositivoId?: ?int, Motivo?: ?string, ActorId?: ?int}  $datos
     */
    public function registrar(string $tipo, array $datos = [], ?Request $request = null): void
    {
        if (! Monitoreo::activo()) {
            return;
        }

        $request ??= request();

        Monitoreo::seguro('registrar acceso '.$tipo, function () use ($tipo, $datos, $request): void {
            $dispositivoId = array_key_exists('DispositivoId', $datos)
                ? $datos['DispositivoId']
                : $this->dispositivos->idPorUuid(DispositivoService::uuid($request), $request);

            DB::connection('sqlsrv')->table('SYSMonAcceso')->insert([
                'Fecha' => now(),
                'Tipo' => $tipo,
                'NumeroEmpleado' => Monitoreo::texto($datos['NumeroEmpleado'] ?? null, 20),
                'UsuarioId' => $datos['UsuarioId'] ?? null,
                'DispositivoId' => $dispositivoId,
                'Ip' => mb_substr(getClientIpv4(), 0, 45),
                'Motivo' => Monitoreo::texto($datos['Motivo'] ?? null, 200),
                'ActorId' => $datos['ActorId'] ?? null,
            ]);
        });
    }
}
