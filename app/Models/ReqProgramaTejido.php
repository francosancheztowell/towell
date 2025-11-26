<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReqProgramaTejido extends Model
{
    /** @var string */
    protected $table = 'ReqProgramaTejido';

    /** @var string */
    protected $primaryKey = 'Id';

    /** @var string */
    protected $keyType = 'int'; // BIGINT en SQL Server

    /** @var bool */
    public $incrementing = true;

    /** @var bool */
    public $timestamps = false; // Usas CreatedAt/UpdatedAt manuales

    /**
     * Mass assignment: no necesitas exponer 'Id'
     * Mantengo el listado original (menos 'Id') para compatibilidad.
     */
    protected $fillable = [
        'EnProceso','CuentaRizo','CalibreRizo','SalonTejidoId','NoTelarId','Ultimo','CambioHilo','Maquina','Ancho',
        'EficienciaSTD','VelocidadSTD','FibraRizo','CalibrePie','CalendarioId','TamanoClave','NoExisteBase',
        'InventSizeId','Produccion','SaldoPedido','SaldoMarbete','ProgramarProd','NoProduccion','Programado','FlogsId',
        'NombreProyecto','CustName','AplicacionId','Observaciones','TipoPedido','NoTiras','Peine','Luchaje','PesoCrudo',
        'CalibreTrama','FibraTrama','DobladilloId','PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3',
        'PasadasComb4','PasadasComb5','AnchoToalla','CodColorTrama','ColorTrama','CodColorC1','NomColorC1','CodColorC2',
        'NomColorC2','CodColorC3','NomColorC3','CodColorC4','NomColorC4','CodColorC5','NomColorC5','CalibreComb12',
        'FibraComb1','CodColorComb1','NombreCC1','CalibreComb22','FibraComb2','CodColorComb2','NombreCC2','CalibreComb32',
        'FibraComb3','CodColorComb3','NombreCC3','CalibreComb42','FibraComb4','CodColorComb4','NombreCC4','CalibreComb52',
        'FibraComb5','CodColorComb5','NombreCC5','MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2',
        'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect',
        'Calc4','Calc5','Calc6','EntregaProduc','EntregaPT','EntregaCte','PTvsCte','CreatedAt','UpdatedAt','RowNum',
        'FibraPie','FechaInicio','FechaFinal',
        // Nuevos
        'CalibreRizo2','CalibrePie2','CalibreTrama2','CalibreComb1','CalibreComb2','CalibreComb3','CalibreComb4','CalibreComb5',
        // Campos que también usas aunque no estuvieran arriba en fillable en tu código previo
        'ItemId','Rasurado','NombreProducto','TotalPedido',
        // Prioridad
        'Prioridad',
        // Nuevo campo
        'LargoCrudo',
        // Campo para dividir registros (relaciona registros que comparten el mismo origen)
        'OrdCompartida'
    ];

    /**
     * Casts. Mantengo tus mapeos y completo algunos boolean/int.
     * Nota: Ultimo puede ser '1'/'0'/'UL' => no castear a boolean.
     */
    protected $casts = [
        'EnProceso' => 'boolean',
        'Ultimo' => 'string',
        'CambioHilo' => 'string', // NVARCHAR(4)

        'CalibreRizo' => 'float',
        'CalibreRizo2' => 'float', // REAL en SQL Server
        'Ancho' => 'float',
        'EficienciaSTD' => 'float',
        'VelocidadSTD' => 'integer',
        'CalibrePie' => 'float',
        'CalibrePie2' => 'float', // REAL en SQL Server
        'TotalPedido' => 'float',
        'Produccion' => 'float',
        'SaldoPedido' => 'float',
        'SaldoMarbete' => 'integer',

        'ProgramarProd' => 'date', // DATE en SQL Server
        'Programado' => 'date', // DATE en SQL Server

        'NoTiras' => 'integer',
        'Peine' => 'integer',
        'Luchaje' => 'integer',
        'PesoCrudo' => 'integer',
        'CalibreTrama' => 'float',
        'CalibreTrama2' => 'float', // REAL en SQL Server

        'PasadasTrama' => 'integer',
        'PasadasComb1' => 'integer',
        'PasadasComb2' => 'integer',
        'PasadasComb3' => 'integer',
        'PasadasComb4' => 'integer',
        'PasadasComb5' => 'integer',
        'AnchoToalla' => 'float',

        'CalibreComb1' => 'string', // NVARCHAR(40) en SQL Server
        'CalibreComb2' => 'string', // NVARCHAR(40) en SQL Server
        'CalibreComb3' => 'string', // NVARCHAR(40) en SQL Server
        'CalibreComb4' => 'string', // NVARCHAR(40) en SQL Server
        'CalibreComb5' => 'string', // NVARCHAR(40) en SQL Server
        'CalibreComb12' => 'float',
        'CalibreComb22' => 'float',
        'CalibreComb32' => 'float',
        'CalibreComb42' => 'float',
        'CalibreComb52' => 'float',

        'MedidaPlano' => 'float',
        'PesoGRM2' => 'float',   // ⭐ Ahora la columna es float en BD
        'CuentaPie' => 'integer',
        'DiasEficiencia' => 'float',
        'ProdKgDia' => 'float',
        'StdDia' => 'float',
        'ProdKgDia2' => 'float',
        'StdToaHra' => 'float',
        'DiasJornada' => 'float',
        'HorasProd' => 'float',
        'StdHrsEfect' => 'float',

        'FechaInicio' => 'datetime',
        'FechaFinal' => 'datetime',

        'EntregaProduc' => 'date',
        'EntregaPT' => 'date',
        'EntregaCte' => 'datetime',

        'PTvsCte' => 'integer',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
        'RowNum' => 'integer',
        'LargoCrudo' => 'integer',
        'OrdCompartida' => 'integer', // INT para relacionar registros divididos
    ];

    /* ===========================
     |  Scopes reutilizables
     |===========================*/
    public function scopeSalon(Builder $q, string $salon): Builder
    {
        return $q->where('SalonTejidoId', $salon);
    }

    public function scopeTelar(Builder $q, $noTelar): Builder
    {
        return $q->where('NoTelarId', $noTelar);
    }

    public function scopeEnProceso(Builder $q, bool $enProceso = true): Builder
    {
        return $q->where('EnProceso', $enProceso ? 1 : 0);
    }

    public function scopeOrdenado(Builder $q): Builder
    {
        return $q->orderBy('SalonTejidoId')->orderBy('NoTelarId')->orderBy('FechaInicio','asc');
    }

    public function scopeProgramadas(Builder $q): Builder
    {
        return $q->whereNotNull('FechaInicio')->whereNotNull('FechaFinal');
    }

    /* ===========================
     |  Helpers estáticos (manteniendo API anterior)
     |===========================*/
    public static function getTelaresPorSalon($tipoSalon = 'JACQUARD')
    {
        return static::query()->salon($tipoSalon)->enProceso()->orderBy('NoTelarId')->get();
    }

    public static function getTelarEnProceso($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        return static::query()->salon($tipoSalon)->telar($numeroTelar)->enProceso()->first();
    }

    public static function getSiguienteOrden($numeroTelar, $tipoSalon = 'JACQUARD', $fechaActual = null)
    {
        $q = static::query()->salon($tipoSalon)->telar($numeroTelar)->enProceso(false);
        if ($fechaActual) $q->where('FechaInicio','>',$fechaActual);
        return $q->orderBy('FechaInicio')->first();
    }

    public static function getOrdenesProgramadas($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        return static::query()->salon($tipoSalon)->telar($numeroTelar)->orderBy('FechaInicio')->get();
    }

    /* ===========================
     |  Relaciones (si existen modelos)
     |===========================*/
    public function lineas()
    {
        // Si tienes el modelo ReqProgramaTejidoLine, descomenta y ajusta
        // return $this->hasMany(ReqProgramaTejidoLine::class, 'ProgramaId', 'Id');
        return $this->hasMany(\App\Models\ReqProgramaTejidoLine::class, 'ProgramaId', 'Id');
    }
}
