<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Tejedores\TelTelaresOperador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Quién puede hacer qué sobre una orden de trabajo mecánica.
 *
 * Permisos del módulo "Ordenes de Trabajo" (SYSRoles 193): crear/modificar/eliminar
 * son del mecánico y registrar es del supervisor (autoriza). El área decide si el
 * usuario es tejedor: el tejedor solo ve las órdenes de sus telares y, sin
 * registrar, solo califica.
 */
class OrdenTrabajoAcceso
{
    public const MODULO = 'Ordenes de Trabajo';

    /** @var list<string>|null */
    private ?array $telares = null;

    public function puede(string $accion): bool
    {
        return userCan($accion, self::MODULO);
    }

    /** Convención de la app: area = TEJEDORES / TEJEDOR. */
    public function esTejedor(): bool
    {
        $area = strtoupper(trim((string) (Auth::user()->area ?? '')));

        return in_array($area, ['TEJEDORES', 'TEJEDOR'], true);
    }

    public function puedeRegistrar(): bool
    {
        return $this->puede('registrar');
    }

    /** Tejedor sin registrar: solo califica, no captura intervenciones. */
    public function modoTejedor(): bool
    {
        return $this->esTejedor() && ! $this->puedeRegistrar();
    }

    /** Mecánico (modificar) o supervisor (registrar); nunca el tejedor en modo calificación. */
    public function puedeFinalizar(): bool
    {
        return ! $this->modoTejedor() && ($this->puede('modificar') || $this->puedeRegistrar());
    }

    /** Tejedor (área) o supervisor (registrar), una vez finalizada la orden. */
    public function puedeCalificar(): bool
    {
        return $this->esTejedor() || $this->puedeRegistrar();
    }

    /**
     * Supervisores (registrar) y el área Sistemas (Gate `admin`) corrigen renglones
     * capturados por error aunque la orden ya esté finalizada o calificada.
     */
    public function puedeEliminarLineasComoSupervisor(): bool
    {
        return $this->puedeRegistrar() || Gate::allows('admin');
    }

    /** Mecánico con `eliminar` o supervisión; el estatus lo decide bloqueaEliminarLinea(). */
    public function puedeEliminarLineas(): bool
    {
        return $this->puedeEliminarLineasComoSupervisor() || ($this->puede('eliminar') && ! $this->modoTejedor());
    }

    public function bloqueaEliminarLinea(string $estatus): bool
    {
        if ($this->puedeEliminarLineasComoSupervisor()) {
            return in_array($estatus, [MecOrdenTrabajoModel::ESTATUS_AUTORIZADO, MecOrdenTrabajoModel::ESTATUS_CANCELADO], true);
        }

        return ($estatus ?: MecOrdenTrabajoModel::ESTATUS_ACTIVO) !== MecOrdenTrabajoModel::ESTATUS_ACTIVO;
    }

    /**
     * 403 si falta el permiso, o si es tejedor en modo calificación: el mismo criterio
     * que la vista usa para mostrar los botones.
     */
    public function exigir(string $accion, string $mensaje): void
    {
        abort_unless($this->puede($accion) && ! $this->modoTejedor(), 403, $mensaje);
    }

    /** Orden por folio: 404 si no existe, 403 si es de un telar que el tejedor no tiene asignado. */
    public function orden(string $folio): MecOrdenTrabajoModel
    {
        $orden = MecOrdenTrabajoModel::find($folio);
        abort_if($orden === null, 404, 'Orden de trabajo no encontrada.');
        abort_unless($this->puedeVer($orden), 403, 'No tienes acceso a esta orden: el telar no está asignado a tu usuario.');

        return $orden;
    }

    public function linea(MecOrdenTrabajoModel $orden, int $id): MecOrdenTrabajoLineModel
    {
        $linea = $orden->lineas()->find($id);
        abort_if($linea === null, 404, 'Renglón de orden no encontrado.');

        return $linea;
    }

    /** 422 si la orden ya salió de Activo. */
    public function exigirCaptura(MecOrdenTrabajoModel $orden): void
    {
        if (! $orden->admiteCaptura()) {
            throw ValidationException::withMessages([
                'Estatus' => ['La orden ya no admite edición (finalizada, calificada, autorizada o cancelada).'],
            ]);
        }
    }

    public function puedeVer(MecOrdenTrabajoModel $orden): bool
    {
        if (! $this->esTejedor()) {
            return true;
        }

        return in_array(trim((string) $orden->TelarId), $this->telaresAsignados(), true);
    }

    /** Tejedor: solo órdenes de sus telares en TelTelaresOperador. */
    public function filtrarTelaresTejedor(Builder $query): void
    {
        if ($this->esTejedor()) {
            $query->whereIn('TelarId', $this->telaresAsignados());
        }
    }

    /** @return list<string> */
    private function telaresAsignados(): array
    {
        $numeroEmpleado = trim((string) (Auth::user()->numero_empleado ?? ''));
        if ($numeroEmpleado === '') {
            return [];
        }

        return $this->telares ??= TelTelaresOperador::query()
            ->where('numero_empleado', $numeroEmpleado)
            ->whereNotNull('NoTelarId')
            ->pluck('NoTelarId')
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }
}
