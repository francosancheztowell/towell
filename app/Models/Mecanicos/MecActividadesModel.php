<?php

namespace App\Models\Mecanicos;

use Illuminate\Database\Eloquent\Model;

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
}
