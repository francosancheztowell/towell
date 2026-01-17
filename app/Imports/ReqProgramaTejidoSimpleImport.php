<?php
declare(strict_types=1);

namespace App\Imports;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ReqProgramaTejidoSimpleImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    /** Contadores */
    private int $rowCounter    = 0;
	private int $processedRows = 0;
    private int $skippedRows   = 0;

    /** Cache de límites de columnas string según esquema */
    private static array $schemaStringLimits = [];

    /** Cache en memoria para ReqModelosCodificados (SalonTejidoId + TamanoClave) */
    private static array $modelosCodificadosCache = [];

    /** Cache en memoria para TwFlogsCustomer (FlogsId) */
    private static array $flogsCache = [];

    /** Cache temporal de posiciones asignadas por telar durante el batch actual (para evitar duplicados) */
    private static array $posicionesCachePorTelar = [];
    public function model(array $rawRow)
	{
		try {
            $row = $this->normalizeRowKeys($rawRow);
	        $this->rowCounter++;

            // Logging removido para rendimiento

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
                // FibraComb1 ya no se lee del Excel; se rellenará desde NombreCC1
                'FibraComb1'      => null,

                // Campos ahora provienen de modelos codificados
                'CodColorComb1'   => null,
                'NombreCC1'       => null,

                'PasadasComb1'    => $this->parseFloat($this->getValue($row, ['Pasadas C1','Pasadas Comb1','pasadas c1','pasadas_comb1'])),

                // C2
                'CalibreComb2'    => null,
                'CalibreComb22'   => $this->parseFloat($this->getValue($row, ['Calibre C2'])),
                // FibraComb2 ya no se lee del Excel; se rellenará desde NombreCC2
                'FibraComb2'      => null,
                'CodColorComb2'   => null,
                'NombreCC2'       => null,
                'PasadasComb2'    => $this->parseFloat($this->getValue($row, ['Pasadas C2','Pasadas Comb2','pasadas c2','pasadas_comb2'])),

                // C3
                'CalibreComb3'    => null,
                'CalibreComb32'   => $this->parseFloat($this->getValue($row, ['Calibre C3'])),
                // FibraComb3 ya no se lee del Excel; se rellenará desde NombreCC3
                'FibraComb3'      => null,
                'CodColorComb3'   => null,
                'NombreCC3'       => null,
                'PasadasComb3'    => $this->parseFloat($this->getValue($row, ['Pasadas C3','Pasadas Comb3','pasadas c3','pasadas_comb3'])),

                // C4
                'CalibreComb4'    => null,
                'CalibreComb42'   => $this->parseFloat($this->getValue($row, ['Calibre C4'])),
                // FibraComb4 ya no se lee del Excel; se rellenará desde NombreCC4
                'FibraComb4'      => null,
                'CodColorComb4'   => null,
                'NombreCC4'       => null,
                'PasadasComb4'    => $this->parseFloat($this->getValue($row, ['Pasadas C4','Pasadas Comb4','pasadas c4','pasadas_comb4'])),

                // C5
                'CalibreComb5'    => null,
                'CalibreComb52'   => $this->parseFloat($this->getValue($row, ['Calibre C5'])),
                // FibraComb5 ya no se lee del Excel; se rellenará desde NombreCC5
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
                // Campo verde (*2): viene del Excel
                'CalibreRizo2'    => null,
                // Campo blanco (base): se rellena desde ReqModelosCodificados
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
                // Campo verde (*2): viene del Excel
                'CalibrePie2'     => $this->parseFloat($this->getValue($row, ['Calibre Pie'])),
                // Campo blanco (base): se rellena desde ReqModelosCodificados
                'CalibrePie'      => null,
                'FlogsId'         => $this->parseString($this->getValue($row, ['Flogs','idflog','id_flog','FlogsId','flogs_id','Flogs']), 40),
                'NombreProyecto'  => $this->parseString($this->getValue($row, ['Descrip.','Descrip','Descripción','Descripcion','nombre_proyecto']), 120),
                // CustName y CategoriaCalidad se llenan solo desde TwFlogsCustomer (no desde Excel)
                'CustName'        => null,
                'AplicacionId'    => $this->parseString($this->getValue($row, ['Aplic.','Aplic','aplicacion_id']), 20),
                'Observaciones'   => $this->parseString($this->getValue($row, ['Obs','Observaciones','observaciones']), 200),
                'TipoPedido'      => $this->parseString($this->getValue($row, ['Tipo Ped.','Tipo Ped','tipo_pedido']), 40),
                'NoTiras'         => $this->parseFloat($this->getValue($row, ['Tiras','No Tiras','no_tiras'])),

                // Pedido / Producción
                // TotalPedido se lee del Excel (lo que antes era SaldoPedido)
                'TotalPedido'     => $this->parseFloat($this->getValue($row, ['Saldos','Saldo Pedido','saldo_pedido','saldos','Total Pedido','total_pedido'])),
                'Produccion'      => $this->parseFloat($this->getValue($row, ['Producción','Produccion','produccion','Producción'])),
                'SaldoPedido'     => null,
                'SaldoMarbete'    => $this->parseInteger($this->getValue($row, ['Saldo Marbete','saldo_marbete','Marbete','marbete'])),
                'ProgramarProd'   => $this->parseDateOnly($this->getValue($row, ['Day Sheduling','Day Scheduling','Día Scheduling','Dia Scheduling','programar_prod'])),
                'NoProduccion'    => $this->parseString($this->getValue($row, ['Orden Prod.','Orden Prod','no_produccion']), 30),
                // Campo OrdCompartida: se toma del Excel usando la columna "Dividir"
                'OrdCompartida'   => $this->parseString($this->getValue($row, ['Dividir','dividir','OrdCompartida','ord_compartida']), 30),
                'Programado'      => $this->parseDateOnly($this->getValue($row, ['INN','Inn','programado'])),
                'OrdCompartidaLider' => $this->parseBoolean($this->getValue($row, ['Lider'])),
                // Calc4..6 en BD son FLOAT
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

            // Si viene FlogsId/IdFlog, obtener CustName y CategoriaCalidad desde TwFlogsCustomer (otra conexión)
            if (!empty($data['FlogsId'])) {
                $flogsId = (string)$data['FlogsId'];
                // Usar caché en memoria para evitar consultas repetidas
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
                return null;
            }



            // Buscar en ReqModelosCodificados por SalonTejidoId + TamanoClave y actualizar campos verdes
            $this->enrichFromModelosCodificados($data);

            // Si NombreCC1 no vino de codificados, intentar con COLOR C1 del Excel
            if ($data['NombreCC1'] === null && $colorC1Excel !== null) {
                $data['NombreCC1'] = $colorC1Excel;
            }

            // Si NombreCC2 no vino de codificados, intentar con COLOR C2 del Excel
            if ($data['NombreCC2'] === null && $colorC2Excel !== null) {
                $data['NombreCC2'] = $colorC2Excel;
            }

            // Si NombreCC3 no vino de codificados, intentar con COLOR C3 del Excel
            if ($data['NombreCC3'] === null && $colorC3Excel !== null) {
                $data['NombreCC3'] = $colorC3Excel;
            }

            // Si NombreCC4 no vino de codificados, intentar con COLOR C4 del Excel
            if ($data['NombreCC4'] === null && $colorC4Excel !== null) {
                $data['NombreCC4'] = $colorC4Excel;
            }

            // Si NombreCC5 no vino de codificados, intentar con COLOR C5 del Excel
            if ($data['NombreCC5'] === null && $colorC5Excel !== null) {
                $data['NombreCC5'] = $colorC5Excel;
            }

            // FibraComb1 debe reflejar NombreCC1 (no se toma del Excel directamente)
            $data['FibraComb1'] = $this->parseString($data['NombreCC1'] ?? null, 30);
            // FibraComb2 debe reflejar NombreCC2 (no se toma del Excel directamente)
            $data['FibraComb2'] = $this->parseString($data['NombreCC2'] ?? null, 30);
            // FibraComb3 debe reflejar NombreCC3 (no se toma del Excel directamente)
            $data['FibraComb3'] = $this->parseString($data['NombreCC3'] ?? null, 30);
            // FibraComb4 debe reflejar NombreCC4 (no se toma del Excel directamente)
            $data['FibraComb4'] = $this->parseString($data['NombreCC4'] ?? null, 30);
            // FibraComb5 debe reflejar NombreCC5 (no se toma del Excel directamente)
            $data['FibraComb5'] = $this->parseString($data['NombreCC5'] ?? null, 30);

            // Recorta contra el esquema real (INFORMATION_SCHEMA)
            $this->enforceSchemaStringLengths($data);

            // Asignar posición consecutiva para este telar si no viene en el Excel
            if (!isset($data['Posicion']) || $data['Posicion'] === null || $data['Posicion'] === '') {
                $salonTejidoId = $data['SalonTejidoId'] ?? null;
                $noTelarId = $data['NoTelarId'] ?? null;
                if ($salonTejidoId && $noTelarId) {
                    $cacheKey = $salonTejidoId . '|' . $noTelarId;
                    
                    // Obtener siguiente posición disponible desde BD
                    $siguientePosicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salonTejidoId, $noTelarId);
                    
                    // Verificar si ya asignamos posiciones para este telar en este batch
                    if (!isset(self::$posicionesCachePorTelar[$cacheKey])) {
                        self::$posicionesCachePorTelar[$cacheKey] = [];
                    }
                    
                    // Ajustar posición si ya fue asignada en este batch
                    while (in_array($siguientePosicion, self::$posicionesCachePorTelar[$cacheKey], true)) {
                        $siguientePosicion++;
                    }
                    
                    // Guardar en cache y asignar
                    self::$posicionesCachePorTelar[$cacheKey][] = $siguientePosicion;
                    $data['Posicion'] = $siguientePosicion;
                }
            }

            // Normalizar datos para batch insert: todos los registros deben tener las mismas columnas
            $data = $this->normalizeDataForBatchInsert($data);

            $modelo = new ReqProgramaTejido($data);

            // Logging removido para rendimiento

            $this->processedRows++;
            return $modelo;

        } catch (\Throwable $e) {
            // Loguear causa de error de fila para diagnóstico
            Log::error('Import PT: error en fila', [
                'row_num' => $this->rowCounter,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->skippedRows++;
            return null;
        }
    }

    public function batchSize(): int { return 15; } // SQL Server limita a 2100 parámetros. Con ~100 campos, máximo 15 registros por batch
    public function chunkSize(): int { return 500; } // Leer Excel en chunks de 500 filas

	public function getStats(): array
	{
		return [
			'processed_rows' => $this->processedRows,
            'skipped_rows'   => $this->skippedRows,
            'total_rows'     => $this->rowCounter,
        ];
    }

    /* ====================== Helpers de parseo ====================== */

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

        // fracciones tipo "5/3" o "1/2"
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

    /**
     * DATETIME robusto (Y-m-d H:i:s)
     * - DateTime/Carbon
     * - Serial Excel
     * - Unix timestamp
     * - Strings comunes (incluye "08-sep", "29-sep 13:45", "a. m./p. m.")
 */
    private function parseDate($value): ?string
{
    if ($value === null || $value === '') return null;

    try {
        // 1) DateTime/Carbon
        if ($value instanceof DateTimeInterface) {
                return Carbon::instance(\DateTime::createFromInterface($value))->format('Y-m-d H:i:s');
        }

            // 2) Serial Excel o Unix
        if (is_numeric($value)) {
            $n = (float)$value;
            if ($n > 0 && $n < 100000) {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n);
                return Carbon::instance($dt)->format('Y-m-d H:i:s');
            }
                if ($n >= 631152000 && $n <= 4102444800) { // 1990..2100
                return Carbon::createFromTimestamp((int)$n)->format('Y-m-d H:i:s');
            }
        }

            // 3) Normalización
        $s = (string)$value;
        $s = preg_replace('/[\x{00A0}\x{2000}-\x{200B}]/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        $s = preg_replace('/\b(a\s*\.?\s*m\.?)\b/iu', 'AM', $s);
        $s = preg_replace('/\b(p\s*\.?\s*m\.?)\b/iu', 'PM', $s);
        $s = preg_replace('/\b(a\s*\.?\s*m)\b/iu', 'AM', $s);
        $s = preg_replace('/\b(p\s*\.?\s*m)\b/iu', 'PM', $s);

            // 4) Formatos preferidos
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

            // 5) DD-MMM (sin año)
            if ($out = $this->parseDiaMesNombre($s)) return $out;

            // 6) DD-MMM-YYYY (con año)
            if ($out = $this->parseDiaMesNombreConAno($s)) return $out;

            // 7) Último recurso
            return Carbon::parse($s)->format('Y-m-d H:i:s');

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retorna el primer valor no vacío de un arreglo por lista de claves (case-insensitive)
     */


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

    /** Solo fecha (Y-m-d) */
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

    /* ====================== Encabezados ====================== */

    private function getValue(array $row, array $aliases)
    {
        // Primera pasada: búsqueda exacta normalizada
        foreach ($aliases as $alias) {
            $key = $this->normalizeKey($alias);
            if (array_key_exists($key, $row)) {
                $val = $this->nullIfPlaceholder($row[$key]);
                if ($val !== '' && $val !== null) return $val;
            }
        }

        // Segunda pasada: búsqueda flexible para casos con espacios/caracteres especiales
        // Solo para aliases cortos (3-4 caracteres) que podrían tener problemas de normalización
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeKey($alias);
            $aliasLength = strlen($normalizedAlias);

            // Solo buscar parcialmente si el alias es corto (3-4 chars) para evitar falsos positivos
            if ($aliasLength >= 3 && $aliasLength <= 4) {
                foreach ($row as $rowKey => $rowValue) {
                    // Asegurar que rowKey sea string antes de usar str_starts_with/str_ends_with
                    $rowKeyStr = (string)$rowKey;

                    // Coincidencia exacta o que empiece/termine con el alias normalizado
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
            // Asegurar que la clave sea string antes de normalizar
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

    /** Normaliza placeholders como ?, -, cadena vacía a null */
    private function nullIfPlaceholder($v)
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return ($s === '' || $s === '?' || $s === '-') ? null : $v;
    }

    /**
     * Recorta y normaliza longitudes de strings según límites de la BD
     */
    /** Carga una sola vez los límites NVARCHAR/VARCHAR desde INFORMATION_SCHEMA */
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

    /** Recorta TODOS los strings según límites reales del esquema */
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
        // Normaliza comillas/tildes raras y NBSP
        $s = str_replace(["\xC2\xA0"], ' ', $value); // NBSP
        $s = str_replace(["’","‘","“","”"], ["'","'","\"","\""], $s);
        // Elimina caracteres de control no imprimibles
        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? $s;
        // Opcional: quitar acentos para evitar variantes raras de codificación
        $s = $this->removeAccents($s);
        return $s;
    }

    /**
     * Busca en ReqModelosCodificados por SalonTejidoId + TamanoClave
     * y actualiza los campos BLANCOS (base):
     * - CalibreRizo, CalibrePie, CalibreTrama
     * - CalibreComb1, CalibreComb2, CalibreComb3, CalibreComb4, CalibreComb5
     *
     * NOTA: Los campos verdes (*2) vienen del Excel y NO se sobrescriben
     * OPTIMIZACIÓN: Usa caché en memoria para evitar consultas repetidas
     */
    private function enrichFromModelosCodificados(array &$data): void
    {
        $salonId = $data['SalonTejidoId'] ?? null;
        $tamanoClave = $data['TamanoClave'] ?? null;

        // Si no tenemos los campos necesarios para la búsqueda, salir
        if ($salonId === null || $salonId === '' || $tamanoClave === null || $tamanoClave === '') {
            return;
        }

        // Crear clave de caché
        $cacheKey = $salonId . '|' . $tamanoClave;

        // Verificar caché primero
        if (!isset(self::$modelosCodificadosCache[$cacheKey])) {
            try {
                // Buscar en ReqModelosCodificados por SalonTejidoId + TamanoClave
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
            // Actualizar campos BLANCOS (base) con valores de modelos codificados
            // Los campos verdes (*2) ya vienen del Excel y NO se sobrescriben
            $data['CalibreRizo'] = $modelo->CalibreRizo;
            $data['CalibreRizo2'] = $modelo->CalibreRizo2;
            $data['CalibrePie'] = $modelo->CalibrePie;
            $data['CalibreTrama'] = $modelo->CalibreTrama;
            $data['CodColorTrama'] = $modelo->CodColorTrama;
            $data['ColorTrama'] = $modelo->ColorTrama;

            // Actualizar campos Comb base (1-5) desde modelos codificados
            $data['CalibreComb1'] = $modelo->CalibreComb1;
            $data['CalibreComb2'] = $modelo->CalibreComb2;
            $data['CalibreComb3'] = $modelo->CalibreComb3;
            $data['CalibreComb4'] = $modelo->CalibreComb4;
            $data['CalibreComb5'] = $modelo->CalibreComb5;

            // Colores y nombres de combinaciones
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

            // Datos generales adicionales
            $data['ItemId'] = $modelo->ItemId;
            $data['InventSizeId'] = $modelo->InventSizeId;
            $data['Rasurado'] = $modelo->Rasurado;
        }
    }

    /**
     * Cache para las columnas válidas (fillable del modelo)
     */
    private static ?array $validColumns = null;

    /**
     * Obtiene las columnas válidas del modelo (fillable ya contiene solo campos que existen en BD)
     */
    private function getValidColumns(): array
    {
        if (self::$validColumns === null) {
            self::$validColumns = (new ReqProgramaTejido())->getFillable();
        }
        return self::$validColumns;
    }

    /**
     * Normaliza el array de datos para batch insert
     * Asegura que todos los registros tengan exactamente las mismas columnas
     * Esto es necesario porque SQL Server requiere que todos los registros en un batch insert tengan las mismas columnas
     */
    private function normalizeDataForBatchInsert(array $data): array
    {
        // Obtener solo las columnas válidas (excluyendo campos que no existen en la BD)
        $validColumns = $this->getValidColumns();

        // Crear un array normalizado con solo los campos válidos
        $normalized = [];
        foreach ($validColumns as $field) {
            // Incluir el campo incluso si es null, para que todos los registros tengan las mismas columnas
            $normalized[$field] = $data[$field] ?? null;
        }

        // Agregar CreatedAt y UpdatedAt si no están presentes
        if (!isset($normalized['CreatedAt'])) {
            $normalized['CreatedAt'] = now();
        }
        if (!isset($normalized['UpdatedAt'])) {
            $normalized['UpdatedAt'] = now();
        }

        return $normalized;
    }

    /** Define si una fila debe omitirse por estar vacía o sin datos útiles */
    private function shouldSkipEmptyRow(array $data): bool
    {
        // Campos que consideramos como mínimo relevantes para no omitir
        $relevant = [
            'NombreProducto','NombreProyecto','TamanoClave','NoProduccion','FlogsId',
            'SalonTejidoId','NoTelarId','AplicacionId'
        ];

        $missing = [];
        foreach ($relevant as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if ($v !== null && $v !== '') {
                    return false;
                }
            }
            $missing[] = $k;
        }



        // Si no hay ninguno relevante, omite la fila
        return true;
    }
}
