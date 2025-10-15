<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReqProgramaTejido extends Model
{
    protected $table = 'ReqProgramaTejido';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'EnProceso',
        'CuentaRizo',
        'CalibreRizo',
        'SalonTejidoId',
        'NoTelarId',
        'Ultimo',
        'CambioHilo',
        'Maquina',
        'Ancho',
        'EficienciaSTD',
        'VelocidadSTD',
        'FibraRizo',
        'CalibrePie',
        'CalendarioId',
        'TamanoClave',
        'NoExisteBase',
        'ItemId',
        'InventSizeId',
        'Rasurado',
        'NombreProducto',
        'TotalPedido',
        'Produccion',
        'SaldoPedido',
        'SaldoMarbete',
        'ProgramarProd',
        'NoProduccion',
        'Programado',
        'FlogsId',
        'NombreProyecto',
        'CustName',
        'AplicacionId',
        'Observaciones',
        'TipoPedido',
        'NoTiras',
        'Peine',
        'Luchaje',
        'PesoCrudo',
        'CalibreTrama',
        'FibraTrama',
        'DobladilloId',
        'PasadasTrama',
        'PasadasComb1',
        'PasadasComb2',
        'PasadasComb3',
        'PasadasComb4',
        'PasadasComb5',
        'AnchoToalla',
        'CodColorTrama',
        'ColorTrama',
        'CalibreComb12',
        'FibraComb1',
        'CodColorComb1',
        'NombreCC1',
        'CalibreComb22',
        'FibraComb2',
        'CodColorComb2',
        'NombreCC2',
        'CalibreComb32',
        'FibraComb3',
        'CodColorComb3',
        'NombreCC3',
        'CalibreComb42',
        'FibraComb4',
        'CodColorComb4',
        'NombreCC4',
        'CalibreComb52',
        'FibraComb5',
        'CodColorComb5',
        'NombreCC5',
        'MedidaPlano',
        'CuentaPie',
        'CodColorCtaPie',
        'NombreCPie',
        'PesoGRM2',
        'DiasEficiencia',
        'ProdKgDia',
        'StdDia',
        'ProdKgDia2',
        'StdToaHra',
        'DiasJornada',
        'HorasProd',
        'StdHrsEfect',
        'FechaInicio',
        'Calc4',
        'Calc5',
        'Calc6',
        'FechaFinal',
        'EntregaProduc',
        'EntregaPT',
        'EntregaCte',
        'PTvsCte',
        'CreatedAt',
        'UpdatedAt',
        'RowNum',
        'FibraPie',
    ];

    protected $casts = [
        'EnProceso' => 'boolean',
        'CalibreRizo' => 'float',
        'Ancho' => 'float',
        'EficienciaSTD' => 'float',
        'VelocidadSTD' => 'integer',
        'CalibrePie' => 'float',
        'TotalPedido' => 'float',
        'Produccion' => 'float',
        'SaldoPedido' => 'float',
        'SaldoMarbete' => 'integer',
        'ProgramarProd' => 'date',
        'Programado' => 'date',
        'NoTiras' => 'integer',
        'Peine' => 'integer',
        'Luchaje' => 'integer',
        'PesoCrudo' => 'integer',
        'CalibreTrama' => 'float',
        'PasadasTrama' => 'integer',
        'PasadasComb1' => 'integer',
        'PasadasComb2' => 'integer',
        'PasadasComb3' => 'integer',
        'PasadasComb4' => 'integer',
        'PasadasComb5' => 'integer',
        'AnchoToalla' => 'integer',
        'CalibreComb12' => 'float',
        'CalibreComb22' => 'float',
        'CalibreComb32' => 'float',
        'CalibreComb42' => 'float',
        'CalibreComb52' => 'float',
        'MedidaPlano' => 'integer',
        'PesoGRM2' => 'integer',
        'DiasEficiencia' => 'float',
        'ProdKgDia' => 'float',
        'StdDia' => 'float',
        'ProdKgDia2' => 'float',
        'StdToaHra' => 'float',
        'DiasJornada' => 'float',
        'HorasProd' => 'float',
        'StdHrsEfect' => 'float',
        'FechaInicio' => 'date',
        'Calc4' => 'float',
        'Calc5' => 'float',
        'Calc6' => 'float',
        'FechaFinal' => 'date',
        'EntregaProduc' => 'date',
        'EntregaPT' => 'date',
        'EntregaCte' => 'date',
        'PTvsCte' => 'integer',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
        'RowNum' => 'integer',
    ];

    /**
     * Obtener telares en proceso por tipo de salón
     */
    public static function getTelaresPorSalon($tipoSalon = 'JACQUARD')
    {
        return self::where('SalonTejidoId', $tipoSalon)
            ->where('EnProceso', 1)
            ->orderBy('NoTelarId')
            ->get();
    }

    /**
     * Obtener datos del telar en proceso
     */
    public static function getTelarEnProceso($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        return self::where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $numeroTelar)
            ->where('EnProceso', 1)
            ->first();
    }

    /**
     * Obtener la siguiente orden programada para un telar
     */
    public static function getSiguienteOrden($numeroTelar, $tipoSalon = 'JACQUARD', $fechaActual = null)
    {
        $query = self::where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $numeroTelar)
            ->where('EnProceso', 0);

        if ($fechaActual) {
            $query->where('FechaInicio', '>', $fechaActual);
        }

        return $query->orderBy('FechaInicio')
            ->first();
    }

    /**
     * Obtener todas las órdenes programadas para un telar
     */
    public static function getOrdenesProgramadas($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        return self::where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $numeroTelar)
            ->orderBy('FechaInicio')
            ->get();
    }
}
