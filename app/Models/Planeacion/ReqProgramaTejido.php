<?php

namespace App\Models\Planeacion;

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

    public function getTable()
    {
        $override = config('planeacion.programa_tejido_table');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->table;
    }

    public static function tableName(): string
    {
        $override = config('planeacion.programa_tejido_table');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return (new static())->table;
    }

    /**
     * Mass assignment: no necesitas exponer 'Id'
     * Mantengo el listado original (menos 'Id') para compatibilidad.
     */
    protected $fillable = [
        'EnProceso','CuentaRizo','CalibreRizo','SalonTejidoId','NoTelarId','Ultimo','CambioHilo','Maquina','Ancho',
        'EficienciaSTD','VelocidadSTD','FibraRizo','CalibrePie','CalendarioId','TamanoClave','NoExisteBase',
        'ItemId','InventSizeId','Rasurado','NombreProducto','TotalPedido','Produccion','SaldoPedido','SaldoMarbete',
        'ProgramarProd','NoProduccion','Programado','FlogsId','NombreProyecto','CustName','AplicacionId','Observaciones',
        'TipoPedido','NoTiras','Peine','Luchaje','PesoCrudo','CalibreTrama','FibraTrama','DobladilloId',
        'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5','AnchoToalla',
        'CodColorTrama','ColorTrama','CalibreComb12','FibraComb1','CodColorComb1','NombreCC1',
        'CalibreComb22','FibraComb2','CodColorComb2','NombreCC2','CalibreComb32','FibraComb3','CodColorComb3','NombreCC3',
        'CalibreComb42','FibraComb4','CodColorComb4','NombreCC4','CalibreComb52','FibraComb5','CodColorComb5','NombreCC5',
        'MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2','DiasEficiencia','ProdKgDia','StdDia',
        'ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','Calc4','Calc5','Calc6',
        'EntregaProduc','EntregaPT','EntregaCte','PTvsCte','CreatedAt','UpdatedAt','FibraPie','FechaInicio','FechaFinal',
        'CalibreRizo2','CalibrePie2','CalibreTrama2','CalibreComb1','CalibreComb2','CalibreComb3','CalibreComb4','CalibreComb5',
        'Prioridad','LargoCrudo','OrdCompartida','CategoriaCalidad','PorcentajeSegundos','PedidoTempo','OrdCompartidaLider','Reprogramar','Posicion',
        'MtsRollo','PzasRollo','TotalRollos','TotalPzas','Repeticiones','CombinaTram','BomId','BomName','CreaProd',
        'Densidad','HiloAX','ActualizaLmat','PesoMuestra',
        'FechaCreacion','HoraCreacion','UsuarioCrea','FechaModificacion','HoraModificacion','UsuarioModifica',
        'OrdPrincipal',
        'FechaArranque','FechaFinaliza'
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
        'PedidoTempo' => 'float',
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
        'PesoGRM2' => 'float',   //  Ahora la columna es float en BD
        'CuentaPie' => 'string', // NVARCHAR en SQL Server
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

        'PTvsCte' => 'float',
        'Densidad' => 'float',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
        'LargoCrudo' => 'integer',
        'OrdCompartida' => 'integer', // INT para relacionar registros divididos
        'OrdCompartidaLider' => 'boolean', // BIT para indicar si es el líder del grupo
        'CategoriaCalidad' => 'string', // VARCHAR en SQL Server
        'PorcentajeSegundos' => 'float',
        'Reprogramar' => 'string', // CHAR en SQL Server

        // Nuevos campos agregados
        'MtsRollo' => 'float', // REAL en SQL Server
        'PzasRollo' => 'float', // REAL en SQL Server
        'TotalRollos' => 'float', // REAL en SQL Server
        'TotalPzas' => 'float', // REAL en SQL Server
        'Repeticiones' => 'float', // REAL en SQL Server
        'CombinaTram' => 'string', // VARCHAR(60) en SQL Server
        'BomId' => 'string', // VARCHAR(20) en SQL Server
        'BomName' => 'string', // VARCHAR(60) en SQL Server
        'CreaProd' => 'boolean', // BIT DEFAULT 1 en SQL Server
        'HiloAX' => 'string', // VARCHAR(30) en SQL Server
        'ActualizaLmat' => 'boolean', // BIT DEFAULT 1 en SQL Server
        'PesoMuestra' => 'float', // REAL NULL en SQL Server
        // Campos de auditoría
        'FechaCreacion' => 'date', // DATE en SQL Server
        'HoraCreacion' => 'string', // TIME en SQL Server
        'UsuarioCrea' => 'string', // VARCHAR(50) en SQL Server
        'FechaModificacion' => 'date', // DATE en SQL Server
        'HoraModificacion' => 'string', // TIME en SQL Server
        'UsuarioModifica' => 'string', // VARCHAR(50) en SQL Server
        'OrdPrincipal' => 'integer', // INT NULL en SQL Server
        'FechaArranque' => 'datetime', // DATETIME NULL en SQL Server
        'FechaFinaliza' => 'datetime', // DATETIME NULL en SQL Server
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
        // Ordenar por salón y telar primero, luego por Posicion dentro de cada telar
        // Cada telar tiene su propia secuencia de Posicion (1, 2, 3...)
        // FechaInicio como fallback para registros sin Posicion
        return $q->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->orderBy('Posicion', 'asc') // Posicion es específica por telar (1, 2, 3... en cada telar)
            ->orderBy('FechaInicio', 'asc'); // Fallback para registros sin Posicion
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
        // Optimizado: aprovecha índice IX_ReqProgramaTejido_Telar_EnProceso_Pos
        return static::query()
            ->salon($tipoSalon)
            ->enProceso()
            ->orderBy('NoTelarId')
            ->orderBy('Posicion', 'asc') // Aprovecha índice
            ->get();
    }

    public static function getTelarEnProceso($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        // Optimizado: aprovecha índice IX_ReqProgramaTejido_Telar_EnProceso_Pos
        return static::query()
            ->salon($tipoSalon)
            ->telar($numeroTelar)
            ->enProceso()
            ->orderBy('Posicion', 'asc') // Aprovecha índice
            ->first();
    }

    public static function getSiguienteOrden($numeroTelar, $tipoSalon = 'JACQUARD', $fechaActual = null)
    {
        // Optimizado: aprovecha índice IX_ReqProgramaTejido_Telar_EnProceso_Pos
        // Orden: SalonTejidoId, NoTelarId, EnProceso, Posicion (INCLUDE: Id, FechaInicio, FechaFinal)
        $q = static::query()
            ->salon($tipoSalon)
            ->telar($numeroTelar)
            ->enProceso(false); // EnProceso = 0 en el índice

        if ($fechaActual) {
            $q->where('FechaInicio', '>', $fechaActual);
        }

        return $q->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_EnProceso_Pos
            ->orderBy('FechaInicio', 'asc') // FechaInicio está en INCLUDE
            ->first();
    }

    public static function getOrdenesProgramadas($numeroTelar, $tipoSalon = 'JACQUARD')
    {
        // Optimizado: usar Posicion primero, luego FechaInicio como fallback
        return static::query()
            ->salon($tipoSalon)
            ->telar($numeroTelar)
            ->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_Posicion
            ->orderBy('FechaInicio', 'asc') // Fallback
            ->get();
    }

    /* ===========================
     |  Relaciones (si existen modelos)
     |===========================*/
    public function lineas()
    {
        // Si tienes el modelo ReqProgramaTejidoLine, descomenta y ajusta
        // return $this->hasMany(ReqProgramaTejidoLine::class, 'ProgramaId', 'Id');
        return $this->hasMany(ReqProgramaTejidoLine::class, 'ProgramaId', 'Id');
    }
}
