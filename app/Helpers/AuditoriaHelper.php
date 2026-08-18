<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditoriaHelper
{
    /**
     * Aplica los campos de auditoría (creación y modificación) a un modelo
     *
     * @param  Model  $modelo  El modelo al que se aplicarán los campos
     * @param  bool  $soloModificacion  Si es true, solo actualiza campos de modificación
     */
    public static function aplicarCamposAuditoria(Model $modelo, bool $soloModificacion = false): void
    {
        $usuario = self::obtenerUsuarioActual();
        $fechaActual = now();
        $table = $modelo->getTable();
        $columns = Schema::getColumnListing($table);

        // Campos de creación (solo si no es solo modificación y no existen)
        if (! $soloModificacion) {
            if (in_array('FechaCreacion', $columns, true) && ! $modelo->FechaCreacion) {
                $modelo->setAttribute('FechaCreacion', $fechaActual);
            }
            if (in_array('HoraCreacion', $columns, true) && ! $modelo->HoraCreacion) {
                $modelo->setAttribute('HoraCreacion', $fechaActual->format('H:i:s'));
            }
            if (in_array('UsuarioCrea', $columns, true) && ! $modelo->UsuarioCrea) {
                $modelo->setAttribute('UsuarioCrea', $usuario);
            }
            if (in_array('CreatedAt', $columns, true) && ! $modelo->CreatedAt) {
                $modelo->setAttribute('CreatedAt', $fechaActual);
            }
            if (in_array('CreatedBy', $columns, true) && ! $modelo->CreatedBy) {
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
     */
    public static function obtenerUsuarioActual(): string
    {
        if (! Auth::check() || ! Auth::user()) {
            return 'Sistema';
        }

        $user = Auth::user();

        return $user->nombre ?? $user->numero_empleado ?? 'Sistema';
    }

    /**
     * Sella el CONTEXT_INFO de SQL Server con el "por qué" de la operación que sigue.
     *
     * tr_ReqProgramaTejido_Audit lo lee y lo escribe en SYSAuditoria.Detalle, así una
     * sola fila dice qué cambió y por qué (LIBERAR, ELIMINAR_DESARROLLADORES, ...).
     * Llamar justo antes del INSERT/UPDATE/DELETE, dentro de la misma conexión.
     *
     * ponytail: el contexto queda pegado a la conexión hasta que alguien lo vuelva a sellar.
     * En web no importa: SetSqlContextInfo lo reinicia (sin acción) al inicio de cada request.
     * Si un worker de cola llegara a encadenar operaciones distintas sobre la misma conexión,
     * la segunda heredaría el contexto de la primera; ahí habría que sellar antes de cada una.
     *
     * @param  string  $accion  Contexto de negocio, en MAYUSCULAS, máx. 30 chars
     */
    public static function contexto(string $accion): void
    {
        try {
            $connection = DB::connection();

            // En pruebas la conexión puede ser sqlite; el EXEC solo aplica a SQL Server.
            if ($connection->getDriverName() !== 'sqlsrv') {
                return;
            }

            $usuario = self::obtenerUsuarioActual();

            $connection->statement('EXEC dbo.sp_SetAppContext ?, ?, ?, ?', [
                Auth::check() ? (int) Auth::id() : null,
                $usuario,
                substr((string) (request()?->ip() ?? ''), 0, 64),
                substr($accion, 0, 30),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe tumbar una operación de negocio.
            Log::warning('AuditoriaHelper::contexto falló', [
                'accion' => $accion,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
