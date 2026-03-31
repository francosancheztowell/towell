<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqCalendarioLine extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqCalendarioLine';

    public function getTable()
    {
        $override = config('planeacion.req_calendario_line_table');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->table;
    }

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'CalendarioId',
        'FechaInicio',
        'FechaFin',
        'HorasTurno',
        'Turno',
    ];

    protected $casts = [
        'Id' => 'integer',
        'HorasTurno' => 'float',
        'Turno' => 'integer',
        'FechaInicio' => 'datetime',
        'FechaFin' => 'datetime',
    ];

    public function getRouteKeyName()
    {
        return 'Id';
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
