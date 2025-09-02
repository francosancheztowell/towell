<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrdidoEngomado extends Model
{
    use HasFactory;

    // Nombre de la tabla (si no es el plural del nombre del modelo)
    protected $table = 'urdido_engomado';

    // Si el modelo tiene una clave primaria diferente a 'id', la definimos aquí
    protected $primaryKey = 'folio';
    public $incrementing = false;
    protected $keyType = 'string';


    // Definir si la tabla usa o no timestamps
    public $timestamps = true;

    // Los campos que se pueden llenar de forma masiva
    protected $fillable = [
        'folio',
        'cuenta',
        'urdido',
        'proveedor',
        'tipo',
        'destino',
        'metros',
        'nucleo',
        'no_telas',
        'balonas',
        'metros_tela',
        'cuendados_mini',
        'maquinaEngomado',
        'observaciones',
        'estatus_urdido',
        'estatus_engomado',
        'engomado',
        'color',
        'solidos'

    ];

    // Si se desea definir el tipo de dato de los campos, se puede hacer
    // protected $casts = [
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    // ];
}
