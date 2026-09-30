<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AtaComentariosModel extends Model
{
    protected $table = 'AtaComentarios';

    protected $connection = 'sqlsrv';

    protected $primaryKey = 'Nota1';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'Nota1',
        'Nota2',
    ];

    /** Llave en caché de {@see tieneId()}; tras correr database/sql/atadores_comentarios_id.sql, cache:clear. */
    public const CACHE_TIENE_ID = 'atadores:comentarios:tiene-id';

    /**
     * Si dbo.AtaComentarios ya tiene la columna Id (database/sql/atadores_comentarios_id.sql).
     * Con ella la pantalla usa /comentarios/id/{Id}; sin ella, Nota1 como hasta ahora (HANDOFF 16 C3).
     */
    public static function tieneId(): bool
    {
        return (bool) Cache::remember(
            self::CACHE_TIENE_ID,
            3600,
            fn () => Schema::connection((new self)->getConnectionName())->hasColumn('AtaComentarios', 'Id')
        );
    }
}
