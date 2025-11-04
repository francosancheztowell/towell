<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejEficiencia extends Model
{
    //

    protected $table = "TejEficiencia";

    protected $primaryKey = "Folio";

    protected $fillable = [
        "Folio",
        "Date",
        'Turno',
        'Status',
        'numero_empleado',
        'nombreEmpl',
        'Horario1',
        'Horario2',
        'Horario3',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = "updated_at";

    protected $casts = [
        'Date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
