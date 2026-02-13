<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditoriaUrdEng extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'AuditoriaUrdEng';

    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'Tabla',
        'RegistroId',
        'Folio',
        'Accion',
        'Campos',
        'UsuarioId',
        'UsuarioNombre',
        'CreatedAt',
    ];

    public const TABLA_URDIDO = 'UrdProgramaUrdido';
    public const TABLA_ENGOMADO = 'EngProgramaEngomado';
    public const ACCION_CREATE = 'create';
    public const ACCION_UPDATE = 'update';

    /** Longitud máxima para Campos (valor anterior -> valor nuevo). */
    private const MAX_LENGTH_CAMPOS = 2000;

    /**
     * Formatea un campo para auditoría: "NombreCampo: valorAnterior -> valorNuevo".
     */
    public static function formatoCampo(string $nombreCampo, $valorAnterior, $valorNuevo): string
    {
        $ant = $valorAnterior === null || $valorAnterior === '' ? '(vacío)' : (string) $valorAnterior;
        $nue = $valorNuevo === null || $valorNuevo === '' ? '(vacío)' : (string) $valorNuevo;
        return "{$nombreCampo}: {$ant} -> {$nue}";
    }

    /**
     * Registra en auditoría una creación o actualización en UrdProgramaUrdido o EngProgramaEngomado.
     * En update, $campos debe ser texto con formato "Campo: valorAnterior -> valorNuevo".
     */
    public static function registrar(
        string $tabla,
        int $registroId,
        ?string $folio,
        string $accion,
        ?string $campos = null
    ): void {
        try {
            $user = Auth::user();
            self::create([
                'Tabla' => $tabla,
                'RegistroId' => $registroId,
                'Folio' => $folio ?? '',
                'Accion' => $accion,
                'Campos' => $campos !== null ? substr($campos, 0, self::MAX_LENGTH_CAMPOS) : null,
                'UsuarioId' => $user?->id ?? null,
                'UsuarioNombre' => $user?->nombre ?? $user?->name ?? null,
                'CreatedAt' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
