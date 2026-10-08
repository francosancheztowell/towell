<?php

declare(strict_types=1);

namespace App\Services\Tejedores;

use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelTelaresOperador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * BPM Tejedores: quién recibe y quién entrega sale de Telares x Operador (TelTelaresOperador), no de
 * SYSUsuario por área como en Urdido / Engomado. Antes vivía en TelBpmController.
 */
class OperadoresBpm
{
    /** Telares del operador que recibe: NoTelarId => SalonTejidoId. */
    public function telares(string $numeroEmpleado): array
    {
        return TelTelaresOperador::query()
            ->where('numero_empleado', $numeroEmpleado)
            ->whereNotNull('NoTelarId')
            ->pluck('SalonTejidoId', 'NoTelarId')
            ->all();
    }

    /**
     * Quien puede entregar: los operadores (no supervisores) de los mismos telares que el que recibe,
     * con nombre y turno de SYSUsuario cuando los hay.
     *
     * @return Collection<int, array{numero: string, nombre: string, turno: string}>
     */
    public function entregadores(string $numeroRecibe): Collection
    {
        $telares = array_keys($this->telares($numeroRecibe));
        if ($telares === []) {
            return collect();
        }

        $operadores = TelTelaresOperador::query()
            ->whereIn('NoTelarId', $telares)
            ->where(fn (Builder $q) => $q->where('Supervisor', '!=', 1)->orWhereNull('Supervisor'))
            ->where('numero_empleado', '!=', $numeroRecibe)
            ->orderByDesc('Id')
            ->get(['numero_empleado', 'nombreEmpl', 'Turno'])
            ->unique('numero_empleado');

        $usuarios = SYSUsuario::query()
            ->whereIn('numero_empleado', $operadores->pluck('numero_empleado')->filter()->all())
            ->get(['numero_empleado', 'nombre', 'turno'])
            ->keyBy('numero_empleado');

        return $operadores
            ->map(fn (TelTelaresOperador $op): array => [
                'numero' => (string) $op->numero_empleado,
                'nombre' => (string) (filled($usuarios[$op->numero_empleado]->nombre ?? null) ? $usuarios[$op->numero_empleado]->nombre : $op->nombreEmpl),
                'turno' => (string) (filled($usuarios[$op->numero_empleado]->turno ?? null) ? $usuarios[$op->numero_empleado]->turno : $op->Turno),
            ])
            ->sortBy('numero', SORT_NATURAL)
            ->values();
    }

    /**
     * Turno de un empleado: SYSUsuario y, si no lo tiene, Telares x Operador. "770" y "0770" son el
     * mismo empleado; se compara en PHP (TRY_CONVERT no existe en SQL Server 2008 R2).
     */
    public function turno(string $numeroEmpleado): ?string
    {
        $sinCeros = ltrim(trim($numeroEmpleado), '0');
        if ($sinCeros === '') {
            return null;
        }

        // ponytail: LIKE '%770' recorre la tabla; SYSUsuario y TelTelaresOperador son de cientos de filas.
        $mismo = fn (string $columna) => fn ($fila): bool => ltrim(trim((string) $fila->numero_empleado), '0') === $sinCeros
            && filled($fila->{$columna});

        $turno = SYSUsuario::query()->where('numero_empleado', 'like', '%'.$sinCeros.'%')
            ->orderByDesc('Productivo')->orderByDesc('idusuario')->get(['numero_empleado', 'turno'])->first($mismo('turno'))->turno
            ?? TelTelaresOperador::query()->where('numero_empleado', 'like', '%'.$sinCeros.'%')
                ->orderByDesc('Id')->get(['numero_empleado', 'Turno'])->first($mismo('Turno'))?->Turno;

        return $turno !== null ? (string) $turno : null;
    }

    /** Supervisor por el campo Supervisor = 1 de Telares x Operador. */
    public function esSupervisor(string $numeroEmpleado): bool
    {
        return $numeroEmpleado !== ''
            && TelTelaresOperador::query()->where('numero_empleado', $numeroEmpleado)->where('Supervisor', 1)->exists();
    }
}
