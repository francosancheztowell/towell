<?php

namespace Tests\Feature\Tejido\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;

/**
 * Base de los tests de Tejido (19-02): reutiliza la de 19-01 (sqlite en memoria, permisos por
 * módulo, contarQueries) y agrega tablas en el esquema `dbo` para los modelos que lo escriben.
 */
trait ModuloTejido
{
    use ModuloUrdEng;

    /**
     * Tabla del modelo con sus $fillable + $casts. Si el modelo dice `dbo.X`, se crea en un
     * esquema sqlite adjunto llamado dbo (sqlite lee el prefijo como esquema).
     *
     * @param  class-string<Model>  $modelo
     * @param  array<int, string>  $extra
     */
    protected function tablaTejido(string $modelo, array $extra = []): void
    {
        $m = new $modelo;
        $tabla = $m->getTable();
        $conexion = DB::connection('sqlsrv');
        $esquema = 'main';
        if (str_starts_with(strtolower($tabla), 'dbo.')) {
            $esquema = 'dbo';
            $tabla = substr($tabla, 4);
            if (! in_array('dbo', array_column($conexion->select('PRAGMA database_list'), 'name'), true)) {
                $conexion->statement("ATTACH DATABASE ':memory:' AS dbo");
            }
        }

        $pk = $m->getKeyName();
        $casts = $m->getCasts();
        $columnas = array_values(array_unique(array_merge([$pk], $m->getFillable(), array_keys($casts), $extra)));
        if ($m->usesTimestamps()) {
            $columnas = array_values(array_unique(array_merge($columnas, ['created_at', 'updated_at'])));
        }

        $defs = [];
        foreach ($columnas as $c) {
            $tipo = strtok((string) ($casts[$c] ?? 'string'), ':');
            $defs[] = '"'.$c.'" '.match (true) {
                $c === $pk && $m->getIncrementing() => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                in_array($tipo, ['int', 'integer', 'bool', 'boolean'], true) => 'INTEGER',
                in_array($tipo, ['float', 'double', 'real', 'decimal'], true) => 'REAL',
                default => 'TEXT',
            };
        }
        $conexion->statement('CREATE TABLE '.$esquema.'."'.$tabla.'" ('.implode(', ', $defs).')');
    }
}
