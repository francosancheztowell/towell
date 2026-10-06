<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $SalonTejidoId
 * @property string $NoTelarId
 * @property string $FibraId
 * @property int|float|null $Velocidad
 * @property string|null $Densidad
 */
class ReqVelocidadStd extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqVelocidadStd';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'SalonTejidoId',
        'NoTelarId',
        'FibraId',
        'Velocidad',
        'Densidad',
    ];

    protected $casts = [
        'Velocidad' => 'float',
    ];

    public function getRouteKeyName()
    {
        return 'Id';
    }

    public static function buscar($salon = null, $telar = null, $fibra = null, $densidad = null)
    {
        $query = self::query();
        if ($salon) {
            $query->where('SalonTejidoId', 'like', "%{$salon}%");
        }
        if ($telar) {
            $query->where('NoTelarId', 'like', "%{$telar}%");
        }
        if ($fibra) {
            $query->where('FibraId', 'like', "%{$fibra}%");
        }
        if ($densidad) {
            $query->where('Densidad', 'like', "%{$densidad}%");
        }

        return $query->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->orderBy('FibraId')
            ->get();
    }
}
