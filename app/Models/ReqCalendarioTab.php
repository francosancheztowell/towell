<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqCalendarioTab extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqCalendarioTab';
    protected $primaryKey = 'CalendarioId';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'CalendarioId',
        'Nombre'
    ];

    public function getRouteKeyName()
    {
        return 'CalendarioId';
    }

    // Relación con líneas
    public function lineas()
    {
        return $this->hasMany(ReqCalendarioLine::class, 'CalendarioId', 'CalendarioId');
    }

    public static function obtenerTodos()
    {
        return self::orderBy('CalendarioId')->get();
    }
}






































