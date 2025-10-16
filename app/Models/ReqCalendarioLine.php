<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqCalendarioLine extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqCalendarioLine';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'CalendarioId',
        'FechaInicio',
        'FechaFin',
        'HorasTurno',
        'Turno'
    ];

    protected $casts = [
        'id' => 'integer',
        'HorasTurno' => 'float',
        'Turno' => 'integer',
        'FechaInicio' => 'datetime',
        'FechaFin' => 'datetime'
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    // Relación con calendario
    public function calendario()
    {
        return $this->belongsTo(ReqCalendarioTab::class, 'CalendarioId', 'CalendarioId');
    }

    public static function obtenerPorCalendario($calendarioId)
    {
        return self::where('CalendarioId', $calendarioId)
                  ->orderBy('FechaInicio')
                  ->get();
    }
}
