<?php
declare(strict_types=1);

namespace App\Imports;

use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
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

    /* ====================== Maatwebsite API ====================== */

    public function model(array $rawRow)
	{
		try {
            $row = $this->normalizeRowKeys($rawRow);
	        $this->rowCounter++;

            // Detectar si el campo Ultimo contiene "ULTIMO" y establecerlo a '1'
            $ultimoValue = $this->getValue($row, ['Último','Ultimo','ultimo']);
            $isUltimo = (strtoupper(trim((string)($ultimoValue ?? ''))) === 'ULTIMO');

            $data = [
                /* ===== PRINCIPALES ===== */
                'SalonTejidoId'   => $this->parseString($this->getValue($row, ['Salón','Salon','Salon Tejido Id','salon_tejido_id']), 20),
                'NoTelarId'       => $this->parseString($this->getValue($row, ['Telar','No Telar','no_telar_id']), 20),
                'Ultimo'          => $isUltimo ? '1' : $this->parseString($ultimoValue, 4),
                'CambioHilo'      => $this->parseString($this->getValue($row, ['Cambios Hilo','Cambio Hilo','CAMBIOS HILO','CAMBIO HILO','cambio_hilo']), 4),
                'Maquina'         => $this->parseString($this->getValue($row, ['Maq','Máq','Maquina','máquina','maquina']), 30),

                // Producto: ampliar alias y permitir fallback luego si sigue nulo
                'NombreProducto'  => $this->parseString($this->getValue($row, [
                    'NombreProducto','Nombre Producto','nombre_producto','producto',
                    'Nombre del Producto','Producto Final','Producto/Nombre','prod'
                ]), 200),
                'TamanoClave'     => $this->parseString($this->getValue($row, ['Clave Mod.','Clave mod.','Clave Mod','Tamaño Clave','Tamano Clave','tamano_clave']), 40),
                'MedidaPlano'     => $this->parseInteger($this->getValue($row, ['Plano','Medida Plano','medida_plano'])),

                /* ===== PIE ===== */
                'CuentaPie'       => $this->parseString($this->getValue($row, ['Cuenta Pie','cuenta_pie']), 20),
                'CodColorCtaPie'  => $this->parseString($this->getValue($row, ['Código Color Pie','Codigo Color Pie','Cod Color Cta Pie','cod color cta pie','cod_color_cta_pie']), 20),
                'NombreCPie'      => $this->parseString($this->getValue($row, ['Color Pie','Nombre C Pie','nombre c pie','nombre_cpie']), 120),
                'FibraPie'        => $this->parseString($this->getValue($row, ['Fibra Pie','fibra_pie','Hilo Pie','hilo_pie']), 30),
                'AnchoToalla'     => $this->parseInteger($this->getValue($row, ['Ancho por Toalla','Ancho Toalla','ancho_toalla'])),

                /* ===== TRAMA ===== */
                'CodColorTrama'   => $this->parseString($this->getValue($row, ['Código Color Tra','Codigo Color Tra','Cod Color Trama','cod_color_trama']), 20),
                'ColorTrama'      => $this->parseString($this->getValue($row, ['Color Tra','Color Trama','color_trama']), 120),
                // Campo verde (*2): viene del Excel
                'CalibreTrama2'   => $this->parseFloat($this->getValue($row, [
                                        'Calibre Tra','Calibre Trama','CalibreTrama','calibre_trama',
                                        'Calibre Tra 2','Calibre Trama 2','Calibre Trama/2','CalibreTrama2','calibre_trama2','calibre_trama_2','calibre trama/2'
                                    ])),
                // Campo blanco (base): se rellena desde ReqModelosCodificados
                'CalibreTrama'    => null,
                'FibraTrama'      => $this->parseString($this->getValue($row, ['Fibra Trama','fibra_trama']), 30),
                'DobladilloId'    => $this->parseString($this->getValue($row, ['Dobladillo','Dob']), 20),
                'PasadasTrama'    => $this->parseInteger($this->getValue($row, ['Pasadas Tra','Pasadas Trama','pasadas_trama'])),

                /* ===== COMBINACIONES 1..5 ===== */
                // C1
                'CalibreComb1'    => null,
                'CalibreComb12'   => $this->parseFloat($this->getValue($row, ['Calibre C1'])),
                'FibraComb1'      => $this->parseString($this->getValue($row, ['Fibra C1','Fibra Comb1','fibra comb1','fibra_comb1']), 30),
                'CodColorComb1'   => $this->parseString($this->getValue($row, ['Código Color C1','Codigo Color C1','Cod Color C1','cod color c1','cod_color_c1']), 20),
                'NombreCC1'       => $this->parseString($this->getValue($row, ['Color C1','Nombre C1','nombre c1','nombre_c1']), 120),
                'PasadasComb1'    => $this->parseInteger($this->getValue($row, ['Pasadas C1','Pasadas Comb1','pasadas c1','pasadas_comb1'])),

                // C2
                'CalibreComb2'    => null,
                'CalibreComb22'   => $this->parseFloat($this->getValue($row, ['Calibre C2'])),
                'FibraComb2'      => $this->parseString($this->getValue($row, ['Fibra C2','Fibra Comb2','fibra comb2','fibra_comb2']), 30),
                'CodColorComb2'   => $this->parseString($this->getValue($row, ['Código Color C2','Codigo Color C2','Cod Color C2','cod color c2','cod_color_c2']), 20),
                'NombreCC2'       => $this->parseString($this->getValue($row, ['Color C2','Nombre C2','nombre c2','nombre_c2']), 120),
                'PasadasComb2'    => $this->parseInteger($this->getValue($row, ['Pasadas C2','Pasadas Comb2','pasadas c2','pasadas_comb2'])),

                // C3
                'CalibreComb3'    => null,
                'CalibreComb32'   => $this->parseFloat($this->getValue($row, ['Calibre C3'])),
                'FibraComb3'      => $this->parseString($this->getValue($row, ['Fibra C3','Fibra Comb3','fibra comb3','fibra_comb3']), 30),
                'CodColorComb3'   => $this->parseString($this->getValue($row, ['Código Color C3','Codigo Color C3','Cod Color C3','cod color c3','cod_color_c3']), 20),
                'NombreCC3'       => $this->parseString($this->getValue($row, ['Color C3','Nombre C3','nombre c3','nombre_c3']), 120),
                'PasadasComb3'    => $this->parseInteger($this->getValue($row, ['Pasadas C3','Pasadas Comb3','pasadas c3','pasadas_comb3'])),

                // C4
                'CalibreComb4'    => null,
                'CalibreComb42'   => $this->parseFloat($this->getValue($row, ['Calibre C4'])),
                'FibraComb4'      => $this->parseString($this->getValue($row, ['Fibra C4','Fibra Comb4','fibra comb4','fibra_comb4']), 30),
                'CodColorComb4'   => $this->parseString($this->getValue($row, ['Código Color C4','Codigo Color C4','Cod Color C4','cod color c4','cod_color_c4']), 20),
                'NombreCC4'       => $this->parseString($this->getValue($row, ['Color C4','Nombre C4','nombre c4','nombre_c4']), 120),
                'PasadasComb4'    => $this->parseInteger($this->getValue($row, ['Pasadas C4','Pasadas Comb4','pasadas c4','pasadas_comb4'])),

                // C5
                'CalibreComb5'    => null,
                'CalibreComb52'   => $this->parseFloat($this->getValue($row, ['Calibre C5'])),
                'FibraComb5'      => $this->parseString($this->getValue($row, ['Fibra C5','Fibra Comb5','fibra comb5','fibra_comb5']), 30),
                'CodColorComb5'   => $this->parseString($this->getValue($row, ['Código Color C5','Codigo Color C5','Cod Color C5','cod color c5','cod_color_c5']), 20),
                'NombreCC5'       => $this->parseString($this->getValue($row, ['Color C5','Nombre C5','nombre c5','nombre_c5']), 120),
                'PasadasComb5'    => $this->parseInteger($this->getValue($row, ['Pasadas C5','Pasadas Comb5','pasadas c5','pasadas_comb5'])),

                /* ===== NÚMEROS ===== */
                'Peine'           => $this->parseInteger($this->getValue($row, ['Pei.','Pei','Peine','peine'])),
                'Luchaje'         => $this->parseInteger($this->getValue($row, ['Luc','Luchaje','luchaje','LUC','LUCHE','Luch','luc'])),
                'PesoCrudo'       => $this->parseInteger($this->getValue($row, ['Pcr','Peso Crudo','peso crudo','peso_crudo'])),
                'LargoCrudo'      => $this->parseInteger($this->getValue($row, ['Lcr','Largo Crudo','largo_crudo'])),
                'PesoGRM2'        => $this->parseInteger($this->getValue($row, [
                                        'Peso (gr/m²)','Peso GRM2','peso grm2','peso_gr_m_2'
                ])),
                'DiasEficiencia'  => $this->parseFloat($this->getValue($row, ['Días Ef.','Dias Ef.','Días Eficiencia','Dias Eficiencia','dias_eficiencia'])),

                /* ===== TÍTULOS SIMILARES ===== */
                'ProdKgDia'       => $this->parseFloat($this->findFirstColumnContaining($row, ['prod','kg','dia'], ['2'])),
                'StdDia'          => $this->parseFloat($this->findFirstColumnContaining($row, ['std','dia'], ['toa','hr','100','efectivo'])),
                'ProdKgDia2'      => $this->parseFloat($this->findFirstColumnContaining($row, ['prod','kg','dia','2'])),
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

                'EntregaProduc'   => $this->parseDateOnly($this->getValue($row, ['Fecha Compromiso Prod','Fecha Compromiso Prod.','Entrega Producción','Entrega Produccion','entrega_produc'])),
                'EntregaPT'       => $this->parseDateOnly($this->getValue($row, ['Fecha Compromiso PT','Entrega PT','entrega_pt'])),
                'EntregaCte'      => $this->parseDate($this->getValue($row, ['Entrega','Entrega Cte','entrega_cte'])),
                'PTvsCte'         => $this->parseInteger($this->getValue($row, ['Dif vs Compromiso','PT vs Cte','pt vs cte','pt_vs_cte'])),

                /* ===== ESTADO ===== */
                'EnProceso'       => $this->parseBoolean($this->getValue($row, ['Estado','estado','en_proceso'])),

                /* ===== ADICIONALES ===== */
                'CuentaRizo'      => $this->parseString($this->getValue($row, ['Cuenta','Cuenta Rizo','cuenta_rizo']), 20),
                // Campo verde (*2): viene del Excel
                'CalibreRizo2'    => $this->parseFloat($this->getValue($row, [
                                        'Calibre Rizo','CalibreRizo','calibre_rizo',
                                    ])),
                // Campo blanco (base): se rellena desde ReqModelosCodificados
                'CalibreRizo'     => null,
                'CalendarioId'    => $this->parseString($this->getValue($row, ['Jornada','jornada','calendario_id']), 30),
                'NoExisteBase'    => $this->parseString($this->getValue($row, ['Usar cuando no existe en base','no_existe_base']), 40),
                'ItemId'          => $this->parseString($this->getValue($row, ['Clave AX','item_id']), 40),
                'InventSizeId'    => $this->parseString($this->getValue($row, ['Tamaño AX','Tamano AX','invent_size_id']), 20),
                'Rasurado'        => $this->parseString($this->getValue($row, ['Rasurado','rasurado']), 4),
                'Ancho'           => $this->parseFloat($this->getValue($row, ['Ancho','ancho'])),
                'EficienciaSTD'   => $this->parseFloat($this->getValue($row, ['Ef Std','ef std','ef_std','eficiencia std','eficiencia_std','eficiencia'])),
                'VelocidadSTD'    => $this->parseInteger($this->getValue($row, ['Vel','vel','velocidad','velocidad_std'])),
                'FibraRizo'       => $this->parseString($this->getValue($row, ['Hilo','hilo','Fibra Rizo','fibra rizo','fibra_rizo']), 30),
                // Campo verde (*2): viene del Excel
                'CalibrePie2'     => $this->parseFloat($this->getValue($row, [
                    'Calibre Pie','CalibrePie','calibre_pie',
                    'CalibrePie2','Calibre Pie 2','Calibre Pie/2','calibre_pie2','calibre pie 2'
                ])),
                // Campo blanco (base): se rellena desde ReqModelosCodificados
                'CalibrePie'      => null,
                'FlogsId'         => $this->parseString($this->getValue($row, ['Id Flog','flogs_id']), 40),
                'NombreProyecto'  => $this->parseString($this->getValue($row, ['Descrip.','Descrip','Descripción','Descripcion','nombre_proyecto']), 120),
                'CustName'        => $this->parseString($this->getValue($row, ['Nombre Cliente','cust_name']), 120),
                'AplicacionId'    => $this->parseString($this->getValue($row, ['Aplic.','Aplic','aplicacion_id']), 20),
                'Observaciones'   => $this->parseString($this->getValue($row, ['Obs','Observaciones','observaciones']), 200),
                'TipoPedido'      => $this->parseString($this->getValue($row, ['Tipo Ped.','Tipo Ped','tipo_pedido']), 40),
                'NoTiras'         => $this->parseInteger($this->getValue($row, ['Tiras','No Tiras','no_tiras'])),

                // Pedido / Producción
                'TotalPedido'     => $this->parseFloat($this->getValue($row, ['Total Pedido','Total Ped','total_pedido','total pedido','Total'])),
                'Produccion'      => $this->parseFloat($this->getValue($row, ['Producción','Produccion','produccion','Producción'])),
                'SaldoPedido'     => $this->parseFloat($this->getValue($row, ['Saldos','Saldo Pedido','saldo_pedido','saldos'])),
                'SaldoMarbete'    => $this->parseInteger($this->getValue($row, ['Saldo Marbete','saldo_marbete','Marbete','marbete'])),
                'ProgramarProd'   => $this->parseDateOnly($this->getValue($row, ['Day Sheduling','Day Scheduling','Día Scheduling','Dia Scheduling','programar_prod'])),
                'NoProduccion'    => $this->parseString($this->getValue($row, ['Orden Prod.','Orden Prod','no_produccion']), 30),
                'Programado'      => $this->parseDateOnly($this->getValue($row, ['INN','Inn','programado'])),

                // Calc4..6 en BD son FLOAT
                'Calc4'           => $this->parseFloat($this->getValue($row, ['Calc4','calc4','Calc 4'])),
                'Calc5'           => $this->parseFloat($this->getValue($row, ['Calc5','calc5','Calc 5'])),
                'Calc6'           => $this->parseFloat($this->getValue($row, ['Calc6','calc6','Calc 6'])),
            ];

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

            // Recorta contra el esquema real (INFORMATION_SCHEMA)
            $this->enforceSchemaStringLengths($data);

            $modelo = new ReqProgramaTejido($data);

		$this->processedRows++;
		return $modelo;

        } catch (\Throwable $e) {
            Log::error('Error importando fila', [
                'row_num' => $this->rowCounter,
                'msg'     => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'data_payload' => isset($data) ? $data : null,
            ]);
            $this->skippedRows++;
            return null;
        }
    }

    public function batchSize(): int { return 1; } // DIAGNÓSTICO: 1 para aislar fila problemática
    public function chunkSize(): int { return 200; }

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
            Log::warning('No se pudo parsear fecha', ['valor' => $value, 'msg' => $e->getMessage()]);
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
            Log::warning('No se pudo parsear fecha (solo fecha)', ['valor' => $value, 'msg' => $e->getMessage()]);
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
            $k = mb_strtolower($key, 'UTF-8');

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
     */
    private function enrichFromModelosCodificados(array &$data): void
    {
        $salonId = $data['SalonTejidoId'] ?? null;
        $tamanoClave = $data['TamanoClave'] ?? null;

        // Si no tenemos los campos necesarios para la búsqueda, salir
        if ($salonId === null || $salonId === '' || $tamanoClave === null || $tamanoClave === '') {
            return;
        }

        try {
            // Buscar en ReqModelosCodificados por SalonTejidoId + TamanoClave
            $modelo = ReqModelosCodificados::where('SalonTejidoId', $salonId)
                ->where('TamanoClave', $tamanoClave)
                ->first();

            if ($modelo) {
                // Actualizar campos BLANCOS (base) con valores de modelos codificados
                // Los campos verdes (*2) ya vienen del Excel y NO se sobrescriben
                $data['CalibreRizo'] = $modelo->CalibreRizo;
                $data['CalibrePie'] = $modelo->CalibrePie;
                $data['CalibreTrama'] = $modelo->CalibreTrama;

                // Actualizar campos Comb base (1-5) desde modelos codificados
                $data['CalibreComb1'] = $modelo->CalibreComb1;
                $data['CalibreComb2'] = $modelo->CalibreComb2;
                $data['CalibreComb3'] = $modelo->CalibreComb3;
                $data['CalibreComb4'] = $modelo->CalibreComb4;
                $data['CalibreComb5'] = $modelo->CalibreComb5;
            }
        } catch (\Throwable $e) {
            Log::error('Error al buscar en ReqModelosCodificados', [
                'row_num' => $this->rowCounter,
                'salon_id' => $salonId,
                'tamano_clave' => $tamanoClave,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /** Define si una fila debe omitirse por estar vacía o sin datos útiles */
    private function shouldSkipEmptyRow(array $data): bool
    {
        // Campos que consideramos como mínimo relevantes para no omitir
        $relevant = [
            'NombreProducto','NombreProyecto','TamanoClave','NoProduccion','FlogsId',
            'SalonTejidoId','NoTelarId','AplicacionId'
        ];

        foreach ($relevant as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if ($v !== null && $v !== '') return false;
            }
        }

        // Si no hay ninguno relevante, omite la fila
        return true;
    }
}
