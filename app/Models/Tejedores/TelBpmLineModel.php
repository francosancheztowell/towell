<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string $Folio
 * @property int|null $Orden
 * @property string|null $Actividad
 * @property string|null $Valor
 * @property string|null $TurnoRecibe
 * @property string|null $NoTelarId
 * @property string|null $SalonTejidoId
 */
class TelBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'TelBPMLine';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'integer';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'TurnoRecibe',
        'NoTelarId',
        'SalonTejidoId',
        'Orden',
        'Actividad',
        'Valor',
    ];

    protected $casts = [
        'Orden' => 'integer',
    ];
}
