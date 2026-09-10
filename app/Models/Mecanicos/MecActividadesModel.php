<?php

namespace App\Models\Mecanicos;

use App\Livewire\Mecanicos\VerificaMaquina\Show;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MecActividadesModel extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'MecActividades';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Orden',
        'Actividad',
    ];

    protected $casts = [
        'Orden' => 'integer',
    ];

    /**
     * Cualquier alta/baja/cambio invalida el catálogo cacheado en Estado de
     * Máquina (Show::actividadesCatalogo), sin importar desde qué controller
     * o import se haya escrito.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Show::CACHE_KEY_ACTIVIDADES));
        static::deleted(fn () => Cache::forget(Show::CACHE_KEY_ACTIVIDADES));
    }
}
