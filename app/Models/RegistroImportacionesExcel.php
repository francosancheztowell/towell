<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroImportacionesExcel extends Model
{
    use HasFactory;

    protected $table = 'registro_importaciones_excel';

    protected $fillable = [
        'usuario',
        'total_registros',
        'tipo_importacion',
        'archivo_original',
    ];
}
