<?php
declare(strict_types=1);

namespace App\Imports;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ReqProgramaTejidoUpdateImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /** Contadores */
    private int $rowCounter = 0;
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $updatedRows = 0;

    /** Cache de límites de columnas string según esquema */
    private static array $schemaStringLimits = [];

    /** Cache en memoria para ReqModelosCodificados (SalonTejidoId + TamanoClave) */
    private static array $modelosCodificadosCache = [];

    /** Cache en memoria para TwFlogsCustomer (FlogsId) */
    private static array $flogsCache = [];

    /** Cache temporal de posiciones asignadas por telar durante el batch actual */
    private static array $posicionesCachePorTelar = [];

    /** Cache para rastrear el primer registro de cada telar en el batch actual */
    private static array $primerRegistroPorTelar = [];

    /** Cache para rastrear qué telares ya fueron recalculados en este chunk */
    private static array $telaresRecalculados = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $rawRow) {
            try {
                $row = $this->normalizeRowKeys($rawRow->toArray());
                $this->rowCounter++;

                // Valores auxiliares leídos del Excel
                $colorC1Excel = $this->parseString($this->getValue($row, ['COLOR C1']), 120);
                $colorC2Excel = $this->parseString($this->getValue($row, ['COLOR C2']), 120);
                $colorC3Excel = $this->parseString($this->getValue($row, ['COLOR C3']), 120);
                $colorC4Excel = $this->parseString($this->getValue($row, ['COLOR C4']), 120);
                $colorC5Excel = $this->parseString($this->getValue($row, ['COLOR C5']), 120);

                $ultimoValue = $this->getValue($row, ['Último','Ultimo','ultimo']);

                // Si contiene "ultimo" o "ULTIMO", establecer a '1'
                $ultimoFinal = '1';
                if ($ultimoValue !== null) {
                    $ultimoStr = strtoupper(trim((string)$ultimoValue));
                    if (!str_contains($ultimoStr, 'ULTIMO')) {
                        $ultimoFinal = $this->parseString($ultimoValue, 4);
                    }
                } else {
                    $ultimoFinal = $this->parseString($ultimoValue, 4);
                }

                $data = [
                    /* ===== PRINCIPALES ===== */
                    'SalonTejidoId'   => $this->parseString($this->getValue($row, ['Salón','Salon','Salon Tejido Id','salon_tejido_id']), 20),
                    'NoTelarId'       => $this->parseString($this->getValue($row, ['Telar','No Telar','no_telar_id']), 20),
                    'Ultimo'          => $ultimoFinal,
                    'CambioHilo'      => $this->parseString($this->getValue($row, ['Cambios Hilo','Cambio Hilo','CAMBIOS HILO','CAMBIO HILO','cambio_hilo']), 4),
                    'Maquina'         => $this->parseString($this->getValue($row, ['Maq','Máq','Maquina','máquina','maquina']), 30),

                    // Producto: ampliar alias y permitir fallback luego si sigue nulo
                    'NombreProducto'  => $this->parseString($this->getValue($row, [
                        'NombreProducto','Nombre Producto','nombre_producto','producto',
                        'Nombre del Producto','Producto Final','Producto/Nombre','prod','Producto Final Nombre'
                    ]), 200),
                    'TamanoClave'     => $this->parseString($this->getValue($row, ['Clave Mod.','Clave mod.','Clave Mod','Tamaño Clave','Tamano Clave','tamano_clave']), 40),
                    'MedidaPlano'     => $this->parseFloat($this->getValue($row, ['Plano','Medida Plano','medida_plano'])),

                    /* ===== PIE ===== */
                    'CuentaPie'       => $this->parseFloat($this->getValue($row, ['Cuenta Pie','cuenta_pie'])),
                    'CodColorCtaPie'  => $this->parseString($this->getValue($row, ['Código Color Pie','Codigo Color Pie','Cod Color Cta Pie','cod color cta pie','cod_color_cta_pie']), 20),
                    'NombreCPie'      => $this->parseString($this->getValue($row, ['Color Pie','Nombre C Pie','nombre c pie','nombre_cpie']), 120),
                    'FibraPie'        => $this->parseString($this->getValue($row, ['Fibra Pie','fibra_pie','Hilo Pie','hilo_pie']), 30),
                    'AnchoToalla'     => $this->parseFloat($this->getValue($row, ['Ancho por Toalla','Ancho Toalla','ancho_toalla'])),

                    /* ===== TRAMA ===== */
                    'CodColorTrama'   => null,
                    'ColorTrama'      => null,
                    // Campo verde (*2): viene del Excel
                    'CalibreTrama2'   => $this->parseFloat($this->getValue($row, [
                                        'CALIBRE TRA','Calibre Trama','CalibreTrama','calibre_trama',
                                    ])),
                    // Campo blanco (base): se rellena desde ReqModelosCodificados
                    'CalibreTrama'    => null,
                    'FibraTrama'      => $this->parseString($this->getValue($row, ['COLOR TRA']), 30),
                    'DobladilloId'    => $this->parseString($this->getValue($row, ['Dobladillo','Dob']), 20),
                    'PasadasTrama'    => $this->parseFloat($this->getValue($row, ['Pasadas Tra','Pasadas Trama','pasadas_trama'])),

                    /* ===== COMBINACIONES 1..5 ===== */
                    // C1
                    'CalibreComb1'    => null,
                    'CalibreComb12'   => $this->parseFloat($this->getValue($row, ['Calibre C1'])),
                    'FibraComb1'      => null,
                    'CodColorComb1'   => null,
                    'NombreCC1'       => null,
                    'PasadasComb1'    => $this->parseFloat($this->getValue($row, ['Pasadas C1','Pasadas Comb1','pasadas c1','pasadas_comb1'])),

                    // C2
                    'CalibreComb2'    => null,
                    'CalibreComb22'   => $this->parseFloat($this->getValue($row, ['Calibre C2'])),
                    'FibraComb2'      => null,
                    'CodColorComb2'   => null,
                    'NombreCC2'       => null,
                    'PasadasComb2'    => $this->parseFloat($this->getValue($row, ['Pasadas C2','Pasadas Comb2','pasadas c2','pasadas_comb2'])),

                    // C3
                    'CalibreComb3'    => null,
                    'CalibreComb32'   => $this->parseFloat($this->getValue($row, ['Calibre C3'])),
                    'FibraComb3'      => null,
                    'CodColorComb3'   => null,
                    'NombreCC3'       => null,
                    'PasadasComb3'    => $this->parseFloat($this->getValue($row, ['Pasadas C3','Pasadas Comb3','pasadas c3','pasadas_comb3'])),

                    // C4
                    'CalibreComb4'    => null,
                    'CalibreComb42'   => $this->parseFloat($this->getValue($row, ['Calibre C4'])),
                    'FibraComb4'      => null,
                    'CodColorComb4'   => null,
                    'NombreCC4'       => null,
                    'PasadasComb4'    => $this->parseFloat($this->getValue($row, ['Pasadas C4','Pasadas Comb4','pasadas c4','pasadas_comb4'])),

                    // C5
                    'CalibreComb5'    => null,
                    'CalibreComb52'   => $this->parseFloat($this->getValue($row, ['Calibre C5'])),
                    'FibraComb5'      => null,
                    'CodColorComb5'   => null,
                    'NombreCC5'       => null,
                    'PasadasComb5'    => $this->parseFloat($this->getValue($row, ['Pasadas C5','Pasadas Comb5','pasadas c5','pasadas_comb5'])),

                    /* ===== NÚMEROS ===== */
                    'Peine'           => $this->parseFloat($this->getValue($row, ['Pei.','Pei','Peine','peine'])),
                    'Luchaje'         => $this->parseFloat($this->getValue($row, ['Luc','Luchaje','luchaje','LUC','LUCHE','Luch','luc'])),
                    'LargoCrudo'      => $this->parseFloat($this->getValue($row, ['Lcr','Largo Crudo','largo_crudo'])),
                    'PesoCrudo'       => $this->parseFloat($this->getValue($row, ['Pcr','Peso Crudo','peso crudo','peso_crudo'])),
                    'PesoGRM2'        => $this->parseFloat($this->getValue($row, [
                                        'PesoGRM2','Peso GRM2','peso grm2','peso_gr_m_2'
                    ])),
                    'DiasEficiencia'  => $this->parseFloat($this->getValue($row, ['Días Ef.','Dias Ef.','Días Eficiencia','Dias Eficiencia','dias_eficiencia'])),

                    /* ===== TÍTULOS SIMILARES ===== */
                    'ProdKgDia'       => $this->parseFloat($this->findFirstColumnContaining($row, ['ProdKgDia'])),
                    'StdDia'          => $this->parseFloat($this->findFirstColumnContaining($row, ['StdDia'], ['toa','hr','100','efectivo'])),
                    'ProdKgDia2'      => $this->parseFloat($this->findFirstColumnContaining($row, ['ProdKgDia2'])),
                    'StdToaHra'       => $this->parseFloat($this->findFirstColumnContaining($row, ['std','toa','hr','100'])),

                    'DiasJornada'     => $this->parseFloat($this->getValue($row, ['Días Jornada','Dias Jornada Completa','Dias jornada completa','dias_jornada','Jornada','jornada','dias jornada completa'])),
                    'HorasProd'       => $this->parseFloat($this->getValue($row, ['Horas','Horas Prod','horas prod','horas_prod'])),
                    'StdHrsEfect'     => $this->parseFloat(
                                        $this->findFirstColumnContaining($row, ['std','hr','efectivo']) ??
                                        $this->getValue($row, ['Std/Hr Efectivo','STD Hrs Efect','std_hrs_efect'])
                    ),

                    /* ===== FECHAS (DATETIME) ===== */
                    'FechaInicio'     => $this->parseDate($this->getValue($row, ['Inicio','Fecha Inicio','fecha inicio','fecha_inicio'])),
                    'FechaFinal'      => $this->parseDate($this->getValue($row, ['Fin','Fecha Final','fecha final','fecha_final'])),

                    'EntregaProduc'   => $this->parseDateOnly($this->getValue($row, ['EntregaProduc','Fecha Compromiso Prod.','Entrega Producción','Entrega Produccion','entrega_produc'])),
                    'EntregaPT'       => $this->parseDateOnly($this->getValue($row, ['EntregaPT','Fecha Compromiso PT','Entrega PT','entrega_pt'])),
                    'EntregaCte'      => $this->parseDate($this->getValue($row, ['Entrega','Entrega Cte','entrega_cte'])),
                    'PTvsCte'         => $this->parseFloat($this->getValue($row, ['Dif vs Compromiso','PT vs Cte','pt vs cte','pt_vs_cte'])),

                    /* ===== ESTADO ===== */
                    'EnProceso'       => $this->parseBoolean($this->getValue($row, ['Estado','estado','en_proceso'])),

                    /* ===== ADICIONALES ===== */
                    'CuentaRizo'      => $this->parseString($this->getValue($row, ['Cuenta','Cuenta Rizo','cuenta_rizo']), 20),
                    'CalibreRizo2'    => null,
                    'CalibreRizo'     => null,
                    'CalendarioId'    => $this->parseString($this->getValue($row, ['Jornada','jornada','calendario_id']), 30),
                    'NoExisteBase'    => $this->parseString($this->getValue($row, ['Usar cuando no existe en base','no_existe_base']), 40),
                    'ItemId'          => null,
                    'InventSizeId'    => null,
                    'Rasurado'        => null,
                    'Ancho'           => $this->parseFloat($this->getValue($row, ['Ancho','ancho'])),
                    'EficienciaSTD'   => $this->parseFloat($this->getValue($row, ['Ef Std','ef std','ef_std','eficiencia std','eficiencia_std','eficiencia'])),
                    'VelocidadSTD'    => $this->parseFloat($this->getValue($row, ['Vel','vel','velocidad','velocidad_std'])),
                    'FibraRizo'       => $this->parseString($this->getValue($row, ['Hilo','hilo','Fibra Rizo','fibra rizo','fibra_rizo']), 30),
                    'CalibrePie2'     => $this->parseFloat($this->getValue($row, ['Calibre Pie'])),
                    'CalibrePie'      => null,
                    'FlogsId'         => $this->parseString($this->getValue($row, ['Flogs','idflog','id_flog','FlogsId','flogs_id','Flogs']), 40),
                    'NombreProyecto'  => $this->parseString($this->getValue($row, ['Descrip.','Descrip','Descripción','Descripcion','nombre_proyecto']), 120),
                    'CustName'        => null,
                    'AplicacionId'    => $this->parseString($this->getValue($row, ['Aplic.','Aplic','aplicacion_id']), 20),
                    'Observaciones'   => $this->parseString($this->getValue($row, ['Obs','Observaciones','observaciones']), 200),
                    'TipoPedido'      => $this->parseString($this->getValue($row, ['Tipo Ped.','Tipo Ped','tipo_pedido']), 40),
                    'NoTiras'         => $this->parseFloat($this->getValue($row, ['Tiras','No Tiras','no_tiras'])),

                    // Pedido / Producción
                    'TotalPedido'     => $this->parseFloat($this->getValue($row, ['Saldos','Saldo Pedido','saldo_pedido','saldos','Total Pedido','total_pedido'])),
                    'Produccion'      => $this->parseFloat($this->getValue($row, ['Producción','Produccion','produccion','Producción'])),
                    'SaldoPedido'     => null,
                    'SaldoMarbete'    => $this->parseInteger($this->getValue($row, ['Saldo Marbete','saldo_marbete','Marbete','marbete'])),
                    'ProgramarProd'   => $this->parseDateOnly($this->getValue($row, ['Day Sheduling','Day Scheduling','Día Scheduling','Dia Scheduling','programar_prod'])),
                    'NoProduccion'    => $this->parseString($this->getValue($row, ['Orden Prod.','Orden Prod','no_produccion']), 30),
                    'OrdCompartida'   => $this->parseString($this->getValue($row, ['Dividir','dividir','OrdCompartida','ord_compartida']), 30),
                    'OrdPrincipal'    => $this->parseInteger($this->getValue($row, ['Principal','principal','OrdPrincipal','ord_principal'])),
                    'Programado'      => $this->parseDateOnly($this->getValue($row, ['INN','Inn','programado'])),
                    'OrdCompartidaLider' => $this->parseBoolean($this->getValue($row, ['Lider'])),
                    'Calc4'           => $this->parseFloat($this->getValue($row, ['Calc4','calc4','Calc 4'])),
                    'Calc5'           => $this->parseFloat($this->getValue($row, ['Calc5','calc5','Calc 5'])),
                    'Calc6'           => $this->parseFloat($this->getValue($row, ['Calc6','calc6','Calc 6'])),
                ];

                // SaldoPedido se calcula como TotalPedido - Produccion
                $totalPedido = $data['TotalPedido'] ?? null;
                $produccion = $data['Produccion'] ?? null;
                if ($totalPedido !== null && $produccion !== null) {
                    $data['SaldoPedido'] = $totalPedido - $produccion;
                } elseif ($totalPedido !== null) {
                    $data['SaldoPedido'] = $totalPedido;
                } else {
                    $data['SaldoPedido'] = null;
                }

                // Si viene FlogsId/IdFlog, obtener CustName y CategoriaCalidad desde TwFlogsCustomer
                if (!empty($data['FlogsId'])) {
                    $flogsId = (string)$data['FlogsId'];
                    if (!isset(self::$flogsCache[$flogsId])) {
                        $flogsConn = 'sqlsrv_ti';
                        try {
                            $flog = DB::connection($flogsConn)
                                ->table('TwFlogsCustomer')
                                ->select(['IdFlog','CustName','CategoriaCalidad'])
                                ->where('IdFlog', $flogsId)
                                ->first();
                            self::$flogsCache[$flogsId] = $flog;
                        } catch (\Throwable $e) {
                            self::$flogsCache[$flogsId] = null;
                        }
                    }

                    $flog = self::$flogsCache[$flogsId];
                    if ($flog) {
                        $data['CustName'] = $this->parseString($flog->CustName ?? null, 120);
                        $data['CategoriaCalidad'] = $this->parseString($flog->CategoriaCalidad ?? null, 20);
                    }
                }

                // Fallback: si NombreProducto viene nulo, intenta con otras columnas conocidas
                if ($data['NombreProducto'] === null) {
                    $fallbackNombre = $this->parseString($this->getValue($row, [
                        'Descrip.','Descrip','Descripción','Descripcion','Descripción Producto','Descripcion Producto'
                    ]), 200);
                    if ($fallbackNombre !== null) {
                        $data['NombreProducto'] = $fallbackNombre;
                    }
                }

                // Si la fila está vacía o sin campos relevantes, se omite
                if ($this->shouldSkipEmptyRow($data)) {
                    $this->skippedRows++;
                    continue;
                }

                // Buscar en ReqModelosCodificados por SalonTejidoId + TamanoClave y actualizar campos verdes
                $this->enrichFromModelosCodificados($data);

                // Si NombreCC1-5 no vinieron de codificados, intentar con COLOR C1-C5 del Excel
                if ($data['NombreCC1'] === null && $colorC1Excel !== null) {
                    $data['NombreCC1'] = $colorC1Excel;
                }
                if ($data['NombreCC2'] === null && $colorC2Excel !== null) {
                    $data['NombreCC2'] = $colorC2Excel;
                }
                if ($data['NombreCC3'] === null && $colorC3Excel !== null) {
                    $data['NombreCC3'] = $colorC3Excel;
                }
                if ($data['NombreCC4'] === null && $colorC4Excel !== null) {
                    $data['NombreCC4'] = $colorC4Excel;
                }
                if ($data['NombreCC5'] === null && $colorC5Excel !== null) {
                    $data['NombreCC5'] = $colorC5Excel;
                }

                // FibraComb1-5 deben reflejar NombreCC1-5
                $data['FibraComb1'] = $this->parseString($data['NombreCC1'] ?? null, 30);
                $data['FibraComb2'] = $this->parseString($data['NombreCC2'] ?? null, 30);
                $data['FibraComb3'] = $this->parseString($data['NombreCC3'] ?? null, 30);
                $data['FibraComb4'] = $this->parseString($data['NombreCC4'] ?? null, 30);
                $data['FibraComb5'] = $this->parseString($data['NombreCC5'] ?? null, 30);

                // Recorta contra el esquema real (INFORMATION_SCHEMA)
                $this->enforceSchemaStringLengths($data);

                // Buscar registro existente por NoTelarId y NoProduccion (o por telar si no hay producción)
                $salonTejidoId = $data['SalonTejidoId'] ?? null;
                $noTelarId = $data['NoTelarId'] ?? null;
                $noProduccion = $data['NoProduccion'] ?? null;

                if (!$salonTejidoId || !$noTelarId) {
                    $this->skippedRows++;
                    continue;
                }

                // Buscar registro existente
                $query = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                    ->where('NoTelarId', $noTelarId);

                if ($noProduccion) {
                    $query->where('NoProduccion', $noProduccion);
                }

                $registroExistente = $query->first();

                if (!$registroExistente) {
                    // Si no existe, crear nuevo registro (igual que el import original)
                    $this->asignarPosicion($data, $salonTejidoId, $noTelarId);
                    $data = $this->normalizeDataForBatchInsert($data);
                    ReqProgramaTejido::create($data);
                    $this->processedRows++;
                    continue;
                }

                // ACTUALIZAR registro existente (solo actualiza campos, sin recalcular fechas todavía)
                $this->updateExistingRecordFields($registroExistente, $data);
                $this->updatedRows++;
                $this->processedRows++;

                // Marcar este telar para recalcular al final del chunk
                $cacheKeyTelar = $salonTejidoId . '|' . $noTelarId;
                if (!isset(self::$telaresRecalculados[$cacheKeyTelar])) {
                    self::$telaresRecalculados[$cacheKeyTelar] = [
                        'salonTejidoId' => $salonTejidoId,
                        'noTelarId' => $noTelarId
                    ];
                }

            } catch (\Throwable $e) {
                Log::error('Import PT Update: error en fila', [
                    'row_num' => $this->rowCounter,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->skippedRows++;
            }
        }

        // Al final del chunk, recalcular fechas para todos los telares que fueron actualizados
        foreach (self::$telaresRecalculados as $telarInfo) {
            try {
                $this->recalcularFechasTelar($telarInfo['salonTejidoId'], $telarInfo['noTelarId']);
            } catch (\Throwable $e) {
                Log::error('Import PT Update: error al recalcular telar', [
                    'salonTejidoId' => $telarInfo['salonTejidoId'],
                    'noTelarId' => $telarInfo['noTelarId'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Limpiar cache de telares recalculados para el siguiente chunk
        self::$telaresRecalculados = [];
    }

    /**
     * Actualiza solo los campos de un registro existente (sin recalcular fechas)
     */
    private function updateExistingRecordFields(ReqProgramaTejido $registro, array $data): void
    {
        // Obtener columnas válidas del modelo
        $validColumns = $this->getValidColumns();

        // Actualizar solo campos válidos que vienen del Excel
        foreach ($data as $key => $value) {
            if (in_array($key, $validColumns, true)) {
                // No actualizar campos que se recalcularán después (fechas, EnProceso, Posicion)
                if (!in_array($key, ['FechaInicio', 'FechaFinal', 'EnProceso', 'Posicion', 'Ultimo', 'CambioHilo'], true)) {
                    $registro->setAttribute($key, $value);
                }
            }
        }

        $registro->save();
    }

    /**
     * Recalcula fechas para todos los registros de un telar
     */
    private function recalcularFechasTelar(string $salonTejidoId, string $noTelarId): void
    {
        // Obtener TODOS los registros del telar ordenados por Posicion y FechaInicio
        $todosRegistrosTelar = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        if ($todosRegistrosTelar->isEmpty()) {
            return;
        }

        // Encontrar el registro que inicia primero (menor FechaInicio)
        $primerRegistro = $todosRegistrosTelar->sortBy(function($r) {
            if (empty($r->FechaInicio)) {
                return PHP_INT_MAX; // Sin fecha = último
            }
            try {
                return Carbon::parse($r->FechaInicio)->timestamp;
            } catch (\Throwable $e) {
                return PHP_INT_MAX;
            }
        })->first();

        if (!$primerRegistro || empty($primerRegistro->FechaInicio)) {
            return;
        }

        // Obtener FechaInicio del primer registro como punto de inicio
        try {
            $inicioOriginal = Carbon::parse($primerRegistro->FechaInicio);
        } catch (\Throwable $e) {
            return;
        }

        // Primero, asegurarse de que TODOS los registros del telar tengan EnProceso = 0
        // Esto garantiza que se quite el EnProceso de los registros que ya no deberían tenerlo
        DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->update(['EnProceso' => 0]);

        // Recalcular fechas para todos los registros del telar
        [$updates] = DateHelpers::recalcularFechasSecuencia($todosRegistrosTelar, $inicioOriginal, true);

        if (empty($updates)) {
            return;
        }

        // Actualizar registros en batch
        $idsActualizar = array_keys($updates);

        // Evitar colisiones de Posicion durante el update
        DB::table('ReqProgramaTejido')
            ->whereIn('Id', $idsActualizar)
            ->where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->update(['Posicion' => DB::raw('Posicion + 10000')]);

        // Actualizar cada registro con sus nuevos valores (incluyendo EnProceso = 1 para el primero)
        foreach ($updates as $idU => $dataU) {
            if (isset($dataU['Posicion'])) {
                $dataU['Posicion'] = (int) $dataU['Posicion'];
            }

            // Asegurarse de que EnProceso esté en el array de actualización
            // DateHelpers::recalcularFechasSecuencia ya debería incluir esto
            if (!isset($dataU['EnProceso'])) {
                // Si no está, significa que no es el primer registro, así que debe ser 0
                $dataU['EnProceso'] = 0;
            }

            DB::table('ReqProgramaTejido')
                ->where('Id', $idU)
                ->where('SalonTejidoId', $salonTejidoId)
                ->where('NoTelarId', $noTelarId)
                ->update($dataU);
        }
    }

    /**
     * Actualiza un registro existente y recalcula fechas para el telar completo
     * @deprecated Usar updateExistingRecordFields + recalcularFechasTelar al final del chunk
     */
    private function updateExistingRecord(ReqProgramaTejido $registro, array $data, string $salonTejidoId, string $noTelarId): void
    {
        // Obtener columnas válidas del modelo
        $validColumns = $this->getValidColumns();

        // Actualizar solo campos válidos que vienen del Excel
        foreach ($data as $key => $value) {
            if (in_array($key, $validColumns, true)) {
                // No actualizar campos que se recalcularán (fechas, EnProceso, Posicion)
                if (!in_array($key, ['FechaInicio', 'FechaFinal', 'EnProceso', 'Posicion', 'Ultimo', 'CambioHilo'], true)) {
                    $registro->setAttribute($key, $value);
                }
            }
        }

        $registro->save();

        // Obtener TODOS los registros del telar ordenados por Posicion y FechaInicio
        $todosRegistrosTelar = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        if ($todosRegistrosTelar->isEmpty()) {
            return;
        }

        // Encontrar el registro que inicia primero (menor FechaInicio)
        $primerRegistro = $todosRegistrosTelar->sortBy(function($r) {
            if (empty($r->FechaInicio)) {
                return PHP_INT_MAX; // Sin fecha = último
            }
            try {
                return Carbon::parse($r->FechaInicio)->timestamp;
            } catch (\Throwable $e) {
                return PHP_INT_MAX;
            }
        })->first();

        if (!$primerRegistro || empty($primerRegistro->FechaInicio)) {
            return;
        }

        // Obtener FechaInicio del primer registro como punto de inicio
        try {
            $inicioOriginal = Carbon::parse($primerRegistro->FechaInicio);
        } catch (\Throwable $e) {
            return;
        }

        // Recalcular fechas para todos los registros del telar
        [$updates] = DateHelpers::recalcularFechasSecuencia($todosRegistrosTelar, $inicioOriginal, true);

        if (empty($updates)) {
            return;
        }

        // Primero, asegurarse de que TODOS los registros del telar tengan EnProceso = 0
        // Esto garantiza que se quite el EnProceso de los registros que ya no deberían tenerlo
        DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->update(['EnProceso' => 0]);

        // Actualizar registros en batch
        $idsActualizar = array_keys($updates);

        // Evitar colisiones de Posicion durante el update
        DB::table('ReqProgramaTejido')
            ->whereIn('Id', $idsActualizar)
            ->where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->update(['Posicion' => DB::raw('Posicion + 10000')]);

        // Actualizar cada registro con sus nuevos valores (incluyendo EnProceso = 1 para el primero)
        foreach ($updates as $idU => $dataU) {
            if (isset($dataU['Posicion'])) {
                $dataU['Posicion'] = (int) $dataU['Posicion'];
            }

            // Asegurarse de que EnProceso esté en el array de actualización
            // DateHelpers::recalcularFechasSecuencia ya debería incluir esto, pero lo verificamos
            if (!isset($dataU['EnProceso'])) {
                // Si no está, significa que no es el primer registro, así que debe ser 0
                $dataU['EnProceso'] = 0;
            }

            DB::table('ReqProgramaTejido')
                ->where('Id', $idU)
                ->where('SalonTejidoId', $salonTejidoId)
                ->where('NoTelarId', $noTelarId)
                ->update($dataU);
        }
    }

    /**
     * Asigna posición para nuevo registro (igual que el import original)
     */
    private function asignarPosicion(array &$data, ?string $salonTejidoId, ?string $noTelarId): void
    {
        if (!isset($data['Posicion']) || $data['Posicion'] === null || $data['Posicion'] === '') {
            if ($salonTejidoId && $noTelarId) {
                $cacheKey = $salonTejidoId . '|' . $noTelarId;

                if (!isset(self::$primerRegistroPorTelar[$cacheKey])) {
                    self::$primerRegistroPorTelar[$cacheKey] = true;
                    $data['EnProceso'] = 1;
                }

                if (!isset(self::$posicionesCachePorTelar[$cacheKey])) {
                    self::$posicionesCachePorTelar[$cacheKey] = [];
                }

                $siguientePosicion = \App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers::obtenerSiguientePosicionDisponible($salonTejidoId, $noTelarId);

                while (in_array($siguientePosicion, self::$posicionesCachePorTelar[$cacheKey], true)) {
                    $siguientePosicion++;
                }

                self::$posicionesCachePorTelar[$cacheKey][] = $siguientePosicion;
                $data['Posicion'] = $siguientePosicion;
            }
        }
    }

    public function chunkSize(): int { return 500; }

    public function getStats(): array
    {
        return [
            'processed_rows' => $this->processedRows,
            'updated_rows' => $this->updatedRows,
            'skipped_rows' => $this->skippedRows,
            'total_rows' => $this->rowCounter,
        ];
    }

    /* ====================== Helpers de parseo (copiados del import original) ====================== */

    private function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') return null;
        if (is_bool($value)) return $value;
        $v = mb_strtolower(trim((string)$value), 'UTF-8');
        return in_array($v, ['true','1','yes','si','sí','verdadero','x','ok'], true)
            || in_array($v, ['en proceso','en_proceso'], true);
    }

    private function parseFloat($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float)$value;
        $s = (string)$value;
        if (str_contains($s, '/')) {
            $parts = explode('/', $s);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $den = (float)$parts[1];
                if ($den != 0.0) return (float)$parts[0] / $den;
            }
        }
        $isPercent = str_contains($s, '%');
        $s = str_replace(['%',' '], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-' || $s === '.') return null;
        $num = (float)$s;
        return $isPercent ? $num / 100.0 : $num;
    }

    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int)$value;
        $s = preg_replace('/[^\d\-]/', '', (string)$value) ?? '';
        return ($s === '' || $s === '-') ? null : (int)$s;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(\DateTime::createFromInterface($value))->format('Y-m-d H:i:s');
            }
            if (is_numeric($value)) {
                $n = (float)$value;
                if ($n > 0 && $n < 100000) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n);
                    return Carbon::instance($dt)->format('Y-m-d H:i:s');
                }
                if ($n >= 631152000 && $n <= 4102444800) {
                    return Carbon::createFromTimestamp((int)$n)->format('Y-m-d H:i:s');
                }
            }
            $s = (string)$value;
            $s = preg_replace('/[\x{00A0}\x{2000}-\x{200B}]/u', ' ', $s);
            $s = preg_replace('/\s+/', ' ', trim($s));
            $s = preg_replace('/\b(a\s*\.?\s*m\.?)\b/iu', 'AM', $s);
            $s = preg_replace('/\b(p\s*\.?\s*m\.?)\b/iu', 'PM', $s);
            $formatos = [
                'd/m/Y h:i:s A','d-m-Y h:i:s A','d/m/Y h:i A','d-m-Y h:i A',
                'd/m/Y g:i:s A','d-m-Y g:i:s A','d/m/Y g:i A','d-m-Y g:i A',
                'd/m/Y H:i:s','d-m-Y H:i:s','d/m/Y H:i','d-m-Y H:i',
                'Y-m-d H:i:s','Y-m-d H:i','Y/m/d H:i:s','Y/m/d H:i',
                'd/m/Y','d-m-Y','Y-m-d','Y/m/d',
            ];
            foreach ($formatos as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, $s);
                    if ($c !== false) return $c->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {}
            }
            if ($out = $this->parseDiaMesNombre($s)) return $out;
            if ($out = $this->parseDiaMesNombreConAno($s)) return $out;
            return Carbon::parse($s)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDiaMesNombre(string $s): ?string
    {
        if (!preg_match('/^(\d{1,2})[\/\-\s]([a-zA-Záéíóúñ]+)(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?$/u', $s, $m)) {
            return null;
        }
        $mes = $this->mesNumero($m[2]);
        if (!$mes) return null;
        $d = (int)$m[1];
        $y = (int)date('Y');
        $h = isset($m[3]) ? (int)$m[3] : 9;
        $i = isset($m[4]) ? (int)$m[4] : 0;
        $s2 = isset($m[5]) ? (int)$m[5] : 0;
        $ampm = isset($m[6]) ? strtoupper($m[6]) : null;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        if ($ampm === 'PM' && $h < 12)  $h += 12;
        $str = sprintf('%04d-%s-%02d %02d:%02d:%02d', $y, $mes, $d, $h, $i, $s2);
        return $this->esFechaValida($str) ? $str : null;
    }

    private function parseDiaMesNombreConAno(string $s): ?string
    {
        if (!preg_match('/^(\d{1,2})[\/\-\s]([a-zA-Záéíóúñ]+)[\/\-\s](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?$/u', $s, $m)) {
            return null;
        }
        $mes = $this->mesNumero($m[2]);
        if (!$mes) return null;
        $d = (int)$m[1];
        $y = (int)$m[3];
        if ($y < 100) $y += ($y < 50) ? 2000 : 1900;
        $h = isset($m[4]) ? (int)$m[4] : 9;
        $i = isset($m[5]) ? (int)$m[5] : 0;
        $s2 = isset($m[6]) ? (int)$m[6] : 0;
        $ampm = isset($m[7]) ? strtoupper($m[7]) : null;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        if ($ampm === 'PM' && $h < 12)  $h += 12;
        $str = sprintf('%04d-%s-%02d %02d:%02d:%02d', $y, $mes, $d, $h, $i, $s2);
        return $this->esFechaValida($str) ? $str : null;
    }

    private function mesNumero(string $nombre): ?string
    {
        $nombre = mb_strtolower($this->removeAccents($nombre), 'UTF-8');
        $meses = [
            'enero'=>'01','ene'=>'01','febrero'=>'02','feb'=>'02','marzo'=>'03','mar'=>'03','abril'=>'04','abr'=>'04',
            'mayo'=>'05','junio'=>'06','jun'=>'06','julio'=>'07','jul'=>'07','agosto'=>'08','ago'=>'08',
            'septiembre'=>'09','setiembre'=>'09','sept'=>'09','set'=>'09','sep'=>'09',
            'octubre'=>'10','oct'=>'10','noviembre'=>'11','nov'=>'11','diciembre'=>'12','dic'=>'12',
            'january'=>'01','jan'=>'01','february'=>'02','march'=>'03','april'=>'04','apr'=>'04','may'=>'05',
            'june'=>'06','july'=>'07','august'=>'08','aug'=>'08','september'=>'09','october'=>'10','november'=>'11','december'=>'12','dec'=>'12',
        ];
        return $meses[$nombre] ?? null;
    }

    private function esFechaValida(string $fecha): bool
    {
        $partes = explode(' ', $fecha)[0] ?? $fecha;
        [$y, $m, $d] = array_map('intval', explode('-', $partes) + [null, null, null]);
        if ($y < 1900 || $y > 2100) return false;
        if ($m < 1 || $m > 12) return false;
        if ($d < 1 || $d > 31) return false;
        return checkdate($m, $d, $y);
    }

    private function parseString($value, int $maxLength): ?string
    {
        if ($value === null || $value === '') return null;
        $s = trim((string)$value);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return $s === '' ? null : mb_substr($s, 0, $maxLength, 'UTF-8');
    }

    private function parseDateOnly($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(\DateTime::createFromInterface($value))->format('Y-m-d');
            }
            if (is_numeric($value)) {
                $n = (float)$value;
                if ($n > 0 && $n < 100000) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n);
                    return Carbon::instance($dt)->format('Y-m-d');
                }
            }
            $parsed = $this->parseDate($value);
            return $parsed ? substr($parsed, 0, 10) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getValue(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeKey($alias);
            if (array_key_exists($key, $row)) {
                $val = $this->nullIfPlaceholder($row[$key]);
                if ($val !== '' && $val !== null) return $val;
            }
        }
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeKey($alias);
            $aliasLength = strlen($normalizedAlias);
            if ($aliasLength >= 3 && $aliasLength <= 4) {
                foreach ($row as $rowKey => $rowValue) {
                    $rowKeyStr = (string)$rowKey;
                    if ($rowKeyStr === $normalizedAlias ||
                        str_starts_with($rowKeyStr, $normalizedAlias) ||
                        str_ends_with($rowKeyStr, $normalizedAlias)) {
                        $val = $this->nullIfPlaceholder($rowValue);
                        if ($val !== '' && $val !== null) return $val;
                    }
                }
            }
        }
        return null;
    }

    private function findFirstColumnContaining(array $row, array $mustContain, array $mustNotContain = [])
    {
        foreach ($row as $key => $value) {
            $k = mb_strtolower((string)$key, 'UTF-8');
            $ok = true;
            foreach ($mustContain as $w) {
                if (!str_contains($k, mb_strtolower($w, 'UTF-8'))) { $ok = false; break; }
            }
            if (!$ok) continue;
            foreach ($mustNotContain as $w) {
                if (str_contains($k, mb_strtolower($w, 'UTF-8'))) { $ok = false; break; }
            }
            if ($ok) return ($value !== '' && $value !== null) ? $value : null;
        }
        return null;
    }

    private function normalizeRowKeys(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$this->normalizeKey((string)$k)] = $v;
        }
        return $out;
    }

    private function normalizeKey(string $key): string
    {
        $s = mb_strtolower(trim($key), 'UTF-8');
        $s = $this->removeAccents($s);
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s) ?? $s;
        $s = preg_replace('/_+/', '_', $s) ?? $s;
        return trim($s, '_');
    }

    private function removeAccents(string $value): string
    {
        $t = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'];
        return strtr($value, $t);
    }

    private function nullIfPlaceholder($v)
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return ($s === '' || $s === '?' || $s === '-') ? null : $v;
    }

    private function loadSchemaStringLimits(): void
    {
        if (!empty(self::$schemaStringLimits)) return;
        $rows = DB::select("
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'ReqProgramaTejido'
        ");
        foreach ($rows as $r) {
            $type = strtolower($r->DATA_TYPE);
            $len  = $r->CHARACTER_MAXIMUM_LENGTH;
            if (($type === 'nvarchar' || $type === 'varchar') && $len !== null && $len > 0) {
                self::$schemaStringLimits[$r->COLUMN_NAME] = (int)$len;
            }
        }
    }

    private function enforceSchemaStringLengths(array &$data): void
    {
        $this->loadSchemaStringLimits();
        foreach ($data as $field => $val) {
            if ($val === null || $val === '') continue;
            if (!is_string($val)) continue;
            $max = self::$schemaStringLimits[$field] ?? null;
            if ($max === null) continue;
            $s = preg_replace('/\s+/', ' ', trim($val)) ?? $val;
            $s = $this->sanitizeText($s);
            if (mb_strlen($s, 'UTF-8') > $max) {
                $s = mb_substr($s, 0, $max, 'UTF-8');
            }
            $data[$field] = $s;
        }
    }

    private function sanitizeText(string $value): string
    {
        $s = str_replace(["\xC2\xA0"], ' ', $value);
        $s = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x9C", "\xE2\x80\x9D"], ["'", "'", "\"", "\""], $s);
        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? $s;
        $s = $this->removeAccents($s);
        return $s;
    }

    private function enrichFromModelosCodificados(array &$data): void
    {
        $salonId = $data['SalonTejidoId'] ?? null;
        $tamanoClave = $data['TamanoClave'] ?? null;
        if ($salonId === null || $salonId === '' || $tamanoClave === null || $tamanoClave === '') {
            return;
        }
        $cacheKey = $salonId . '|' . $tamanoClave;
        if (!isset(self::$modelosCodificadosCache[$cacheKey])) {
            try {
                $modelo = ReqModelosCodificados::where('SalonTejidoId', $salonId)
                    ->where('TamanoClave', $tamanoClave)
                    ->first();
                self::$modelosCodificadosCache[$cacheKey] = $modelo;
            } catch (\Throwable $e) {
                self::$modelosCodificadosCache[$cacheKey] = null;
            }
        }
        $modelo = self::$modelosCodificadosCache[$cacheKey];
        if ($modelo) {
            $data['CalibreRizo'] = $modelo->CalibreRizo;
            $data['CalibreRizo2'] = $modelo->CalibreRizo2;
            $data['CalibrePie'] = $modelo->CalibrePie;
            $data['CalibreTrama'] = $modelo->CalibreTrama;
            $data['CodColorTrama'] = $modelo->CodColorTrama;
            $data['ColorTrama'] = $modelo->ColorTrama;
            $data['CalibreComb1'] = $modelo->CalibreComb1;
            $data['CalibreComb2'] = $modelo->CalibreComb2;
            $data['CalibreComb3'] = $modelo->CalibreComb3;
            $data['CalibreComb4'] = $modelo->CalibreComb4;
            $data['CalibreComb5'] = $modelo->CalibreComb5;
            $data['CodColorComb1'] = $modelo->CodColorC1;
            $data['NombreCC1'] = $modelo->NomColorC1;
            $data['CodColorComb2'] = $modelo->CodColorC2;
            $data['NombreCC2'] = $modelo->NomColorC2;
            $data['CodColorComb3'] = $modelo->CodColorC3;
            $data['NombreCC3'] = $modelo->NomColorC3;
            $data['CodColorComb4'] = $modelo->CodColorC4;
            $data['NombreCC4'] = $modelo->NomColorC4;
            $data['CodColorComb5'] = $modelo->CodColorC5;
            $data['NombreCC5'] = $modelo->NomColorC5;
            $data['ItemId'] = $modelo->ItemId;
            $data['InventSizeId'] = $modelo->InventSizeId;
            $data['Rasurado'] = $modelo->Rasurado;
        }
    }

    private static ?array $validColumns = null;

    private function getValidColumns(): array
    {
        if (self::$validColumns === null) {
            self::$validColumns = (new ReqProgramaTejido())->getFillable();
        }
        return self::$validColumns;
    }

    /** Columnas que en BD son INT: Excel puede traer decimales, se redondean a entero */
    private const INTEGER_COLUMNS = [
        'NoTiras', 'Peine', 'Luchaje', 'PesoCrudo',
        'PasadasTrama', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',
        'SaldoMarbete', 'EnProceso',
    ];

    private function normalizeDataForBatchInsert(array $data): array
    {
        $validColumns = $this->getValidColumns();
        $normalized = [];
        foreach ($validColumns as $field) {
            $value = $data[$field] ?? null;
            if (in_array($field, self::INTEGER_COLUMNS, true) && $value !== null && $value !== '') {
                $value = is_numeric($value) ? (int) round((float) $value) : $value;
            }
            $normalized[$field] = $value;
        }
        if (!isset($normalized['CreatedAt'])) {
            $normalized['CreatedAt'] = now();
        }
        if (!isset($normalized['UpdatedAt'])) {
            $normalized['UpdatedAt'] = now();
        }
        return $normalized;
    }

    private function shouldSkipEmptyRow(array $data): bool
    {
        $relevant = [
            'NombreProducto','NombreProyecto','TamanoClave','NoProduccion','FlogsId',
            'SalonTejidoId','NoTelarId','AplicacionId'
        ];
        foreach ($relevant as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if ($v !== null && $v !== '') {
                    return false;
                }
            }
        }
        return true;
    }
}
