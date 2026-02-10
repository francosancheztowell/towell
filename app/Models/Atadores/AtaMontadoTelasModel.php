<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMontadoTelasModel extends Model
{
    protected $table = 'AtaMontadoTelas';
    protected $connection = 'sqlsrv';
    public $timestamps = false;
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

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
        'FechaSupervisor',
        'AX',
        'comments_sup',
        'comments_ata',
        'comments_tej',
        'ConfigId',
        'InventSizeId',
        'InventColorId',
        'HrInicio',
        'FechaArranque',
        'TiempoParo',
        'FolioParo',
    ];
}
