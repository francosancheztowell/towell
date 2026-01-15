<?php

namespace App\Models\Planeacion\Catalogos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatCodificados extends Model
{
    use HasFactory;

    protected $table = 'CatCodificados';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false;

    public const COLUMNS = [
        'Id', 'FechaTejido', 'FechaCumplimiento', 'Departamento', 'TelarId', 'Prioridad', 'Nombre',
        'ClaveModelo', 'ItemId', 'InventSizeId', 'Tolerancia', 'CodigoDibujo', 'FechaCompromiso', 'FlogsId',
        'NombreProyecto', 'Clave', 'Cantidad', 'Peine', 'Ancho', 'Largo', 'P_crudo', 'Luchaje', 'Tra',
        'CalibreTrama2', 'CodColorTrama', 'ColorTrama', 'FibraId', 'DobladilloId', 'MedidaPlano', 'TipoRizo',
        'AlturaRizo', 'Obs', 'VelocidadSTD', 'CalibreRizo', 'CalibreRizo2', 'CuentaRizo', 'FibraRizo',
        'CalibrePie', 'CalibrePie2', 'CuentaPie', 'FibraPie', 'Comb1', 'Obs1', 'Comb2', 'Obs2', 'Comb3',
        'Obs3', 'Comb4', 'Obs4', 'MedidaCenefa', 'MedIniRizoCenefa', 'Razurada', 'NoTiras', 'Repeticiones',
        'NoMarbete', 'CambioRepaso', 'Vendedor', 'NoOrden', 'Obs5', 'TramaAnchoPeine', 'LogLuchaTotal',
        'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1', 'CalibreComb1',
        'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1', 'PasadasComb1', 'CalibreComb2',
        'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2', 'PasadasComb2', 'CalibreComb3',
        'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3', 'PasadasComb3', 'CalibreComb4',
        'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4', 'PasadasComb4', 'CalibreComb5',
        'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5', 'PasadasComb5', 'Total', 'RespInicio',
        'HrInicio', 'HrTermino', 'MinutosCambio', 'PesoMuestra', 'RegAlinacion', 'Supervisor', 'OBSParaPro',
        'CantidadProducir_2', 'Tejidas', 'pzaXrollo', 'OrdenTejido', 'JulioRizo', 'JulioPie', 'EfiInicial',
        'EfiFinal', 'DesperdicioTrama', 'Pedido', 'Produccion', 'Saldos', 'OrdCompartida',
        'OrdCompartidaLider', 'MtsRollo', 'PzasRollo', 'TotalRollos', 'TotalPzas', 'CombinaTram', 'BomId',
        'BomName', 'CreaProd', 'EficienciaSTD', 'Densidad', 'HiloAX', 'ActualizaLmat', 'FechaCreacion',
        'HoraCreacion', 'UsuarioCrea', 'FechaModificacion', 'HoraModificacion', 'UsuarioModifica',
        'CategoriaCalidad', 'CustName',
    ];

    protected $fillable = self::COLUMNS;

    protected $casts = [
        'Id' => 'integer',
        'FechaTejido' => 'date',
        'FechaCumplimiento' => 'date',
        'FechaCompromiso' => 'date',
        'TelarId' => 'integer',
        'NoMarbete' => 'float',
        'Pedido' => 'float',
        'Produccion' => 'float',
        'Saldos' => 'float',
        // Nuevos campos agregados
        'MtsRollo' => 'float', // REAL en SQL Server
        'PzasRollo' => 'float', // REAL en SQL Server
        'TotalRollos' => 'float', // REAL en SQL Server
        'TotalPzas' => 'float', // REAL en SQL Server
        'CombinaTram' => 'string', // VARCHAR(60) en SQL Server
        'BomId' => 'string', // VARCHAR(20) en SQL Server
        'BomName' => 'string', // VARCHAR(60) en SQL Server
        'CreaProd' => 'boolean', // BIT DEFAULT 1 en SQL Server
        'EficienciaSTD' => 'string', // VARCHAR en SQL Server
        'Densidad' => 'float', // REAL en SQL Server
        'HiloAX' => 'string', // VARCHAR(30) en SQL Server
        'ActualizaLmat' => 'boolean', // BIT DEFAULT 1 en SQL Server
        // Campos de auditoría
        'FechaCreacion' => 'date', // DATE en SQL Server
        'HoraCreacion' => 'string', // TIME en SQL Server
        'UsuarioCrea' => 'string', // VARCHAR(50) en SQL Server
        'FechaModificacion' => 'date', // DATE en SQL Server
        'HoraModificacion' => 'string', // TIME en SQL Server
        'UsuarioModifica' => 'string', // VARCHAR(50) en SQL Server
        'CategoriaCalidad' => 'string', // VARCHAR(50) en SQL Server
        'CustName' => 'string', // NVARCHAR(150) en SQL Server
    ];
}
