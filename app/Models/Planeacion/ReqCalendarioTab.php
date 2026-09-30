<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $CalendarioId
 * @property string|null $Nombre
 */
class ReqCalendarioTab extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqCalendarioTab';

    protected $primaryKey = 'CalendarioId';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'CalendarioId',
        'Nombre',
    ];

    public function getRouteKeyName()
    {
        return 'CalendarioId';
    }

    // Relación con líneas
    public function lineas()
    {
        return $this->hasMany(ReqCalendarioLine::class, 'CalendarioId', 'CalendarioId');
    }

    public static function obtenerTodos()
    {
        return self::orderBy('CalendarioId')->get();
    }
}
