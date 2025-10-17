<?php

namespace App\Imports;

use App\Models\ReqProgramaTejido;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReqProgramaTejidoSimpleImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
	private int $rowCounter = 0;
	private int $processedRows = 0;
	private int $skippedRows = 0;

	/**
	 * Obtiene el primer valor no vacío para un conjunto de alias de encabezado, con normalización.
	 */
	private function getValue(array $row, array $aliases)
	{
		foreach ($aliases as $alias) {
			$key = $this->normalizeKey($alias);
			if (array_key_exists($key, $row)) {
				$val = $row[$key];
				if ($val !== '' && $val !== null) {
					return $val;
				}
			}
		}
		return null;
	}

	/**
	 * Obtiene valores de columnas con el mismo nombre por posición (para columnas duplicadas)
	 */
	private function getValueByPosition(array $row, string $columnName, int $position = 1)
	{
		$normalizedColumn = $this->normalizeKey($columnName);
		$found = 0;

		// Buscar coincidencias exactas primero
		foreach ($row as $key => $value) {
			if ($key === $normalizedColumn) {
				$found++;
				if ($found === $position) {
					return ($value !== '' && $value !== null) ? $value : null;
				}
			}
		}

		// Si no encuentra coincidencias exactas, buscar coincidencias parciales
		foreach ($row as $key => $value) {
			if (strpos($key, $normalizedColumn) !== false || strpos($normalizedColumn, $key) !== false) {
				$found++;
				if ($found === $position) {
					return ($value !== '' && $value !== null) ? $value : null;
				}
			}
		}

		return null;
	}

	/** Normaliza una clave de encabezado: minúsculas, sin acentos y sólo [a-z0-9_] */
	private function normalizeKey(string $key): string
	{
		$s = mb_strtolower(trim($key), 'UTF-8');
		$s = $this->removeAccents($s);
		// Reemplaza cualquier cosa que no sea a-z0-9 por guion bajo
		$s = preg_replace('/[^a-z0-9]+/u', '_', $s);
		// Colapsa múltiples guiones bajos
		$s = preg_replace('/_+/', '_', $s);
		return trim($s, '_');
	}

	/** Normaliza todas las claves del arreglo de fila */
	private function normalizeRowKeys(array $row): array
	{
		$out = [];
		foreach ($row as $k => $v) {
			$out[$this->normalizeKey((string)$k)] = $v;
		}
		return $out;
	}

	/**
	 * Elimina acentos de un string de forma segura.
	 */
	private function removeAccents(string $value): string
	{
		$trans = [
			'á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','ñ' => 'n',
			'Á' => 'A','É' => 'E','Í' => 'I','Ó' => 'O','Ú' => 'U','Ñ' => 'N',
		];
		return strtr($value, $trans);
	}

	/**
	 * 🔍 Encuentra la primera columna que contenga TODAS las palabras clave
	 * y que NO contenga ninguna de las palabras excluidas
	 */
	private function findFirstColumnContaining(array $row, array $mustContain, array $mustNotContain = [])
	{
		foreach ($row as $key => $value) {
			$keyLower = strtolower($key);

			// Verificar que contenga TODAS las palabras requeridas
			$hasAll = true;
			foreach ($mustContain as $word) {
				if (strpos($keyLower, strtolower($word)) === false) {
					$hasAll = false;
					break;
				}
			}

			if (!$hasAll) {
				continue;
			}

			// Verificar que NO contenga ninguna palabra excluida
			$hasExcluded = false;
			foreach ($mustNotContain as $excludedWord) {
				if (strpos($keyLower, strtolower($excludedWord)) !== false) {
					$hasExcluded = true;
					break;
				}
			}

			if ($hasExcluded) {
				continue;
			}

			// Si pasa todas las validaciones, retornar el valor
			return ($value !== '' && $value !== null) ? $value : null;
		}

		return null;
	}

	/** Detecta si una fila luce como un encabezado repetido dentro del cuerpo */
	private function looksLikeHeaderRow(array $row): bool
	{
		$headerHints = ['cuenta','producto','telar','inn','dia','fecha','cliente','pedido'];
		$hits = 0;
		$totalKeys = count($row);

		// Solo considerar encabezado si hay muchas claves que parecen encabezados
		foreach ($row as $k => $v) {
			foreach ($headerHints as $hint) {
				if (strpos($k, $hint) !== false) {
					$hits++;
					break;
				}
			}
		}

		// Ser más estricto: solo si más del 50% de las claves parecen encabezados
		return $hits >= max(3, $totalKeys * 0.5);
	}

	/**
	 * @param array $row
	 *
	 * @return \Illuminate\Database\Eloquent\Model|null
	 */
	public function model(array $row)
	{
		try {
			// Normalizar claves de encabezado para búsqueda robusta
			$row = $this->normalizeRowKeys($row);
			// Saltar filas que son encabezados repetidos dentro del cuerpo
			if ($this->looksLikeHeaderRow($row)) {
				Log::info('Saltando fila que parece encabezado', ['row' => array_keys($row)]);
				$this->skippedRows++;
				return null;
			}

	$this->rowCounter++;

	// Solo loguear la primera fila para ver las claves
	if ($this->rowCounter === 1) {
		Log::info("========== CLAVES DEL EXCEL (PRIMERA FILA) ==========");
		Log::info("Total de columnas: " . count($row));
		Log::info("Claves normalizadas:", array_keys($row));

		// Buscar columnas específicas
		$columnasImportantes = [];
		foreach (array_keys($row) as $key) {
			if (strpos($key, 'prod') !== false ||
			    strpos($key, 'std') !== false ||
			    strpos($key, 'peso') !== false ||
			    strpos($key, 'toa') !== false) {
				$columnasImportantes[] = $key;
			}
		}
		Log::info("Columnas con 'prod', 'std', 'peso' o 'toa':", $columnasImportantes);
		Log::info("====================================================");
	}

			// Mapear las columnas del Excel a los campos de la tabla
			$modelo = new ReqProgramaTejido([
			// Datos principales (acepta encabezados en español como en tu Excel)
			'SalonTejidoId' => $this->parseString($this->getValue($row, ['Salón', 'Salon', 'Salon Tejido Id', 'salon_tejido_id']), 10),
			'NoTelarId' => $this->parseString($this->getValue($row, ['Telar', 'No Telar', 'no_telar_id']), 10),
			'Ultimo' => $this->parseString($this->getValue($row, ['Último','Ultimo', 'ultimo']), 2),
			'CambioHilo' => $this->parseString($this->getValue($row, ['Cambios Hilo', 'Cambio Hilo', 'CAMBIOS HILO', 'CAMBIO HILO', 'cambio_hilo']), 2),
			'Maquina' => $this->parseString($this->getValue($row, ['Maq', 'Máq', 'Maquina', 'máquina', 'maquina']), 15),
			'NombreProducto' => $this->parseString($this->getValue($row, ['Producto', 'Nombre Producto', 'nombre_producto']), 100),
			'TamanoClave' => $this->parseString($this->getValue($row, ['Clave Mod.', 'Clave mod.', 'Clave Mod', 'Tamaño Clave', 'Tamano Clave', 'tamano_clave']), 20),
			'MedidaPlano' => $this->parseInteger($this->getValue($row, ['Plano', 'Medida Plano', 'medida_plano'])),
			'CuentaPie' => $this->parseString($this->getValue($row, ['Cuenta Pie', 'cuenta_pie']), 10),
			'CodColorCtaPie' => $this->parseString($this->getValue($row, ['Código Color Pie', 'Codigo Color Pie', 'Cod Color Cta Pie', 'cod color cta pie', 'cod_color_cta_pie']), 10),
			'NombreCPie' => $this->parseString($this->getValue($row, ['Color Pie', 'Nombre C Pie', 'nombre c pie', 'nombre_cpie']), 60),
			'AnchoToalla' => $this->parseInteger($this->getValue($row, ['Ancho por Toalla', 'Ancho Toalla', 'ancho_toalla'])),

			// Trama
			'CodColorTrama' => $this->parseString($this->getValue($row, ['Código Color Tra', 'Codigo Color Tra', 'Cod Color Trama', 'cod_color_trama']), 10),
			'ColorTrama' => $this->parseString($this->getValue($row, ['Color Tra', 'Color Trama', 'color_trama']), 60),
			'CalibreTrama' => $this->parseFloat($this->getValue($row, ['Calibre Tra', 'Calibre Trama', 'calibre_trama'])),
			'FibraTrama' => $this->parseString($this->getValue($row, ['Fibra Trama', 'fibra_trama']), 15),
			'PasadasTrama' => $this->parseInteger($this->getValue($row, ['Pasadas Tra', 'Pasadas Trama', 'pasadas_trama'])),

			// Combinaciones 1..5
			'CalibreComb12' => $this->parseFloat($this->getValue($row, ['Calibre C1', 'Calibre Comb1/2', 'calibre comb1/2', 'calibre_comb12'])),
			'FibraComb1' => $this->parseString($this->getValue($row, ['Fibra C1', 'Fibra Comb1', 'fibra comb1', 'fibra_comb1']), 15),
			'CodColorComb1' => $this->parseString($this->getValue($row, ['Código Color C1', 'Codigo Color C1', 'Cod Color Comb1', 'cod color comb1', 'cod_color_comb1']), 10),
			'NombreCC1' => $this->parseString($this->getValue($row, ['Color C1', 'Nombre CC1', 'nombre cc1', 'nombre_cc1']), 60),

			'CalibreComb22' => $this->parseFloat($this->getValue($row, ['Calibre C2', 'Calibre Comb2/2', 'calibre comb2/2', 'calibre_comb22'])),
			'FibraComb2' => $this->parseString($this->getValue($row, ['Fibra C2', 'Fibra Comb2', 'fibra comb2', 'fibra_comb2']), 15),
			'CodColorComb2' => $this->parseString($this->getValue($row, ['Código Color C2', 'Codigo Color C2', 'Cod Color Comb2', 'cod color comb2', 'cod_color_comb2']), 10),
			'NombreCC2' => $this->parseString($this->getValue($row, ['Color C2', 'Nombre CC2', 'nombre cc2', 'nombre_cc2']), 60),

			'CalibreComb32' => $this->parseFloat($this->getValue($row, ['Calibre C3', 'Calibre Comb3/2', 'calibre comb3/2', 'calibre_comb32'])),
			'FibraComb3' => $this->parseString($this->getValue($row, ['Fibra C3', 'Fibra Comb3', 'fibra comb3', 'fibra_comb3']), 15),
			'CodColorComb3' => $this->parseString($this->getValue($row, ['Código Color C3', 'Codigo Color C3', 'Cod Color Comb3', 'cod color comb3', 'cod_color_comb3']), 10),
			'NombreCC3' => $this->parseString($this->getValue($row, ['Color C3', 'Nombre CC3', 'nombre cc3', 'nombre_cc3']), 60),

			'CalibreComb42' => $this->parseFloat($this->getValue($row, ['Calibre C4', 'Calibre Comb4/2', 'calibre comb4/2', 'calibre_comb42'])),
			'FibraComb4' => $this->parseString($this->getValue($row, ['Fibra C4', 'Fibra Comb4', 'fibra comb4', 'fibra_comb4']), 15),
			'CodColorComb4' => $this->parseString($this->getValue($row, ['Código Color C4', 'Codigo Color C4', 'Cod Color Comb4', 'cod color comb4', 'cod_color_comb4']), 10),
			'NombreCC4' => $this->parseString($this->getValue($row, ['Color C4', 'Nombre CC4', 'nombre cc4', 'nombre_cc4']), 60),

			'CalibreComb52' => $this->parseFloat($this->getValue($row, ['Calibre C5', 'Calibre Comb5/2', 'calibre comb5/2', 'calibre_comb52'])),
			'FibraComb5' => $this->parseString($this->getValue($row, ['Fibra C5', 'Fibra Comb5', 'fibra comb5', 'fibra_comb5']), 15),
			'CodColorComb5' => $this->parseString($this->getValue($row, ['Código Color C5', 'Codigo Color C5', 'Cod Color Comb5', 'cod color comb5', 'cod_color_comb5']), 10),
			'NombreCC5' => $this->parseString($this->getValue($row, ['Color C5', 'Nombre CC5', 'nombre cc5', 'nombre_cc5']), 60),

			// Otros numéricos
			'Peine' => $this->parseInteger($this->getValue($row, ['Pei.', 'Pei', 'Peine', 'peine'])),
			'Luchaje' => $this->parseInteger($this->getValue($row, ['Lcr', 'Luchaje', 'luchaje'])),
			'PesoCrudo' => $this->parseInteger($this->getValue($row, ['Pcr', 'Peso Crudo', 'peso crudo', 'peso_crudo'])),
		'PesoGRM2' => $this->parseInteger($this->getValue($row, ['Peso (gr/m²)', 'Peso GRM2', 'peso grm2', 'peso_grm2', 'Peso    (gr / m²)', 'peso gr m 2', 'peso_gr_m_2'])),
		'DiasEficiencia' => $this->parseFloat($this->getValue($row, ['Días Ef.', 'Dias Ef.', 'Días Eficiencia', 'Dias Eficiencia', 'dias_eficiencia'])),

	// 🔍 COLUMNAS PROBLEMÁTICAS - Búsqueda flexible por contenido
	'ProdKgDia' => $this->parseFloat($this->findFirstColumnContaining($row, ['prod', 'kg', 'dia'], ['2'])),
	'StdDia' => $this->parseFloat($this->findFirstColumnContaining($row, ['std', 'dia'], ['toa', 'hr', '100', 'efectivo'])),
	'ProdKgDia2' => $this->parseFloat($this->findFirstColumnContaining($row, ['prod', 'kg', 'dia', '2'])),
	'StdToaHra' => $this->parseFloat($this->findFirstColumnContaining($row, ['std', 'toa', 'hr', '100'])),

	'DiasJornada' => $this->parseFloat($this->getValue($row, ['Días Jornada', 'Dias Jornada Completa', 'Dias jornada completa', 'dias_jornada','Jornada','jornada', 'dias jornada completa'])),
	'HorasProd' => $this->parseFloat($this->getValue($row, ['Horas', 'Horas Prod', 'horas prod', 'horas_prod'])),
	'StdHrsEfect' => $this->parseFloat($this->findFirstColumnContaining($row, ['std', 'hr', 'efectivo']) ?: $this->getValue($row, ['Std/Hr Efectivo', 'STD Hrs Efect', 'std_hrs_efect'])),

			// Fechas
			'FechaInicio' => $this->parseDate($this->getValue($row, ['Inicio', 'Fecha Inicio', 'fecha inicio', 'fecha_inicio'])),
			'FechaFinal' => $this->parseDate($this->getValue($row, ['Fin', 'Fecha Final', 'fecha final', 'fecha_final'])),

			// Fechas Compromiso - Manejar columnas con nombres específicos
			'EntregaProduc' => $this->parseDate(
				$this->getValue($row, ['Fecha Compromiso Prod', 'Fecha Compromiso Prod.', 'Entrega Producción', 'Entrega Produccion', 'entrega_produc'])
			),
			'EntregaPT' => $this->parseDate(
				$this->getValue($row, ['Fecha Compromiso PT', 'Entrega PT', 'entrega_pt'])
			),
			'EntregaCte' => $this->parseDate($this->getValue($row, ['Entrega', 'Entrega Cte', 'entrega_cte'])),
			'PTvsCte' => $this->parseInteger($this->getValue($row, ['Dif vs Compromiso', 'PT vs Cte', 'pt vs cte', 'pt_vs_cte'])),

			// Estado -> EnProceso
			'EnProceso' => $this->parseBoolean($this->getValue($row, ['Estado', 'estado', 'en_proceso'])),

			// Campos adicionales (fallback + aliases en español)
			'CuentaRizo' => $this->parseString($this->getValue($row, ['Cuenta', 'Cuenta Rizo', 'cuenta_rizo']), 10),
			'CalibreRizo' => $this->parseFloat($this->getValue($row, ['Calibre Rizo', 'calibre_rizo'])),
			'CalendarioId' => $this->parseString($this->getValue($row, ['Jornada', 'jornada', 'calendario_id']), 15),
			'NoExisteBase' => $this->parseString($this->getValue($row, ['Usar cuando no existe en base', 'no_existe_base']), 20),
			'ItemId' => $this->parseString($this->getValue($row, ['Clave AX', 'item_id']), 20),
			'InventSizeId' => $this->parseString($this->getValue($row, ['Tamaño AX', 'Tamano AX', 'invent_size_id']), 10),
			'Rasurado' => $this->parseString($this->getValue($row, ['Rasurado', 'rasurado']), 2),
			'Ancho' => $this->parseFloat($this->getValue($row, ['Ancho', 'ancho'])),
			'EficienciaSTD' => $this->parseFloat($this->getValue($row, ['Ef Std', 'ef std', 'ef_std', 'eficiencia std', 'eficiencia_std', 'eficiencia'])),
			'VelocidadSTD' => $this->parseInteger($this->getValue($row, ['Vel', 'vel', 'velocidad', 'velocidad_std'])),
			'FibraRizo' => $this->parseString($this->getValue($row, ['Hilo', 'hilo', 'Fibra Rizo', 'fibra rizo', 'fibra_rizo']), 15),
			'CalibrePie' => $this->parseFloat($this->getValue($row, ['calibre_pie'])),
			'FlogsId' => $this->parseString($this->getValue($row, ['Id Flog', 'flogs_id']), 20),
			'NombreProyecto' => $this->parseString($this->getValue($row, ['Descrip.', 'Descrip', 'Descripción', 'Descripcion', 'nombre_proyecto']), 60),
			'CustName' => $this->parseString($this->getValue($row, ['Nombre Cliente', 'cust_name']), 60),
			'AplicacionId' => $this->parseString($this->getValue($row, ['Aplic.', 'Aplic', 'aplicacion_id']), 10),
			'Observaciones' => $this->parseString($this->getValue($row, ['Obs', 'Observaciones', 'observaciones']), 100),
		'TipoPedido' => $this->parseString($this->getValue($row, ['Tipo Ped.', 'Tipo Ped', 'tipo_pedido']), 20),
		'NoTiras' => $this->parseInteger($this->getValue($row, ['Tiras', 'No Tiras', 'no_tiras'])),

		// Campos adicionales de pedido y producción
		'ProgramarProd' => $this->parseDate($this->getValue($row, ['Day Sheduling', 'Day Scheduling', 'Día Scheduling', 'Dia Scheduling', 'programar_prod'])),
		'NoProduccion' => $this->parseString($this->getValue($row, ['Orden Prod.', 'Orden Prod', 'no_produccion']), 15),
		'Programado' => $this->parseDate($this->getValue($row, ['INN', 'Inn', 'programado'])),
		'SaldoPedido' => $this->parseFloat($this->getValue($row, ['Saldos', 'Saldo Pedido', 'saldo_pedido', 'saldos'])),

		// Calc4, Calc5, Calc6 como FLOAT (la BD espera float, no datetime)
		// Si en Excel se ven como fechas, es porque son números de Excel interpretados como fechas
		'Calc4' => $this->parseFloat($this->getValue($row, ['Calc4', 'calc4', 'Calc 4'])),
		'Calc5' => $this->parseFloat($this->getValue($row, ['Calc5', 'calc5', 'Calc 5'])),
		'Calc6' => $this->parseFloat($this->getValue($row, ['Calc6', 'calc6', 'Calc 6'])),
		'RowNum' => $this->rowCounter,

		]);

		$this->processedRows++;
		Log::info("Modelo creado exitosamente para fila {$this->rowCounter}");
		return $modelo;

		} catch (\Exception $e) {
			// Log the error but continue processing
			Log::error('Error importing row: ' . $e->getMessage(), [
				'row' => $row,
				'row_counter' => $this->rowCounter,
				'error_line' => $e->getLine(),
				'error_file' => $e->getFile()
			]);
			return null; // Skip this row
		}
	}

	/**
	 * @return int
	 */
	public function batchSize(): int
	{
		// Reducir el tamaño de lote para evitar el límite de 2100 parámetros en SQL Server
		// Aproximación: ~100 columnas por fila => 15 filas ≈ 1500 parámetros
		return 15;
	}

	/**
	 * Tamaño de lectura por chunks para controlar memoria y parámetros
	 */
	public function chunkSize(): int
	{
		return 200; // leer 200 filas por chunk pero insertar de 15 en 15
	}

	/**
	 * Obtiene estadísticas de la importación
	 */
	public function getStats(): array
	{
		return [
			'processed_rows' => $this->processedRows,
			'skipped_rows' => $this->skippedRows,
			'total_rows' => $this->rowCounter
		];
	}


	/**
	 * Parse boolean values
	 */
	private function parseBoolean($value)
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			$value = strtolower(trim($value));
			return in_array($value, ['true', '1', 'yes', 'si', 'sí', 'verdadero']);
		}

		return (bool) $value;
	}

	/**
	 * Parse float values, soporta % y comas
	 */
	private function parseFloat($value)
	{
		if (is_null($value) || $value === '') {
			return null;
		}
		$hadPercent = false;
		$value = (string) $value;
		if (str_contains($value, '%')) { $hadPercent = true; }
		$value = str_replace(['%',' '], '', $value);
		$value = str_replace(',', '.', $value);
		$value = preg_replace('/[^0-9.\-]/', '', $value);
		if ($value === '' || $value === '-' || $value === '.') {
			return null;
		}
		$num = (float) $value;
		if ($hadPercent) {
			$num = $num / 100.0;
		}
		return $num;
	}

	/**
	 * Parse integer values
	 */
	private function parseInteger($value)
	{
		if (is_null($value) || $value === '') {
			return null;
		}

		// Remove commas and convert to integer
		$value = str_replace(',', '', $value);
		return is_numeric($value) ? (int) $value : null;
	}

	/**
	 * Parse date values - Versión robusta que maneja múltiples formatos
	 */
	private function parseDate($value)
	{
		if (is_null($value) || $value === '') {
			return null;
		}

		try {
			// Si ya es una instancia de Carbon
			if ($value instanceof Carbon) {
				return $value->format('Y-m-d');
			}

			// Si es un timestamp numérico (número de Excel)
			if (is_numeric($value)) {
				$excelDate = (float)$value;

				// Validar que sea un número razonable para una fecha
				if ($excelDate > 0 && $excelDate < 60000) {
					$days = floor($excelDate);
					$fraction = $excelDate - $days;

					// Ajuste por bug de Excel (29 de febrero de 1900 no existe)
					if ($excelDate > 60) {
						$days = $days - 1;
					}

					// Crear fecha base del 1900-01-01
					$baseDate = new \DateTime('1900-01-01');

					// Sumar los días
					if ($days > 1) {
						$baseDate->modify('+' . ($days - 1) . ' days');
					}

					return $baseDate->format('Y-m-d');
				}

				// Si es un pequeño número, probablemente sea un timestamp Unix
				return Carbon::createFromTimestamp($value)->format('Y-m-d');
			}

			$value = trim((string)$value);

			// Limpiar caracteres extraños y normalizar
			$value = preg_replace('/[^\w\s\-\/\.\:]/', '', $value);
			$value = preg_replace('/\s+/', ' ', $value);

			// Mapeo de meses en español e inglés
			$meses = [
				// Español
				'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
				'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
				'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
				// Abreviaciones español
				'ene' => '01', 'feb' => '02', 'mar' => '03', 'abr' => '04',
				'jun' => '06', 'jul' => '07', 'ago' => '08',
				'sep' => '09', 'sept' => '09', 'oct' => '10', 'nov' => '11', 'dic' => '12',
				// Inglés
				'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
				'june' => '06', 'july' => '07', 'august' => '08',
				'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
				// Abreviaciones inglés
				'jan' => '01', 'apr' => '04',
				'aug' => '08', 'dec' => '12'
			];

			// Patrones de fecha que manejamos
			$patrones = [
				// DD/MM/YYYY o DD-MM-YYYY (detecta inteligentemente el formato)
				'/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/' => function($matches) {
					// Si el primer número es > 12, es formato DD/MM/YYYY
					if ($matches[1] > 12) {
						return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
					}
					// Si el segundo número es > 12, es formato MM/DD/YYYY
					if ($matches[2] > 12) {
						return sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
					}
					// Ambos <= 12, asumir formato europeo (DD/MM/YYYY)
					return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
				},
				// DD/MM/YY o DD-MM-YY
				'/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/' => function($matches) {
					$year = (int)$matches[3];
					$year += ($year < 50) ? 2000 : 1900; // Asumir 2000+ si < 50
					return sprintf('%04d-%02d-%02d', $year, $matches[2], $matches[1]);
				},
				// YYYY/MM/DD o YYYY-MM-DD
				'/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/' => function($matches) {
					return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
				},
				// DD Mes YYYY (con nombre de mes)
				'/^(\d{1,2})\s+([a-zA-ZáéíóúñÁÉÍÓÚÑ]+)\s+(\d{4})$/i' => function($matches) use ($meses) {
					$mesNombre = strtolower(trim($matches[2]));
					$mes = $meses[$mesNombre] ?? null;
					if ($mes) {
						return sprintf('%04d-%s-%02d', $matches[3], $mes, $matches[1]);
					}
					return null;
				},
				// Mes DD, YYYY (formato americano)
				'/^([a-zA-ZáéíóúñÁÉÍÓÚÑ]+)\s+(\d{1,2}),?\s+(\d{4})$/i' => function($matches) use ($meses) {
					$mesNombre = strtolower(trim($matches[1]));
					$mes = $meses[$mesNombre] ?? null;
					if ($mes) {
						return sprintf('%04d-%s-%02d', $matches[3], $mes, $matches[2]);
					}
					return null;
				},
				// DD Mes (año actual)
				'/^(\d{1,2})\s+([a-zA-ZáéíóúñÁÉÍÓÚÑ]+)$/i' => function($matches) use ($meses) {
					$mesNombre = strtolower(trim($matches[2]));
					$mes = $meses[$mesNombre] ?? null;
					if ($mes) {
						$year = date('Y');
						return sprintf('%04d-%s-%02d', $year, $mes, $matches[1]);
					}
					return null;
				},
				// DD-Mes-YY o DD/Mes/YY
				'/^(\d{1,2})[\/\-]([a-zA-ZáéíóúñÁÉÍÓÚÑ]+)[\/\-](\d{2})$/i' => function($matches) use ($meses) {
					$mesNombre = strtolower(trim($matches[2]));
					$mes = $meses[$mesNombre] ?? null;
					if ($mes) {
						$year = (int)$matches[3];
						$year += ($year < 50) ? 2000 : 1900;
						return sprintf('%04d-%s-%02d', $year, $mes, $matches[1]);
					}
					return null;
				},
				// Solo año (asumir 1 de enero)
				'/^(\d{4})$/' => function($matches) {
					return sprintf('%04d-01-01', $matches[1]);
				},
				// DD/MM (año actual)
				'/^(\d{1,2})[\/\-](\d{1,2})$/' => function($matches) {
					$year = date('Y');
					return sprintf('%04d-%02d-%02d', $year, $matches[2], $matches[1]);
				}
			];

			// Intentar cada patrón
			foreach ($patrones as $patron => $callback) {
				if (preg_match($patron, $value, $matches)) {
					$resultado = $callback($matches);
					if ($resultado && $this->esFechaValida($resultado)) {
						return $resultado;
					}
				}
			}

			// Si no coincide con ningún patrón, intentar parsear directamente con Carbon
			try {
				$fecha = Carbon::parse($value);
				return $fecha->format('Y-m-d');
			} catch (\Exception $e) {
				// Log del error para debugging
				Log::warning('No se pudo parsear la fecha: ' . $value . ' - Error: ' . $e->getMessage());
				return null;
			}

		} catch (\Exception $e) {
			Log::warning('Error general parseando fecha: ' . $value . ' - Error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Valida si una fecha en formato YYYY-MM-DD es válida
	 */
	private function esFechaValida($fecha)
	{
		try {
			$partes = explode('-', $fecha);
			if (count($partes) !== 3) return false;

			$year = (int)$partes[0];
			$month = (int)$partes[1];
			$day = (int)$partes[2];

			// Validaciones básicas
			if ($year < 1900 || $year > 2100) return false;
			if ($month < 1 || $month > 12) return false;
			if ($day < 1 || $day > 31) return false;

			// Validar que la fecha realmente existe
			return checkdate($month, $day, $year);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Parse string values with max length trimming
	 */
	private function parseString($value, int $maxLength)
	{
		if (is_null($value) || $value === '') {
			return null;
		}
		$s = trim((string)$value);
		// Normalize spaces
		$s = preg_replace('/\s+/', ' ', $s);
		// Trim to max length (multibyte-safe)
		if (function_exists('mb_substr')) {
			$s = mb_substr($s, 0, $maxLength, 'UTF-8');
		} else {
			$s = substr($s, 0, $maxLength);
		}
		return $s === '' ? null : $s;
	}
}
