<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejEficiencia extends Model
{
    protected $table = "TejEficiencia";

    protected $primaryKey = "Folio";

    public $incrementing = false;
    
    protected $keyType = 'string';

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

    /**
     * Relación con las líneas de eficiencia
     */
    public function lineas()
    {
        return $this->hasMany(TejEficienciaLine::class, 'Folio', 'Folio');
    }

    /**
     * Relación con el usuario (si existe la tabla de usuarios)
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\SYSUsuario::class, 'numero_empleado', 'numero_empleado');
    }
}
