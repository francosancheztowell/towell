<?php

namespace App\Models\UrdEngomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrdEngNucleos extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'UrdEngNucleos';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Salon',
        'Nombre',
    ];

    protected $casts = [
        'Id' => 'integer',
    ];
}
