<?php
declare(strict_types=1);

namespace App\Imports;

use App\Models\ReqProgramaTejido;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

class ReqProgramaTejidoSimpleImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    /** Contadores */
	private int $rowCounter = 0;
	private int $processedRows = 0;
	private int $skippedRows = 0;

    /** ======================= API de Maatwebsite ======================= */

    public function model(array $rawRow)
	{
		try {
            // Normaliza claves
            $row = $this->normalizeRowKeys($rawRow);

            // ⚠️ Desactivado: estaba causando que casi todas las filas se saltaran
            // if ($this->looksLikeHeaderRow($row)) {
            //     $this->skippedRows++;
            //     Log::info('Saltada como encabezado embebido', ['row_num' => $this->rowCounter + 1]);
            //     return null;
            // }

	$this->rowCounter++;

	if ($this->rowCounter === 1) {
                Log::info('Primer fila: claves normalizadas', ['count' => count($row), 'keys' => array_keys($row)]);
            }

			$modelo = new ReqProgramaTejido([
                // === ID ÚNICO ===
                'Id' => $this->generateUniqueId(),
                // === PRINCIPALES ===
                'SalonTejidoId'  => $this->parseString($this->getValue($row, ['Salón', 'Salon', 'Salon Tejido Id', 'salon_tejido_id']), 10),
                'NoTelarId'      => $this->parseString($this->getValue($row, ['Telar', 'No Telar', 'no_telar_id']), 10),
                'Ultimo'         => $this->parseString($this->getValue($row, ['Último','Ultimo', 'ultimo']), 2),
                'CambioHilo'     => $this->parseString($this->getValue($row, ['Cambios Hilo', 'Cambio Hilo', 'CAMBIOS HILO', 'CAMBIO HILO', 'cambio_hilo']), 2),
                'Maquina'        => $this->parseString($this->getValue($row, ['Maq', 'Máq', 'Maquina', 'máquina', 'maquina']), 15),
			'NombreProducto' => $this->parseString($this->getValue($row, ['Producto', 'Nombre Producto', 'nombre_producto']), 100),
                'TamanoClave'    => $this->parseString($this->getValue($row, ['Clave Mod.', 'Clave mod.', 'Clave Mod', 'Tamaño Clave', 'Tamano Clave', 'tamano_clave']), 20),
                'MedidaPlano'    => $this->parseInteger($this->getValue($row, ['Plano', 'Medida Plano', 'medida_plano'])),

                // === PIE ===
                'CuentaPie'      => $this->parseString($this->getValue($row, ['Cuenta Pie', 'cuenta_pie']), 10),
			'CodColorCtaPie' => $this->parseString($this->getValue($row, ['Código Color Pie', 'Codigo Color Pie', 'Cod Color Cta Pie', 'cod color cta pie', 'cod_color_cta_pie']), 10),
                'NombreCPie'     => $this->parseString($this->getValue($row, ['Color Pie', 'Nombre C Pie', 'nombre c pie', 'nombre_cpie']), 60),

                'AnchoToalla'    => $this->parseInteger($this->getValue($row, ['Ancho por Toalla', 'Ancho Toalla', 'ancho_toalla'])),

                // === TRAMA ===
                'CodColorTrama'  => $this->parseString($this->getValue($row, ['Código Color Tra', 'Codigo Color Tra', 'Cod Color Trama', 'cod_color_trama']), 10),
                'ColorTrama'     => $this->parseString($this->getValue($row, ['Color Tra', 'Color Trama', 'color_trama']), 60),
                'CalibreTrama'   => $this->parseFloat($this->getValue($row, ['Calibre Tra', 'Calibre Trama', 'calibre_trama'])),
                'FibraTrama'     => $this->parseString($this->getValue($row, ['Fibra Trama', 'fibra_trama']), 15),
                'PasadasTrama'   => $this->parseInteger($this->getValue($row, ['Pasadas Tra', 'Pasadas Trama', 'pasadas_trama'])),

                // === COMBINACIONES 1..5 ===
                'CalibreComb1'   => $this->parseString($this->getValue($row, ['Calibre C1', 'Calibre Comb1', 'calibre comb1', 'calibre_comb1']), 20),
                'CalibreComb12'  => $this->parseString($this->getValue($row, ['Calibre C1/2', 'Calibre Comb1/2', 'calibre comb1/2', 'calibre_comb12']), 20),
                'FibraComb1'     => $this->parseString($this->getValue($row, ['Fibra C1', 'Fibra Comb1', 'fibra comb1', 'fibra_comb1']), 30),
                'CodColorComb1'  => $this->parseString($this->getValue($row, ['Código Color C1', 'Codigo Color C1', 'Cod Color C1', 'cod color c1', 'cod_color_c1']), 10),
                'NombreCC1'      => $this->parseString($this->getValue($row, ['Color C1', 'Nombre C1', 'nombre c1', 'nombre_c1']), 60),
                'PasadasComb1'   => $this->parseString($this->getValue($row, ['Pasadas C1', 'Pasadas Comb1', 'pasadas c1', 'pasadas_comb1']), 20),

                'CalibreComb2'   => $this->parseString($this->getValue($row, ['Calibre C2', 'Calibre Comb2', 'calibre comb2', 'calibre_comb2']), 20),
                'CalibreComb22'  => $this->parseString($this->getValue($row, ['Calibre C2/2', 'Calibre Comb2/2', 'calibre comb2/2', 'calibre_comb22']), 20),
                'FibraComb2'     => $this->parseString($this->getValue($row, ['Fibra C2', 'Fibra Comb2', 'fibra comb2', 'fibra_comb2']), 30),
                'CodColorComb2'  => $this->parseString($this->getValue($row, ['Código Color C2', 'Codigo Color C2', 'Cod Color C2', 'cod color c2', 'cod_color_c2']), 10),
                'NombreCC2'      => $this->parseString($this->getValue($row, ['Color C2', 'Nombre C2', 'nombre c2', 'nombre_c2']), 60),
                'PasadasComb2'   => $this->parseString($this->getValue($row, ['Pasadas C2', 'Pasadas Comb2', 'pasadas c2', 'pasadas_comb2']), 20),

                'CalibreComb3'   => $this->parseString($this->getValue($row, ['Calibre C3', 'Calibre Comb3', 'calibre comb3', 'calibre_comb3']), 20),
                'CalibreComb32'  => $this->parseString($this->getValue($row, ['Calibre C3/2', 'Calibre Comb3/2', 'calibre comb3/2', 'calibre_comb32']), 20),
                'FibraComb3'     => $this->parseString($this->getValue($row, ['Fibra C3', 'Fibra Comb3', 'fibra comb3', 'fibra_comb3']), 30),
                'CodColorComb3'  => $this->parseString($this->getValue($row, ['Código Color C3', 'Codigo Color C3', 'Cod Color C3', 'cod color c3', 'cod_color_c3']), 10),
                'NombreCC3'      => $this->parseString($this->getValue($row, ['Color C3', 'Nombre C3', 'nombre c3', 'nombre_c3']), 60),
                'PasadasComb3'   => $this->parseString($this->getValue($row, ['Pasadas C3', 'Pasadas Comb3', 'pasadas c3', 'pasadas_comb3']), 20),

                'CalibreComb4'   => $this->parseString($this->getValue($row, ['Calibre C4', 'Calibre Comb4', 'calibre comb4', 'calibre_comb4']), 20),
                'CalibreComb42'  => $this->parseString($this->getValue($row, ['Calibre C4/2', 'Calibre Comb4/2', 'calibre comb4/2', 'calibre_comb42']), 20),
                'FibraComb4'     => $this->parseString($this->getValue($row, ['Fibra C4', 'Fibra Comb4', 'fibra comb4', 'fibra_comb4']), 30),
                'CodColorComb4'  => $this->parseString($this->getValue($row, ['Código Color C4', 'Codigo Color C4', 'Cod Color C4', 'cod color c4', 'cod_color_c4']), 10),
                'NombreCC4'      => $this->parseString($this->getValue($row, ['Color C4', 'Nombre C4', 'nombre c4', 'nombre_c4']), 60),
                'PasadasComb4'   => $this->parseString($this->getValue($row, ['Pasadas C4', 'Pasadas Comb4', 'pasadas c4', 'pasadas_comb4']), 20),

                'CalibreComb5'   => $this->parseString($this->getValue($row, ['Calibre C5', 'Calibre Comb5', 'calibre comb5', 'calibre_comb5']), 20),
                'CalibreComb52'  => $this->parseString($this->getValue($row, ['Calibre C5/2', 'Calibre Comb5/2', 'calibre comb5/2', 'calibre_comb52']), 20),
                'FibraComb5'     => $this->parseString($this->getValue($row, ['Fibra C5', 'Fibra Comb5', 'fibra comb5', 'fibra_comb5']), 30),
                'CodColorComb5'  => $this->parseString($this->getValue($row, ['Código Color C5', 'Codigo Color C5', 'Cod Color C5', 'cod color c5', 'cod_color_c5']), 10),
                'NombreCC5'      => $this->parseString($this->getValue($row, ['Color C5', 'Nombre C5', 'nombre c5', 'nombre_c5']), 60),
                'PasadasComb5'   => $this->parseString($this->getValue($row, ['Pasadas C5', 'Pasadas Comb5', 'pasadas c5', 'pasadas_comb5']), 20),

                // === NÚMEROS ===
                'Peine'          => $this->parseInteger($this->getValue($row, ['Pei.', 'Pei', 'Peine', 'peine'])),
                'Luchaje'        => $this->parseInteger($this->getValue($row, ['Lcr', 'Luchaje', 'luchaje'])),
                'PesoCrudo'      => $this->parseInteger($this->getValue($row, ['Pcr', 'Peso Crudo', 'peso crudo', 'peso_crudo'])),
                'PesoGRM2'       => $this->parseInteger($this->getValue($row, [
                    'Peso (gr/m²)', 'Peso GRM2', 'peso grm2', 'peso_grm2', 'Peso    (gr / m²)', 'peso gr m 2', 'peso_gr_m_2'
                ])),
		'DiasEficiencia' => $this->parseFloat($this->getValue($row, ['Días Ef.', 'Dias Ef.', 'Días Eficiencia', 'Dias Eficiencia', 'dias_eficiencia'])),

                // === PROBLEMA TÍTULOS SIMILARES ===
                'ProdKgDia'      => $this->parseFloat($this->findFirstColumnContaining($row, ['prod', 'kg', 'dia'], ['2'])),
                'StdDia'         => $this->parseFloat($this->findFirstColumnContaining($row, ['std', 'dia'], ['toa', 'hr', '100', 'efectivo'])),
                'ProdKgDia2'     => $this->parseFloat($this->findFirstColumnContaining($row, ['prod', 'kg', 'dia', '2'])),
                'StdToaHra'      => $this->parseFloat($this->findFirstColumnContaining($row, ['std', 'toa', 'hr', '100'])),

                'DiasJornada'    => $this->parseFloat($this->getValue($row, ['Días Jornada', 'Dias Jornada Completa', 'Dias jornada completa', 'dias_jornada','Jornada','jornada', 'dias jornada completa'])),
                'HorasProd'      => $this->parseFloat($this->getValue($row, ['Horas', 'Horas Prod', 'horas prod', 'horas_prod'])),
                'StdHrsEfect'    => $this->parseFloat(
                    $this->findFirstColumnContaining($row, ['std', 'hr', 'efectivo']) ??
                    $this->getValue($row, ['Std/Hr Efectivo', 'STD Hrs Efect', 'std_hrs_efect'])
                ),

                // === FECHAS (DATETIME) ===
                'FechaInicio'    => $this->parseDateWithLogging($this->getValue($row, ['Inicio', 'Fecha Inicio', 'fecha inicio', 'fecha_inicio']), 'FechaInicio'),
                'FechaFinal'     => $this->parseDateWithLogging($this->getValue($row, ['Fin', 'Fecha Final', 'fecha final', 'fecha_final']), 'FechaFinal'),

                'EntregaProduc'  => $this->parseDateOnly($this->getValue($row, ['Fecha Compromiso Prod', 'Fecha Compromiso Prod.', 'Entrega Producción', 'Entrega Produccion', 'entrega_produc'])),
                'EntregaPT'      => $this->parseDateOnly($this->getValue($row, ['Fecha Compromiso PT', 'Entrega PT', 'entrega_pt'])),
                'EntregaCte'     => $this->parseDate($this->getValue($row, ['Entrega', 'Entrega Cte', 'entrega_cte'])),
                'PTvsCte'        => $this->parseInteger($this->getValue($row, ['Dif vs Compromiso', 'PT vs Cte', 'pt vs cte', 'pt_vs_cte'])),

                // Estado
                'EnProceso'      => $this->parseBoolean($this->getValue($row, ['Estado', 'estado', 'en_proceso'])),

                // === ADICIONALES ===
                'CuentaRizo'     => $this->parseString($this->getValue($row, ['Cuenta', 'Cuenta Rizo', 'cuenta_rizo']), 10),
                'CalibreRizo'    => $this->parseFloat($this->getValue($row, ['Calibre Rizo', 'calibre_rizo'])),
                'CalendarioId'   => $this->parseString($this->getValue($row, ['Jornada', 'jornada', 'calendario_id']), 15),
                'NoExisteBase'   => $this->parseString($this->getValue($row, ['Usar cuando no existe en base', 'no_existe_base']), 20),
                'ItemId'         => $this->parseString($this->getValue($row, ['Clave AX', 'item_id']), 20),
                'InventSizeId'   => $this->parseString($this->getValue($row, ['Tamaño AX', 'Tamano AX', 'invent_size_id']), 10),
                'Rasurado'       => $this->parseString($this->getValue($row, ['Rasurado', 'rasurado']), 2),
                'Ancho'          => $this->parseFloat($this->getValue($row, ['Ancho', 'ancho'])),
                'EficienciaSTD'  => $this->parseFloat($this->getValue($row, ['Ef Std', 'ef std', 'ef_std', 'eficiencia std', 'eficiencia_std', 'eficiencia'])),
                'VelocidadSTD'   => $this->parseInteger($this->getValue($row, ['Vel', 'vel', 'velocidad', 'velocidad_std'])),
                'FibraRizo'      => $this->parseString($this->getValue($row, ['Hilo', 'hilo', 'Fibra Rizo', 'fibra rizo', 'fibra_rizo']), 15),
                'CalibrePie'     => $this->parseFloat($this->getValue($row, ['calibre_pie'])),
                'FlogsId'        => $this->parseString($this->getValue($row, ['Id Flog', 'flogs_id']), 20),
			'NombreProyecto' => $this->parseString($this->getValue($row, ['Descrip.', 'Descrip', 'Descripción', 'Descripcion', 'nombre_proyecto']), 60),
                'CustName'       => $this->parseString($this->getValue($row, ['Nombre Cliente', 'cust_name']), 60),
                'AplicacionId'   => $this->parseString($this->getValue($row, ['Aplic.', 'Aplic', 'aplicacion_id']), 10),
                'Observaciones'  => $this->parseString($this->getValue($row, ['Obs', 'Observaciones', 'observaciones']), 100),
                'TipoPedido'     => $this->parseString($this->getValue($row, ['Tipo Ped.', 'Tipo Ped', 'tipo_pedido']), 20),
                'NoTiras'        => $this->parseInteger($this->getValue($row, ['Tiras', 'No Tiras', 'no_tiras'])),

                // Pedido / Producción
                'ProgramarProd'  => $this->parseDate($this->getValue($row, ['Day Sheduling', 'Day Scheduling', 'Día Scheduling', 'Dia Scheduling', 'programar_prod'])),
                'NoProduccion'   => $this->parseString($this->getValue($row, ['Orden Prod.', 'Orden Prod', 'no_produccion']), 15),
                'Programado'     => $this->parseDate($this->getValue($row, ['INN', 'Inn', 'programado'])),
                'SaldoPedido'    => $this->parseFloat($this->getValue($row, ['Saldos', 'Saldo Pedido', 'saldo_pedido', 'saldos'])),

                // Calc4..6: en BD son FLOAT, no date
                'Calc4'          => $this->parseFloat($this->getValue($row, ['Calc4', 'calc4', 'Calc 4'])),
                'Calc5'          => $this->parseFloat($this->getValue($row, ['Calc5', 'calc5', 'Calc 5'])),
                'Calc6'          => $this->parseFloat($this->getValue($row, ['Calc6', 'calc6', 'Calc 6'])),
                'RowNum'         => $this->rowCounter,
		]);

		$this->processedRows++;
		return $modelo;

        } catch (\Throwable $e) {
            Log::error('Error importando fila', [
                'row_num' => $this->rowCounter,
                'msg'     => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return null;
        }
    }

	public function batchSize(): int
	{
        // ~100 columnas => 15 filas ≈ 1500 parámetros (bajo 2100 de SQL Server)
		return 15;
	}

	public function chunkSize(): int
	{
        return 200;
	}

	public function getStats(): array
	{
		return [
			'processed_rows' => $this->processedRows,
            'skipped_rows'   => $this->skippedRows,
            'total_rows'     => $this->rowCounter,
        ];
    }

    /** ======================= Parse helpers ======================= */

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

        // Manejar fracciones como "5/3", "1/2", etc.
        if (str_contains($s, '/')) {
            $parts = explode('/', $s);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $numerator = (float)$parts[0];
                $denominator = (float)$parts[1];
                if ($denominator != 0) {
                    return $numerator / $denominator;
                }
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
        return $s === '' || $s === '-' ? null : (int)$s;
    }

    /**
     * Parse date with enhanced logging for FechaInicio and FechaFinal
     */
    private function parseDateWithLogging($value, string $fieldName): ?string
    {
        if ($value === null || $value === '') {
            Log::info("Campo {$fieldName}: valor vacío o nulo");
			return null;
		}

        Log::info("Parseando {$fieldName}", [
            'valor_original' => $value,
            'tipo' => gettype($value),
            'fila' => $this->rowCounter
        ]);

        $result = $this->parseDate($value);

        Log::info("Resultado {$fieldName}", [
            'valor_original' => $value,
            'resultado' => $result,
            'fila' => $this->rowCounter,
            'es_fecha_inicio' => $fieldName === 'FechaInicio',
            'es_fecha_final' => $fieldName === 'FechaFinal'
        ]);

        return $result;
    }

    /**
     * DATETIME robusto (Y-m-d H:i:s)
     * 1) DateTime/Carbon
     * 2) Serial Excel (PhpSpreadsheet)
     * 3) Unix timestamp
     * 4) Strings comunes (incluye "08-sep", "29-sep 13:45", "a. m./p. m.")
     */
 /**
 * DATETIME robusto (Y-m-d H:i:s)
 * Captura bien: 29/09/2025  09:00:00 a. m.  |  29/09/2025 1:30 p. m.  |  29-sep-2025 09:00
 */
private function parseDate($value): ?string
{
    if ($value === null || $value === '') return null;

    try {
        // 1) DateTime/Carbon
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value))
                ->format('Y-m-d H:i:s');
        }

        // 2) Serial Excel
        if (is_numeric($value)) {
            $n = (float)$value;
            if ($n > 0 && $n < 100000) {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n);
                return Carbon::instance($dt)->format('Y-m-d H:i:s');
            }
            // Unix timestamp (1990..2100)
            if ($n >= 631152000 && $n <= 4102444800) {
                return Carbon::createFromTimestamp((int)$n)->format('Y-m-d H:i:s');
            }
        }

        // 3) String: normalización agresiva para "a. m." / "p. m." y espacios
        $s = (string)$value;

        // Sustituye NBSP y espacios raros por espacio normal
        $s = preg_replace('/[\x{00A0}\x{2000}-\x{200B}]/u', ' ', $s);
        // Colapsa espacios múltiples
        $s = preg_replace('/\s+/', ' ', trim($s));

        // CASO ESPECIAL: Formato DD-MMM (como "29-sep", "30-sep", "01-oct") - PRIORIDAD ALTA
        if (preg_match('/^(\d{1,2})[\/\-\s]([a-zA-Záéíóúñ]+)(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?$/u', $s, $m)) {
            $meses = [
                'enero'=>'01','ene'=>'01','febrero'=>'02','feb'=>'02','marzo'=>'03','mar'=>'03','abril'=>'04','abr'=>'04',
                'mayo'=>'05','junio'=>'06','jun'=>'06','julio'=>'07','jul'=>'07','agosto'=>'08','ago'=>'08',
                'septiembre'=>'09','sept'=>'09','sep'=>'09','octubre'=>'10','oct'=>'10','noviembre'=>'11','nov'=>'11','diciembre'=>'12','dic'=>'12',
                'january'=>'01','jan'=>'01','february'=>'02','march'=>'03','april'=>'04','apr'=>'04','may'=>'05',
                'june'=>'06','july'=>'07','august'=>'08','aug'=>'08','september'=>'09','october'=>'10','november'=>'11','december'=>'12','dec'=>'12',
            ];

            $d = (int)$m[1];
            $mesNom = mb_strtolower($this->removeAccents($m[2]), 'UTF-8');
            $mes = $meses[$mesNom] ?? null;

            if ($mes) {
                // Si no hay año, usar el año actual
                $y = date('Y');

                // Si no hay hora, usar hora por defecto (09:00:00)
                $h = isset($m[3]) ? (int)$m[3] : 9;
                $i = isset($m[4]) ? (int)$m[4] : 0;
                $s2 = isset($m[5]) ? (int)$m[5] : 0;
                $ampm = isset($m[6]) ? strtoupper($m[6]) : null;

                if ($ampm === 'AM' && $h === 12) $h = 0;
                if ($ampm === 'PM' && $h < 12)  $h += 12;

                $str = sprintf('%04d-%s-%02d %02d:%02d:%02d', $y, $mes, $d, $h, $i, $s2);
                Log::info('✅ Fecha parseada desde DD-MMM (PRIORIDAD)', ['original' => $s, 'parseada' => $str]);
                return $this->esFechaValida($str) ? $str : null;
            }
        }

        // Normaliza variantes de am/pm en español (con puntos y/o espacios)
        // a. m. | a.m. | a m | am  -> AM   ;   p. m. | p.m. | p m | pm -> PM
        $s = preg_replace('/\b(a\s*\.?\s*m\.?)\b/iu', 'AM', $s);
        $s = preg_replace('/\b(p\s*\.?\s*m\.?)\b/iu', 'PM', $s);
        // También normaliza "a.m"/"p.m" sin el último punto
        $s = preg_replace('/\b(a\s*\.?\s*m)\b/iu', 'AM', $s);
        $s = preg_replace('/\b(p\s*\.?\s*m)\b/iu', 'PM', $s);

        Log::info('Fecha normalizada para AM/PM', ['original' => $value, 'normalizada' => $s]);

        // Soporte directo para: 29/09/2025  09:00:00 AM (doble espacio ok por colapso)
        // Probamos formatos explícitos primero
        $formatosPreferidos = [
            // Español con AM/PM
            'd/m/Y h:i:s A',
            'd-m-Y h:i:s A',
            'd/m/Y h:i A',
            'd-m-Y h:i A',
            // 12h con 1 dígito hora
            'd/m/Y g:i:s A',
            'd-m-Y g:i:s A',
            'd/m/Y g:i A',
            'd-m-Y g:i A',
            // 24h
            'd/m/Y H:i:s',
            'd-m-Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i',
            // ISO-like
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'Y/m/d H:i',
            // Solo fecha
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'Y/m/d',
        ];

        foreach ($formatosPreferidos as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $s);
                if ($c !== false) {
                    return $c->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
                // intenta siguiente
            }
        }

        // Caso especial: formato DD-MMM (como "29-sep", "30-sep", "01-oct")
        if (preg_match('/^(\d{1,2})[\/\-\s]([a-zA-Záéíóúñ]+)(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?$/u', $s, $m)) {
            $meses = [
                'enero'=>'01','ene'=>'01','febrero'=>'02','feb'=>'02','marzo'=>'03','mar'=>'03','abril'=>'04','abr'=>'04',
                'mayo'=>'05','junio'=>'06','jun'=>'06','julio'=>'07','jul'=>'07','agosto'=>'08','ago'=>'08',
                'septiembre'=>'09','sept'=>'09','sep'=>'09','octubre'=>'10','oct'=>'10','noviembre'=>'11','nov'=>'11','diciembre'=>'12','dic'=>'12',
                'january'=>'01','jan'=>'01','february'=>'02','march'=>'03','april'=>'04','apr'=>'04','may'=>'05',
                'june'=>'06','july'=>'07','august'=>'08','aug'=>'08','september'=>'09','october'=>'10','november'=>'11','december'=>'12','dec'=>'12',
            ];

            $d = (int)$m[1];
            $mesNom = mb_strtolower($this->removeAccents($m[2]), 'UTF-8');
            $mes = $meses[$mesNom] ?? null;

            if ($mes) {
                // Si no hay año, usar el año actual
                $y = date('Y');

                // Si no hay hora, usar hora por defecto (09:00:00)
                $h = isset($m[3]) ? (int)$m[3] : 9;
                $i = isset($m[4]) ? (int)$m[4] : 0;
                $s2 = isset($m[5]) ? (int)$m[5] : 0;
                $ampm = isset($m[6]) ? strtoupper($m[6]) : null;

                if ($ampm === 'AM' && $h === 12) $h = 0;
                if ($ampm === 'PM' && $h < 12)  $h += 12;

                $str = sprintf('%04d-%s-%02d %02d:%02d:%02d', $y, $mes, $d, $h, $i, $s2);
                Log::info('Fecha parseada desde DD-MMM', ['original' => $s, 'parseada' => $str]);
                return $this->esFechaValida($str) ? $str : null;
            }
        }

        // Caso "29-sep-2025 09:00", "08-sep 13:45", etc. (mes con texto ES/EN con año)
        if (preg_match('/^(\d{1,2})[\/\-\s]([a-zA-Záéíóúñ]+)[\/\-\s](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?$/u', $s, $m)) {
            $meses = [
                'enero'=>'01','ene'=>'01','febrero'=>'02','feb'=>'02','marzo'=>'03','mar'=>'03','abril'=>'04','abr'=>'04',
                'mayo'=>'05','junio'=>'06','jun'=>'06','julio'=>'07','jul'=>'07','agosto'=>'08','ago'=>'08',
                'septiembre'=>'09','sept'=>'09','sep'=>'09','octubre'=>'10','oct'=>'10','noviembre'=>'11','nov'=>'11','diciembre'=>'12','dic'=>'12',
                'january'=>'01','jan'=>'01','february'=>'02','march'=>'03','april'=>'04','apr'=>'04','may'=>'05',
                'june'=>'06','july'=>'07','august'=>'08','aug'=>'08','september'=>'09','october'=>'10','november'=>'11','december'=>'12','dec'=>'12',
            ];

            $d = (int)$m[1];
            $mesNom = mb_strtolower($this->removeAccents($m[2]), 'UTF-8');
            $y = (int)$m[3]; if ($y < 100) $y += ($y < 50) ? 2000 : 1900;
            $mes = $meses[$mesNom] ?? null;

            if ($mes) {
                $h = isset($m[4]) ? (int)$m[4] : 9; // Hora por defecto 9:00
                $i = isset($m[5]) ? (int)$m[5] : 0;
                $s2 = isset($m[6]) ? (int)$m[6] : 0;
                $ampm = isset($m[7]) ? strtoupper($m[7]) : null;

                if ($ampm === 'AM' && $h === 12) $h = 0;
                if ($ampm === 'PM' && $h < 12)  $h += 12;

                $str = sprintf('%04d-%s-%02d %02d:%02d:%02d', $y, $mes, $d, $h, $i, $s2);
                Log::info('Fecha parseada desde DD-MMM-YYYY', ['original' => $s, 'parseada' => $str]);
                return $this->esFechaValida($str) ? $str : null;
            }
        }

        // Último recurso (parser libre)
        return Carbon::parse($s)->format('Y-m-d H:i:s');

    } catch (\Throwable $e) {
        Log::warning('No se pudo parsear fecha', ['valor' => $value, 'msg' => $e->getMessage()]);
			return null;
		}
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

    /**
     * Parse date only (Y-m-d) - sin hora
     */
    private function parseDateOnly($value): ?string
    {
        if ($value === null || $value === '') return null;

        try {
            // Si ya es una instancia de DateTime/Carbon
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(\DateTime::createFromInterface($value))
                    ->format('Y-m-d');
            }

            // Si es un timestamp numérico de Excel
			if (is_numeric($value)) {
                $n = (float)$value;
                if ($n > 0 && $n < 100000) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n);
                    return Carbon::instance($dt)->format('Y-m-d');
                }
            }

            // Parsear como string y devolver solo fecha
            $parsed = $this->parseDate($value);
            if ($parsed) {
                return substr($parsed, 0, 10); // Y-m-d H:i:s -> Y-m-d
            }

            return null;

        } catch (\Throwable $e) {
            Log::warning('No se pudo parsear fecha (solo fecha)', ['valor' => $value, 'msg' => $e->getMessage()]);
            return null;
        }
    }

    /** ======================= Utilidades de encabezado ======================= */

    private function getValue(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeKey($alias);
            if (array_key_exists($key, $row)) {
                $val = $row[$key];
                if ($val !== '' && $val !== null) return $val;
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
                if (!str_contains($k, mb_strtolower($w, 'UTF-8'))) {
                    $ok = false; break;
                }
            }
            if (!$ok) continue;

            foreach ($mustNotContain as $w) {
                if (str_contains($k, mb_strtolower($w, 'UTF-8'))) {
                    $ok = false; break;
                }
            }
            if ($ok) return ($value !== '' && $value !== null) ? $value : null;
        }
				return null;
			}

    // (Implementado por si luego quieres reactivarlo correctamente sobre VALORES)

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

    /**
     * Generar un ID único para cada registro
     */
    private function generateUniqueId(): int
    {
        // Usar timestamp + contador de fila para garantizar unicidad
        return (int)(time() * 1000) + $this->rowCounter;
    }
}
