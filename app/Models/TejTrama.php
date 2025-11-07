<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejTrama extends Model
{
    protected $table = 'TejTrama';
    protected $primaryKey = 'Folio';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'Status',
        'Turno',
        'numero_empleado',
        'nombreEmpl',
    ];

    protected $casts = [
        'Fecha' => 'date',
    ];

    public function consumos()
    {
        return $this->hasMany(TejTramaConsumos::class, 'Folio', 'Folio');
    }
}
