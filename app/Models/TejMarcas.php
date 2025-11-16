<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejMarcas extends Model
{
    protected $table = 'TejMarcas';
    protected $primaryKey = 'Folio';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Date',
        'Turno',
        'Status',
        'numero_empleado',
        'nombreEmpl',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'Date' => 'date',
        'Turno' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relación con las líneas de marcas
    public function marcasLine()
    {
        return $this->hasMany(TejMarcasLine::class, 'Folio', 'Folio');
    }
}






















































