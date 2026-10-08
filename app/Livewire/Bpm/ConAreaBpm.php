<?php

declare(strict_types=1);

namespace App\Livewire\Bpm;

use App\Http\Middleware\EnsureModulePermission;
use App\Services\Tejedores\OperadoresBpm;
use App\Support\Bpm\AreaBpm;
use Illuminate\Support\Facades\Auth;

/** Área y permisos de los componentes BPM (folios y checklist). */
trait ConAreaBpm
{
    public AreaBpm $area;

    /** eliminar y registrar (autorizar / rechazar) se exigen, igual que en las rutas de antes. */
    protected function exigir(string $accion): void
    {
        abort_unless(userCan($accion, $this->area->modulo()), 403, 'No tienes permiso para esta acción.');
    }

    /**
     * Crear y modificar el documento son captura de turno: siguen en modo auditar (SEC-05), no bloquean
     * pero dejan rastro en SYSMonAcceso si el usuario no tendría permiso.
     */
    protected function auditar(string $accion, string $metodo): void
    {
        if (! userCan($accion, $this->area->modulo())) {
            EnsureModulePermission::registrarDenegacion("livewire bpm.{$this->area->value}.{$metodo}", $accion, (string) $this->area->modulo());
        }
    }

    /** Supervisor por puesto o área en SYSUsuario; en Tejedores también por Telares x Operador. */
    protected function esSupervisor(): bool
    {
        $usuario = Auth::user();
        if ($usuario === null) {
            return false;
        }

        $datos = $usuario->getAttributes();
        $cargo = mb_strtolower(($datos['puesto'] ?? '').' '.($datos['area'] ?? ''));

        return str_contains($cargo, 'supervisor')
            || ($this->area === AreaBpm::Tejedores && app(OperadoresBpm::class)->esSupervisor((string) $usuario->numero_empleado));
    }
}
