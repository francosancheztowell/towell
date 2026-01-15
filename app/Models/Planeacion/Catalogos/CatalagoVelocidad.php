<?php

namespace App\Models\Planeacion\Catalogos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalagoVelocidad extends Model
{
    use HasFactory;

    // Si el nombre de la tabla no sigue la convención (en plural), se especifica
    protected $table = 'catalago_velocidad';

    // Si la tabla no tiene timestamps, puedes deshabilitarlos:
    public $timestamps = false;

    // Si quieres proteger ciertas columnas de la asignación masiva:
    protected $fillable = ['telar', 'tipo_hilo', 'velocidad', 'densidad'];
}
