<?php

namespace Tests\Feature\Monitoreo\Concerns;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Str;

/**
 * Datos de prueba del panel /admin (fase 13) sobre el esquema de PreparaMonitoreo.
 */
trait SiembraPanel
{
    protected Usuario $admin;

    protected function prepararPanel(): void
    {
        $this->prepararMonitoreo();
        $this->admin = $this->crearUsuario(['numero_empleado' => '9001', 'nombre' => 'Admin Sistemas', 'area' => 'Sistemas']);
    }

    protected function dispositivo(array $atributos = []): int
    {
        return (int) $this->mon('SYSMonDispositivo')->insertGetId(array_merge([
            'Uuid' => (string) Str::uuid(),
            'Tipo' => 'tablet',
            'UaHash' => sha1('ua'),
            'UltimaIp' => '10.0.0.5',
            'PrimeraVez' => now()->subDay(),
            'UltimaActividad' => now(),
            'Visible' => true,
            'InactivoSeg' => 0,
        ], $atributos));
    }

    protected function sesion(int $dispositivoId, int $usuarioId, array $atributos = []): int
    {
        return (int) $this->mon('SYSMonSesion')->insertGetId(array_merge([
            'DispositivoId' => $dispositivoId,
            'UsuarioId' => $usuarioId,
            'Origen' => 'login',
            'Ip' => '10.0.0.5',
            'Inicio' => now()->subHour(),
            'UltimaActividad' => now(),
        ], $atributos));
    }

    protected function vista(int $dispositivoId, int $usuarioId, array $atributos = []): int
    {
        return (int) $this->mon('SYSMonVista')->insertGetId(array_merge([
            'Uuid' => (string) Str::uuid(),
            'DispositivoId' => $dispositivoId,
            'UsuarioId' => $usuarioId,
            'Ruta' => 'produccion.index',
            'Url' => '/produccionProceso',
            'Tipo' => 'carga',
            'Inicio' => now()->subMinutes(30),
        ], $atributos));
    }

    protected function error(array $atributos = []): int
    {
        return (int) $this->mon('SYSMonError')->insertGetId(array_merge([
            'Huella' => sha1(Str::random()),
            'Origen' => 'php',
            'Clase' => 'RuntimeException',
            'Mensaje' => 'Algo falló',
            'Estado' => 'nuevo',
            'Ocurrencias' => 1,
            'PrimeraVez' => now()->subDay(),
            'UltimaVez' => now(),
        ], $atributos));
    }
}
