<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TejNotificaTejedorModel extends Model
{
    use HasFactory;

    protected $table = 'TejNotificaTejedor';

    protected $fillable = [
        'telar',
        'tipo',
        'hora',
        'NomEmpleado',
        'NoEmpleado',
        'Reserva'
    ];

    public $timestamps = false;
}
