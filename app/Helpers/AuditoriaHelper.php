<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditoriaHelper
{
    /**
     * Aplica los campos de auditoría (creación y modificación) a un modelo
     *
     * @param Model $modelo El modelo al que se aplicarán los campos
     * @param bool $soloModificacion Si es true, solo actualiza campos de modificación
     * @return void
     */
    public static function aplicarCamposAuditoria(Model $modelo, bool $soloModificacion = false): void
    {
        $usuario = self::obtenerUsuarioActual();
        $fechaActual = now();
        $table = $modelo->getTable();
        $columns = Schema::getColumnListing($table);

        // Campos de creación (solo si no es solo modificación y no existen)
        if (!$soloModificacion) {
            if (in_array('FechaCreacion', $columns, true) && !$modelo->FechaCreacion) {
                $modelo->setAttribute('FechaCreacion', $fechaActual);
            }
            if (in_array('HoraCreacion', $columns, true) && !$modelo->HoraCreacion) {
                $modelo->setAttribute('HoraCreacion', $fechaActual->format('H:i:s'));
            }
            if (in_array('UsuarioCrea', $columns, true) && !$modelo->UsuarioCrea) {
                $modelo->setAttribute('UsuarioCrea', $usuario);
            }
            if (in_array('CreatedAt', $columns, true) && !$modelo->CreatedAt) {
                $modelo->setAttribute('CreatedAt', $fechaActual);
            }
            if (in_array('CreatedBy', $columns, true) && !$modelo->CreatedBy) {
                $modelo->setAttribute('CreatedBy', $usuario);
            }
        }

        // Campos de modificación (siempre se actualizan)
        if (in_array('FechaModificacion', $columns, true)) {
            $modelo->setAttribute('FechaModificacion', $fechaActual);
        }
        if (in_array('HoraModificacion', $columns, true)) {
            $modelo->setAttribute('HoraModificacion', $fechaActual->format('H:i:s'));
        }
        if (in_array('UsuarioModifica', $columns, true)) {
            $modelo->setAttribute('UsuarioModifica', $usuario);
        }
        if (in_array('UpdatedAt', $columns, true)) {
            $modelo->setAttribute('UpdatedAt', $fechaActual);
        }
        if (in_array('UpdatedBy', $columns, true)) {
            $modelo->setAttribute('UpdatedBy', $usuario);
        }
    }

    /**
     * Obtiene el usuario actual para campos de auditoría
     *
     * @return string
     */
    public static function obtenerUsuarioActual(): string
    {
        if (!Auth::check() || !Auth::user()) {
            return 'Sistema';
        }

        $user = Auth::user();
        return $user->nombre ?? $user->numero_empleado ?? 'Sistema';
    }
}
