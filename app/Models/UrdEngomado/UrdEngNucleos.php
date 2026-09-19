<?php

namespace App\Models\UrdEngomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nucleos de urdido/engomado.
 *
 * OJO con la caja de la carpeta: este archivo va en app/Models/UrdEngomado/.
 * Hubo un duplicado en app/Models/urdengomado/ y borrarlo del indice tumbo el
 * archivo real al hacer pull, porque en Windows las dos rutas son la misma.
 * Si hay que tocar rutas de este modelo, hacerlo en un checkout sensible a
 * mayusculas o verificar despues que la clase sigue resolviendo.
 */
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
