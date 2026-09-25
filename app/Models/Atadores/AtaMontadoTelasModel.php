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
        'no_julio2', 'no_julio3', 'no_julio4',
        'no_orden2', 'no_orden3', 'no_orden4',
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
