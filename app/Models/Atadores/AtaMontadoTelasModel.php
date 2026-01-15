<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMontadoTelasModel extends Model
{
    //
    protected $table = 'AtaMontadoTelas';
    protected $connection = 'sqlsrv';
    public $timestamps = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'Estatus',
        'Fecha',
        'Turno',
        'NoJulio',
        'NoProduccion',
        'Tipo',
        'Metros',
        'NoTelarId',
        'LoteProveedor',
        'NoProveedor',
        'MergaKg',
        'HoraParo',
        'HoraArranque',
        'Calidad',
        'Limpieza',
        'CveSupervisor',
        'NomSupervisor',
        'Obs',
        'CveTejedor',
        'NomTejedor',
        'FechaSupervisor'
    ];
}
