<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

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

    /**
     * Registra un evento en la tabla de auditoría usando stored procedure
     *
     * @param string $tabla Nombre de la tabla afectada
     * @param string $accion Acción realizada (INSERT, UPDATE, DELETE, DRAGDROP, etc.)
     * @param string $detalle Detalle del evento (ej: "Id=123", "De Salon=A Telar=1 -> Salon=B Telar=2")
     * @param Request|null $request Request actual (opcional, para obtener IP)
     * @param int|null $usuarioId ID del usuario (opcional, si no se proporciona usa Auth::id())
     * @param string|null $usuarioNombre Nombre del usuario (opcional)
     * @param string|null $ip IP del usuario (opcional)
     * @return void
     */
    public static function logEvento(
        string $tabla,
        string $accion,
        string $detalle = '',
        ?Request $request = null,
        ?int $usuarioId = null,
        ?string $usuarioNombre = null,
        ?string $ip = null
    ): void {
        try {
            // Obtener información del usuario
            if ($usuarioId === null) {
                $usuarioId = Auth::check() ? (int) Auth::id() : 0;
            }

            if ($usuarioNombre === null) {
                $usuarioNombre = self::obtenerUsuarioActual();
            }
            $usuarioNombre = substr($usuarioNombre, 0, 120);

            // Obtener IP
            if ($ip === null) {
                if ($request) {
                    $ip = $request->ip();
                } else {
                    $ip = request()->ip() ?? '0.0.0.0';
                }
            }
            $ip = substr($ip, 0, 64);

            // Ejecutar stored procedure para registrar el evento
            DB::statement("EXEC dbo.sp_LogEvento ?, ?, ?, ?, ?, ?, ?", [
                $tabla,
                $accion,
                $detalle,
                $usuarioId,
                $usuarioNombre,
                $ip,
                now()->format('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Si falla el stored procedure, registrar en log de Laravel
            Log::warning('AuditoriaHelper::logEvento falló', [
                'tabla' => $tabla,
                'accion' => $accion,
                'detalle' => $detalle,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registra un evento de drag & drop con detalles específicos
     *
     * @param string $tabla Nombre de la tabla
     * @param int $registroId ID del registro movido
     * @param array $antes Valores antes del cambio ['salon' => 'A', 'telar' => 1, 'posicion' => 5]
     * @param array $despues Valores después del cambio ['salon' => 'B', 'telar' => 2, 'posicion' => 10]
     * @param Request|null $request Request actual
     * @return void
     */
    public static function logDragDrop(
        string $tabla,
        int $registroId,
        array $antes,
        array $despues,
        ?Request $request = null
    ): void {
        $detalleAntes = [];
        $detalleDespues = [];

        foreach ($antes as $campo => $valor) {
            $detalleAntes[] = ucfirst($campo) . "={$valor}";
        }

        foreach ($despues as $campo => $valor) {
            $detalleDespues[] = ucfirst($campo) . "={$valor}";
        }

        $detalle = "Id={$registroId} | De " . implode(' ', $detalleAntes) . " -> " . implode(' ', $detalleDespues);

        self::logEvento($tabla, 'DRAGDROP', $detalle, $request);
    }

    /**
     * Registra un cambio de FechaInicio en la auditoría
     *
     * @param string $tabla Nombre de la tabla (ej: 'ReqProgramaTejido')
     * @param int $registroId ID del registro afectado
     * @param string|null $fechaAnterior Fecha anterior (formato Y-m-d H:i:s o null)
     * @param string|null $fechaNueva Fecha nueva (formato Y-m-d H:i:s o null)
     * @param string $contexto Contexto del cambio (ej: 'Actualizar Calendarios', 'Balancear', 'Snap Calendario', 'Cascada', 'Duplicar', 'Dividir')
     * @param Request|null $request Request actual (opcional)
     * @param bool|null $enProceso Si el registro está en proceso (EnProceso = 1). Si es true, se añade advertencia especial
     * @return void
     */
    public static function logCambioFechaInicio(
        string $tabla,
        int $registroId,
        ?string $fechaAnterior,
        ?string $fechaNueva,
        string $contexto = 'UPDATE',
        ?Request $request = null,
        ?bool $enProceso = null
    ): void {
        // Formatear fechas para el detalle
        $fechaAntStr = $fechaAnterior ? date('d/m/Y H:i', strtotime($fechaAnterior)) : 'N/A';
        $fechaNuevaStr = $fechaNueva ? date('d/m/Y H:i', strtotime($fechaNueva)) : 'N/A';

        // Solo registrar si realmente cambió
        if ($fechaAnterior === $fechaNueva) {
            return;
        }

        // Construir detalle base
        $detalle = "Id={$registroId} | FechaInicio: {$fechaAntStr} -> {$fechaNuevaStr} | Contexto: {$contexto}";

        self::logEvento($tabla, 'UPDATE', $detalle, $request);
    }
}
