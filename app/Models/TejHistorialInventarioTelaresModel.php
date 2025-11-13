<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejHistorialInventarioTelaresModel extends Model
{
    protected $table = 'TejHistorialInventarioTelares';
    protected $connection = 'sqlsrv';
    public $timestamps = false;
    
    // Sin primaryKey definido ya que la tabla puede no tener PK o ser auto-increment
    public $incrementing = true;

    protected $fillable = [
        'NoTelarId',
        'Status',
        'Tipo',
        'Cuenta',
        'Calibre',
        'FechaRequerimiento',
        'Turno',
        'Fibra',
        'Metros',
        'NoJulio',
        'NoProduccion',
        'TipoAtado',
        'SalonTejidoId',
        'Localidad',
        'LoteProveedor',
        'NoProveedor',
        'HoraParo'
    ];
}
