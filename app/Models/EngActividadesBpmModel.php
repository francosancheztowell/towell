<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngActividadesBpmModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv';

    protected $table = 'EngActividadesBPM';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Orden',
        'Actividad',
    ];

    protected $casts = [
        'Id'    => 'integer',
        'Orden' => 'integer',
    ];
}
