<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdActividadesBpmModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Partes idénticas del checklist BPM de Urdido y Engomado (UrdBpmLineController y
 * EngBpmLineController). Lo que difiere entre módulos (columnas, rutas, filtro por máquina)
 * se queda en cada controller.
 */
trait ChecklistBpm
{
    /**
     * Inserta las líneas del checklist en bloque (Valor=0 = sin marcar). SQL Server acepta a lo más
     * 2100 parámetros por comando: bloques de intdiv(2099, columnas) filas.
     *
     * @param  class-string<Model>  $modelo
     * @param  iterable<int, EngActividadesBpmModel|UrdActividadesBpmModel>  $actividades
     * @param  array<string, mixed>  $comun  columnas iguales en todas las filas
     */
    private function crearLineas(string $modelo, iterable $actividades, array $comun): void
    {
        $filas = [];
        foreach ($actividades as $actividad) {
            $filas[] = $comun + ['Orden' => $actividad->Orden, 'Actividad' => $actividad->Actividad, 'Valor' => 0];
        }
        if ($filas === []) {
            return;
        }

        foreach (array_chunk($filas, intdiv(2099, count($filas[0]))) as $bloque) {
            $modelo::query()->insert($bloque);
        }
    }

    private function currentUserIsSupervisor(): bool
    {
        try {
            $this->getSupervisorInfo('validar permisos');

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function getSupervisorInfo(string $accion): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $numeroEmpleado = $user->numero_empleado ?? $user->cve ?? null;
        $sysUsuario = null;

        if ($numeroEmpleado) {
            $sysUsuario = SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }

        if (! $sysUsuario && isset($user->idusuario)) {
            $sysUsuario = SYSUsuario::where('idusuario', $user->idusuario)->first();
        }

        if (! $sysUsuario) {
            throw new \RuntimeException("No se pudo identificar el usuario para validar permisos de {$accion}.");
        }

        $puesto = mb_strtolower(trim((string) ($sysUsuario->puesto ?? '')));
        $area = mb_strtolower(trim((string) ($sysUsuario->area ?? '')));

        $esSupervisor = str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor');

        if (! $esSupervisor) {
            throw new \RuntimeException("No tienes permisos para {$accion}. Solo los supervisores pueden realizar esta acción.");
        }

        $code = $sysUsuario->numero_empleado
            ?? $user->numero_empleado
            ?? $user->cve
            ?? $user->idusuario
            ?? $user->id
            ?? null;

        $name = $sysUsuario->nombre
            ?? $user->nombre
            ?? $user->name
            ?? $user->Nombre
            ?? null;

        return [$code, $name];
    }
}
