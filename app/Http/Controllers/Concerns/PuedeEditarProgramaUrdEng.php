<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/** Programar Urdido y Programar Engomado: misma regla de quién edita el tablero. */
trait PuedeEditarProgramaUrdEng
{
    /**
     * Verifica si el usuario puede editar: solo usuarios con puesto de Supervisor.
     */
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (! $usuario) {
            return false;
        }

        $puesto = trim($usuario->puesto ?? '');

        return $puesto !== '' && stripos($puesto, 'supervisor') !== false;
    }
}
