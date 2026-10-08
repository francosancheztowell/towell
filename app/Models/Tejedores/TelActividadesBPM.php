<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Orden
 * @property string|null $Actividad
 */
class TelActividadesBPM extends Model
{
    //

    protected $table = 'TelActividadesBPM';

    protected $primaryKey = 'Orden';

    public $incrementing = true;   // IDENTITY(1,1)

    protected $keyType = 'int';

    public $timestamps = false;    // no created_at/updated_at

    protected $fillable = [
        'Actividad',
    ];

    protected $casts = [
        'Orden' => 'integer',
    ];
}
