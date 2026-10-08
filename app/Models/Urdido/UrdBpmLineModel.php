<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string $Folio
 * @property int|null $Orden
 * @property string|null $Actividad
 * @property string|null $Valor
 * @property string|null $TurnoRecibe
 * @property string|null $MaquinaId
 * @property string|null $Departamento
 */
class UrdBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'UrdBPMLine';

    protected $primaryKey = 'Id';  // En tu grid aparece Id

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'TurnoRecibe',
        'MaquinaId',      // visto en tu captura
        'Departamento',   // visto en tu captura
        'Orden',
        'Actividad',
        'Valor',          // NULL | 'OK' | 'X' (o lo que uses)
    ];

    protected $casts = [
        'Id' => 'integer',
        'Orden' => 'integer',
    ];
}
