<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para la tabla de folios: dbo.SSYSFoliosSecuencias
 * Campos esperados: Id (PK), modulo, prefijo, consecutivo
 */
class SSYSFoliosSecuencia extends Model
{
    use HasFactory;

    // Nombre real de la tabla (SQL Server + esquema)
    protected $table = 'dbo.SSYSFoliosSecuencias';

    // PK (asumimos identidad int)
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Sin timestamps
    public $timestamps = false;

    // Asignación masiva
    protected $fillable = [
        'modulo',      // nombre del módulo (texto)
        'prefijo',     // prefijo del folio (texto)
        'consecutivo', // contador numérico (int)
    ];

    protected $casts = [
        'Id'          => 'integer',
        'modulo'      => 'string',
        'prefijo'     => 'string',
        'consecutivo' => 'integer',
    ];

    /* =====================================
     | Helpers
     |===================================== */

    /**
     * Detecta nombres reales de columnas en BD para tolerar variantes como
     * 'moulo'/'modulo' y 'conseutivo'/'consecutivo'.
     */
    protected static function getColumnMap(): array
    {
        $table = (new static())->getTable();
        // Suponemos esquema dbo por el nombre $table = 'dbo.SSYSFoliosSecuencias'
        $parts = explode('.', $table);
        $schema = count($parts) === 2 ? $parts[0] : 'dbo';
        $name   = count($parts) === 2 ? $parts[1] : $table;

        $cols = collect(DB::select(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$schema, $name]
        ))->pluck('COLUMN_NAME')->map(fn($c)=>strtolower($c))->all();

        $colModulo = in_array('modulo', $cols, true) ? 'modulo' : (in_array('moulo', $cols, true) ? 'moulo' : 'modulo');
        $colConsec = in_array('consecutivo', $cols, true) ? 'consecutivo' : (in_array('conseutivo', $cols, true) ? 'conseutivo' : 'consecutivo');
        $colPref   = in_array('prefijo', $cols, true) ? 'prefijo' : 'prefijo';

        return [
            'table' => $table,
            'schema' => $schema,
            'name' => $name,
            'mod' => $colModulo,
            'con' => $colConsec,
            'pref' => $colPref,
        ];
    }

    public function scopeModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    /**
     * Obtiene y aumenta de forma segura el consecutivo para un módulo dado,
     * devolviendo el folio formado como "{prefijo}{consecutivoConPad}".
     * IMPORTANTE: requiere que exista el registro con ese "modulo".
     */
    public static function nextFolio(string $modulo, int $pad = 5): array
    {
        return DB::transaction(function () use ($modulo, $pad) {
            $c = static::getColumnMap();
            $table = $c['table'];

            // Bloquear y obtener fila por modulo
            $row = DB::table($table)->where($c['mod'], $modulo)->lockForUpdate()->first();
            if (!$row) {
                throw new \RuntimeException("No existe configuración de folio para modulo='{$modulo}'");
            }

            $current = (int)($row->{$c['con']} ?? 0);
            $next = $current + 1;
            DB::table($table)->where($c['mod'], $modulo)->update([$c['con'] => $next]);

            $pref = (string)($row->{$c['pref']} ?? '');
            $num  = str_pad((string)$next, $pad, '0', STR_PAD_LEFT);
            $folio = $pref . $num;

            return [
                'folio'       => $folio,
                'prefijo'     => $pref,
                'consecutivo' => $next,
            ];
        }, 3);
    }

    /**
     * Variante: obtiene siguiente folio buscando por prefijo (por ejemplo, 'RE').
     */
    public static function nextFolioByPrefijo(string $prefijo, int $pad = 4): array
    {
        return DB::transaction(function () use ($prefijo, $pad) {
            $c = static::getColumnMap();
            $table = $c['table'];

            // Bloquear y tratar de obtener por prefijo
            $row = DB::table($table)->where($c['pref'], $prefijo)->lockForUpdate()->first();
            if (!$row) {
                // Crear configuración por defecto si no existe (consecutivo = 0)
                DB::table($table)->insert([$c['mod'] => 'REENCONADO', $c['pref'] => $prefijo, $c['con'] => 0]);
                $row = DB::table($table)->where($c['pref'], $prefijo)->lockForUpdate()->first();
                if (!$row) {
                    throw new \RuntimeException("No fue posible crear/obtener la configuración de folio para prefijo='{$prefijo}'");
                }
            }

            $current = (int)($row->{$c['con']} ?? 0);
            $next = $current + 1;
            DB::table($table)->where($c['pref'], $prefijo)->update([$c['con'] => $next]);

            $pref = (string)($row->{$c['pref']} ?? $prefijo);
            $num  = str_pad((string)$next, $pad, '0', STR_PAD_LEFT);
            $folio = $pref . $num;

            return [
                'folio'       => $folio,
                'prefijo'     => $pref,
                'consecutivo' => $next,
            ];
        }, 3);
    }
}
