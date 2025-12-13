<?php

namespace App\Models\catcodificados;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatCodificados extends Model
{
    use HasFactory;

    protected $table = 'CatCodificados';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'Id',
        'OrdenTejido', 'FechaTejido', 'FechaCumplimiento', 'Departamento', 'TelarId', 'Prioridad', 'Nombre',
        'ClaveModelo', 'ItemId', 'InventSizeId', 'Tolerancia', 'CodigoDibujo', 'FechaCompromiso', 'FlogsId',
        'NombreProyecto',

        'Clave', 'Cantidad', 'Peine', 'Ancho', 'Largo', 'P_crudo', 'Luchaje', 'Tra', 'CalibreTrama2',
        'CodColorTrama', 'ColorTrama', 'FibraId',

        'DobladilloId', 'MedidaPlano', 'TipoRizo', 'AlturaRizo', 'Obs', 'VelocidadSTD',

        'CalibreRizo', 'CalibreRizo2', 'CuentaRizo', 'FibraRizo',
        'CalibrePie', 'CalibrePie2', 'CuentaPie', 'FibraPie',

        'Comb1', 'Obs1', 'Comb2', 'Obs2', 'Comb3', 'Obs3', 'Comb4', 'Obs4',
        'MedidaCenefa', 'MedIniRizoCenefa', 'Razurada',

        'NoTiras', 'Repeticiones', 'NoMarbete', 'CambioRepaso',
        'Vendedor', 'NoOrden', 'Obs5',

        'TramaAnchoPeine', 'LogLuchaTotal',

        'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1',

        'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1', 'PasadasComb1',
        'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2', 'PasadasComb2',
        'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3', 'PasadasComb3',
        'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4', 'PasadasComb4',
        'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5', 'PasadasComb5',

        'Total',

        'RespInicio', 'HrInicio', 'HrTermino', 'MinutosCambio', 'PesoMuestra', 'RegAlinacion',
        'Supervisor', 'OBSParaPro', 'CantidadProducir_2', 'Tejidas', 'pzaXrollo',
    ];

    protected $casts = [
        'Id' => 'integer',
        'FechaTejido' => 'date',
        'FechaCumplimiento' => 'date',
        'FechaCompromiso' => 'date',
        'TelarId' => 'integer',
        'NoMarbete' => 'float',
    ];
}

