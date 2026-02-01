<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date;
class OrdenDeCambioFelpaController extends Controller
{
    /** Cache simple para modelos codificados (TamanoClave|SalonTejidoId). */
    private static array $modeloCodificadoCache = [];

    /**
     * Generar Excel de Orden de Cambio de Modelo desde datos de la BD.
     * Usado cuando se libera una ordeddn desde LiberarOrdenesController.
     *
     * @param iterable<ReqProgramaTejido> $registros
     */
    public function generarExcelDesdeBD(iterable $registros)
    {
        $horaActual = $this->obtenerHoraActual();

        try {
            // 1) Cargar plantilla fija
            $spreadsheet = $this->cargarPlantillaExcel(new Request());

            // 2) Crear/asegurar hoja REGISTRO con encabezados
            $this->generarTablaRegistro($spreadsheet);

            // 3) Volcar registros de BD a REGISTRO
            $filaRegistro = 2;
            $registrosParaFormato = [];

            foreach ($registros as $registro) {
                /** @var ReqProgramaTejido $registro */
                $datosRegistro = $this->mapearDatosBDaRegistro($registro, $horaActual);

                // Calcular repeticiones y no_marbetes usando las mismas fórmulas que Excel
                // AX = TRUNCAR((41.5/S)/AW*1000) donde S es p_crudo y AW es tiras
                $pCrudo = $datosRegistro['p_crudo'] ?? $registro->PesoCrudo ?? null;
                $tiras = $datosRegistro['tiras'] ?? $registro->NoTiras ?? null;
                $repeticionesCalculada = '';
                if ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    $repeticionesCalculada = (string) floor(((41.5 / (float)$pCrudo) / (float)$tiras) * 1000);
                }

                // AY se toma directo del campo ya guardado (SaldoMarbete)
                $noMarbetesCalculado = '0';
                if (isset($registro->SaldoMarbete) && is_numeric($registro->SaldoMarbete)) {
                    $noMarbetesCalculado = (string) (int) $registro->SaldoMarbete;
                }

                // Actualizar datosRegistro con los valores calculados
                $datosRegistro['repeticiones'] = $repeticionesCalculada;
                $datosRegistro['no_marbetes'] = $noMarbetesCalculado;

                // Calcular mts_rollo y toallas_rollo usando repeticiones calculada
                $largo = $datosRegistro['largo'] ?? $registro->LargoToalla ?? $registro->AnchoToalla ?? $registro->LargoCrudo ?? '';
                $mtsRollo = '';
                if (!empty($largo) && !empty($repeticionesCalculada) && is_numeric($repeticionesCalculada)) {
                    $largoNum = (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM'], '', (string) $largo);
                    $repNum = (float) $repeticionesCalculada;
                    if ($largoNum > 0 && $repNum > 0) {
                        $mtsRollo = (string) round($largoNum * $repNum / 100, 2);
                    }
                }
                $datosRegistro['mts_rollo'] = $mtsRollo;
                $datosRegistro['programa_corte_rollo'] = $mtsRollo;

                $toallasRollo = '';
                if (!empty($repeticionesCalculada) && !empty($tiras) && is_numeric($repeticionesCalculada) && is_numeric($tiras)) {
                    $repNum = (float) $repeticionesCalculada;
                    $tirasNum = (float) $tiras;
                    if ($repNum > 0 && $tirasNum > 0) {
                        $toallasRollo = (string) round($repNum * $tirasNum, 0);
                    }
                }
                $datosRegistro['toallas_rollo'] = $toallasRollo;

                // Llenar la fila en REGISTRO con las fórmulas y valores calculados
                $this->llenarFilaRegistroDesdeBD($spreadsheet, $datosRegistro, $filaRegistro);

                // Crear registro en CatCodificados con los valores de las fórmulas
                $this->crearOActualizarModeloCodificado($registro, $datosRegistro);

                $registrosParaFormato[] = [
                    'fila'  => $filaRegistro,
                    'datos' => $datosRegistro,
                    'bd'    => $registro,
                ];

                $filaRegistro++;
            }

            if (empty($registrosParaFormato)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros válidos.',
                ], 400);
            }

            // 4) Obtener hoja plantilla (primera que no sea REGISTRO)
            $hojaPlantilla = $this->obtenerHojaPlantilla($spreadsheet);
            if (!$hojaPlantilla) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró una hoja plantilla en el archivo Excel.',
                ], 400);
            }

            // 5) Eliminar todas las hojas excepto REGISTRO y la plantilla
            $this->limpiarHojasNoPlantillaNiRegistro($spreadsheet, $hojaPlantilla);

            // 6) Crear una hoja por registro
            foreach ($registrosParaFormato as $indice => $info) {
                /** @var ReqProgramaTejido $registroBD */
                $registroBD   = $info['bd'];
                $filaRegistro = $info['fila'];
                $datos        = $info['datos'];

                $tipoFormato = $this->determinarTipoFormatoDesdeBD($registroBD);

                // Crear hoja nueva copiando la plantilla (mantiene estilos e imágenes)
                $nuevaHoja = $hojaPlantilla->copy();
                $nuevaHoja->setTitle('TEMP_' . ($indice + 1));
                $spreadsheet->addSheet($nuevaHoja);

                // Nombrar hoja
                $nombreHoja = $this->generarNombreHoja($tipoFormato, (string)($datos['orden_numero'] ?? ''), $indice + 1);
                $nuevaHoja->setTitle($nombreHoja);

                // Llenar fórmulas que referencian a REGISTRO
                $this->llenarFormatoEnHoja($nuevaHoja, $filaRegistro, $tipoFormato, $indice + 1, $registroBD);
            }

            // 7) Eliminar hoja plantilla original
            $indicePlantilla = $spreadsheet->getIndex($hojaPlantilla);
            $spreadsheet->removeSheetByIndex($indicePlantilla);

            // 8) Dejar activa la primera hoja que no sea REGISTRO
            $this->activarPrimeraHojaNoRegistro($spreadsheet);

            // 9) Descargar archivo
            $nombreArchivo = 'ORDEN_CAMBIO_MODELO_' . date('Ymd_His') . '.xlsx';

            return response()->streamDownload(
                function () use ($spreadsheet) {
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->setPreCalculateFormulas(false);
                    $writer->save('php://output');
                },
                $nombreArchivo,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error al generar Excel de Orden de Cambio desde BD', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar Excel de Orden de Cambio de Modelo usando una plantilla con hoja REGISTRO ya llena.
     * - Usa plantilla fija o subida en "plantilla"
     * - Soporta tipos: 'felpa', 'smit', 'jacquard'
     */
    public function generarExcel(Request $request)
    {
        $horaActual = $this->obtenerHoraActual();

        try {
            // 1) Cargar plantilla
            $spreadsheet = $this->cargarPlantillaExcel($request);

            // 2) Asegurar hoja REGISTRO y encabezados
            $this->generarTablaRegistro($spreadsheet);

            // 3) Leer todos los registros de REGISTRO
            $registros = $this->obtenerTodosLosRegistros($spreadsheet, $horaActual);

            if (empty($registros)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros en la hoja REGISTRO.',
                ], 400);
            }

            // 4) Obtener hoja plantilla
            $hojaPlantilla = $this->obtenerHojaPlantilla($spreadsheet);
            if (!$hojaPlantilla) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró una hoja plantilla en el archivo Excel.',
                ], 400);
            }

            // 5) Eliminar todas las hojas excepto REGISTRO y la plantilla
            $this->limpiarHojasNoPlantillaNiRegistro($spreadsheet, $hojaPlantilla);

            // 6) Crear una hoja por registro
            foreach ($registros as $indice => $registroInfo) {
                $filaRegistro = $registroInfo['fila'];
                $datos        = $registroInfo['datos'];

                $tipoFormato = $this->determinarTipoFormato($datos);

                $nuevaHoja = $hojaPlantilla->copy();
                $nuevaHoja->setTitle('TEMP_' . ($indice + 1));
                $spreadsheet->addSheet($nuevaHoja);

                $nombreHoja = $this->generarNombreHoja($tipoFormato, (string)($datos['orden_numero'] ?? ''), $indice + 1);
                $nuevaHoja->setTitle($nombreHoja);

                $this->llenarFormatoEnHoja($nuevaHoja, $filaRegistro, $tipoFormato, $indice + 1);
            }

            // 7) Eliminar plantilla
            $indicePlantilla = $spreadsheet->getIndex($hojaPlantilla);
            $spreadsheet->removeSheetByIndex($indicePlantilla);

            // 8) Activar primera hoja que no sea REGISTRO
            $this->activarPrimeraHojaNoRegistro($spreadsheet);

            // 9) Descargar
            $nombreArchivo = 'ORDEN_CAMBIO_MODELO_' . date('Ymd_His') . '.xlsx';

            return response()->streamDownload(
                function () use ($spreadsheet) {
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->setPreCalculateFormulas(false);
                    $writer->save('php://output');
                },
                $nombreArchivo,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error al generar Excel de Orden de Cambio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Llenar formato (talón) en una hoja específica con fórmulas que referencian REGISTRO.
     */
    protected function llenarFormatoEnHoja(Worksheet $sheet, int $filaRegistro, string $tipoFormato, int $talonNumero, ?ReqProgramaTejido $registroBD = null): void
    {
        // Hora impresión
        $this->establecerFormulaCelda($sheet, 'D5', '=NOW()');

        // Fecha de orden
        $this->establecerFormulaCelda($sheet, 'D6', '=REGISTRO!B' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'E6', '=REGISTRO!B' . $filaRegistro);

        // Número de orden
        $this->establecerFormulaCelda($sheet, 'P4', '=REGISTRO!A' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'P5', '=REGISTRO!A' . $filaRegistro);

        // Telar
        $this->establecerFormulaCelda($sheet, 'I7', '=REGISTRO!E' . $filaRegistro);

        // Modelo
        foreach (['L7', 'M7', 'N7', 'O7', 'P7'] as $celda) {
            $this->establecerFormulaCelda($sheet, $celda, '=REGISTRO!G' . $filaRegistro);
        }

        // Departamento
        $this->establecerFormulaCelda($sheet, 'D7', '=REGISTRO!D' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'E7', '=REGISTRO!D' . $filaRegistro);

        // Prioridad
        foreach (['J6', 'K6', 'L6', 'M6', 'N6', 'O6', 'P6'] as $celda) {
            $this->establecerFormulaCelda($sheet, $celda, '=REGISTRO!F' . $filaRegistro);
        }

        // Rizo
        $this->establecerFormulaCelda($sheet, 'O9', '=REGISTRO!AF' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'P9', '=REGISTRO!AF' . $filaRegistro);

        // Pie
        $this->establecerFormulaCelda($sheet, 'O10', '=REGISTRO!AJ' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'P10', '=REGISTRO!AJ' . $filaRegistro);

        // Clave AX
        $this->establecerFormulaCelda($sheet, 'J10', '=REGISTRO!I' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'K10', '=REGISTRO!I' . $filaRegistro);

        // Calidad (M10)
        $this->establecerFormulaCelda($sheet, 'M10', '=REGISTRO!CT' . $filaRegistro);

        // Clave sistema
        foreach (['D10', 'E10', 'F10', 'G10'] as $celda) {
            $this->establecerFormulaCelda($sheet, $celda, '=REGISTRO!H' . $filaRegistro);
        }

        // Cantidad a producir
        foreach (['D8', 'E8', 'F8'] as $celda) {
            $this->establecerFormulaCelda($sheet, $celda, '=REGISTRO!O' . $filaRegistro);
        }

        // Fecha compromiso
        $this->establecerFormulaCelda($sheet, 'I8', '=REGISTRO!L' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'J8', '=REGISTRO!L' . $filaRegistro);

        // Descripción
        foreach (['L8', 'M8', 'N8', 'O8', 'P8'] as $celda) {
            $this->establecerFormulaCelda($sheet, $celda, '=REGISTRO!M' . $filaRegistro);
        }

        // Clave
        $this->establecerFormulaCelda($sheet, 'B9', '=REGISTRO!N' . $filaRegistro);

        // Tolerancia
        $this->establecerFormulaCelda($sheet, 'F9', '=REGISTRO!J' . $filaRegistro);

        // Rasurada
        $this->establecerFormulaCelda($sheet, 'I9', '=REGISTRO!AV' . $filaRegistro);

        // Cambio de repaso
        $this->establecerFormulaCelda($sheet, 'M9', '=REGISTRO!AZ' . $filaRegistro);

        // Largo / peso / luchaje
        $this->establecerFormulaCelda($sheet, 'A14', '=REGISTRO!R' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'B14', '=REGISTRO!S' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'C14', '=REGISTRO!T' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'D14', '=REGISTRO!X' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'E14', '=REGISTRO!Y' . $filaRegistro);
        // Observaciones
        $this->establecerFormulaCelda($sheet, 'O14', '=REGISTRO!BC' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'P14', '=REGISTRO!BC' . $filaRegistro);

        // Densidad
        $this->establecerFormulaCelda($sheet, 'N16', '=REGISTRO!CI' . $filaRegistro);

        // Rizo / pie / ancho
        $this->establecerFormulaCelda($sheet, 'F16', '=REGISTRO!AF' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'I16', '=REGISTRO!AJ' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'B16', '=REGISTRO!Q' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'O14', '=REGISTRO!BC' . $filaRegistro);
        // Metros de rollo
        // Primero establecer K17 para que H17 pueda referenciarlo
        $this->establecerFormulaCelda($sheet, 'K17', '=REGISTRO!AX' . $filaRegistro);
        $this->establecerFormulaCelda(
            $sheet,
            'H17',
            '=(K17*REGISTRO!R' . $filaRegistro . ')/100'
        );


        // Cenefa
        $this->establecerFormulaCelda($sheet, 'C17', '=REGISTRO!AT' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'D17', '=REGISTRO!AT' . $filaRegistro);

        // Med inicio rizo a cenefa
        $this->establecerFormulaCelda($sheet, 'C19', '=REGISTRO!AU' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'D19', '=REGISTRO!AU' . $filaRegistro);



        // Fórmula K19
        if ($tipoFormato === 'smit' || $tipoFormato === 'jacquard') {
            $this->establecerFormulaCelda($sheet, 'K19', '=K17*M19');
        } else {
            $this->establecerFormulaCelda($sheet, 'K19', '=K17*M19/2');
        }

        // Velocidad / tiras
        $this->establecerFormulaCelda($sheet, 'H19', '=REGISTRO!AC' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'M19', '=REGISTRO!AW' . $filaRegistro);

        // Establecer fórmulas en O2, S2, AW2, AX2, AY5
        $this->establecerFormulaCelda($sheet, 'O2', '=REGISTRO!O' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'S2', '=REGISTRO!S' . $filaRegistro);
        $this->establecerFormulaCelda($sheet, 'AW2', '=REGISTRO!AW' . $filaRegistro);

        // AX2 y AY5 ahora se llenan con valores directos desde BD (sin formulas)
        $repeticionesValor = $datos['repeticiones'] ?? '';
        $noMarbetesValor = $datos['no_marbetes'] ?? '';
        $sheet->setCellValue('AX2', $repeticionesValor);
        $sheet->setCellValue('AY5', $noMarbetesValor);

        // Plano de dobladillo (fórmulas)
        $this->establecerPlanoDobladilloFormulas($sheet, $filaRegistro);

        // Hilos (fórmulas)
        $this->establecerHilosFormulas($sheet, $filaRegistro);

        // Cenefa trama (fórmulas)
        $this->establecerCenefaTramaFormulas($sheet, $filaRegistro);
    }

    /**
     * Cargar plantilla Excel (archivo subido o plantilla fija ordfelpa.xlsx).
     */
    protected function cargarPlantillaExcel(Request $request)
    {
        if ($request->hasFile('plantilla')) {
            $path = $request->file('plantilla')->getRealPath();
        } else {
            $path = __DIR__ . '/ordfelpa.xlsx';
        }

        if (!file_exists($path)) {
            throw new \RuntimeException('No se encontró la plantilla Excel: ' . $path);
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $reader->setIncludeCharts(true);

        return $reader->load($path);
    }

    /**
     * Obtener todos los registros de la hoja REGISTRO (fila + datos).
     * Optimizado: se recorre la hoja una sola vez (O(n)).
     */
    protected function obtenerTodosLosRegistros($spreadsheet, string $horaActual): array
    {
        /** @var Worksheet $worksheet */
        $worksheet = $this->generarTablaRegistro($spreadsheet);

        $ultimaFila = $worksheet->getHighestRow();
        $registros  = [];

        for ($fila = 2; $fila <= $ultimaFila; $fila++) {
            $ordenNumero = $this->obtenerValorCelda($worksheet, $fila, 'A');

            if (trim($ordenNumero) === '') {
                continue;
            }

            $datos = $this->leerDatosDesdeHojaRegistro($worksheet, $fila, $horaActual);

            $registros[] = [
                'fila'  => $fila,
                'datos' => $datos,
            ];
        }

        return $registros;
    }

    /**
     * Determinar tipo de formato desde datos (modelo / nombre formato).
     */
    protected function determinarTipoFormato(array $datos): string
    {
        $modelo        = strtoupper($datos['modelo'] ?? '');
        $nombreFormato = strtoupper($datos['nombre_formato_logistico'] ?? '');

        if (stripos($modelo, 'JACQUARD') !== false || stripos($nombreFormato, 'JACQUARD') !== false) {
            return 'jacquard';
        }

        if (stripos($modelo, 'SMIT') !== false || stripos($nombreFormato, 'SMIT') !== false) {
            return 'smit';
        }

        return 'felpa';
    }

    /**
     * Generar nombre de hoja según tipo y número de orden.
     */
    protected function generarNombreHoja(string $tipoFormato, string $ordenNumero, int $indice): string
    {
        $tipoMayus   = strtoupper($tipoFormato);
        $nombreBase  = $tipoMayus . '_' . $ordenNumero;
        $nombreFinal = $nombreBase . '_' . $indice;

        if (strlen($nombreFinal) > 31) {
            $nombreFinal = substr($tipoMayus, 0, 5) . '_' . substr($ordenNumero, -10) . '_' . $indice;
        }

        return $nombreFinal;
    }


    /**
     * Leer datos de una fila de REGISTRO.
     * Optimizado: solo lee la fila indicada.
     */
    protected function leerDatosDesdeHojaRegistro(Worksheet $worksheet, int $filaNumero, string $horaActual): array
    {
        $filaAUsar = max(2, $filaNumero);

        $datos = [
            'orden_numero'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'A'),
            'fecha_orden'          => $this->formatearFecha($this->obtenerValorCelda($worksheet, $filaAUsar, 'B')),
            'fecha_cumplimiento'   => $this->formatearFecha($this->obtenerValorCelda($worksheet, $filaAUsar, 'C')),
            'departamento'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'D'),
            'telar'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'E'),
            'prioridad'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'F'),
            'modelo'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'G'),
            'clave_modelo'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'H'),
            'clave_ax'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'I'),
            'tolerancia'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'J'),
            'codigo_dibujo'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'K'),
            'fecha_compromiso'     => $this->formatearFecha($this->obtenerValorCelda($worksheet, $filaAUsar, 'L')),
            'nombre_formato_logistico' => $this->obtenerValorCelda($worksheet, $filaAUsar, 'M'),
            'clave'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'N'),
            'cantidad_producir'    => $this->obtenerValorCelda($worksheet, $filaAUsar, 'O'),
            'peine'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'P'),
            'ancho'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'Q'),
            'largo'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'R'),
            'p_crudo'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'S'),
            'luchaje'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'T'),
            'tra'                  => $this->obtenerValorCelda($worksheet, $filaAUsar, 'U'),
            'hilo_tra'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'V'),
            'obs_tra'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'W'),
            'tipo_plano'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'X'),
            'med_plano'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'Y'),
            'tipo_rizo'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'Z'),
            'alt_rizo'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AA'),
            'obs_rizo'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AB'),
            'velocidad_minima'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AC'),
            'rizo'                 => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AD'),
            'hilo_rizo'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AE'),
            'cuenta_rizo'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AF'),
            'obs_rizo_detalle'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AG'),
            'pie'                  => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AH'),
            'hilo_pie'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AI'),
            'cuenta_pie'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AJ'),
            'obs_pie'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AK'),
            'c1'                   => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AL'),
            'obs_c1'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AM'),
            'c2'                   => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AN'),
            'obs_c2'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AO'),
            'c3'                   => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AP'),
            'obs_c3'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AQ'),
            'c4'                   => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AR'),
            'obs_c4'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AS'),
            'med_cenefa'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AT'),
            'med_inicio_rizo_cenefa' => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AU'),
            'rasurada'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AV'),
            'tiras'                => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AW'),
            'repeticiones'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AX'),
            'no_marbetes'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AY'),
            'cambio_repaso'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AZ'),
            'vendedor'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BA'),
            'no_orden'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BB'),
            'observaciones'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BC'),
            'trama_ancho_peine'    => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BD'),
            'log_lucha_total'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BE'),
            'c1_trama_fondo'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BF'),
            'hilo_c1_trama'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BG'),
            'obs_c1_trama'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BH'),
            'pasadasc1'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BI'),
            'c1_pasadas'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BJ'),
            'hilo_c1_pasadas'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BK'),
            'obs_c1_pasadas'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BL'),
            'pasadasc2'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BM'),
            'c2_pasadas'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BN'),
            'hilo_c2_pasadas'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BO'),
            'obs_c2_pasadas'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BP'),
            'pasadasc3'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BQ'),
            'c3_pasadas'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BR'),
            'hilo_c3_pasadas'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BS'),
            'obs_c3_pasadas'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BT'),
            'pasadasc4'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BU'),
            'c4_pasadas'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BV'),
            'hilo_c4_pasadas'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BW'),
            'obs_c4_pasadas'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BX'),
            'pasadasc5'            => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BY'),
            'c5_pasadas'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'BZ'),
            'hilo_c5_pasadas'      => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CA'),
            'obs_c5_pasadas'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CB'),
            'total_pasadas'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CC'),
            'contraccion'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CD'),
            'tramas_cm_tejido'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CE'),
            'contrac_rizo'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CF'),
            'clasificacion_kg'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CG'),
            'kg_dia'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CH'),
            'densidad'             => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CI'),
            'pzas_dia_pasadas'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CJ'),
            'pzas_dia_formula'     => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CK'),
            'dif'                  => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CL'),
            'efic'                 => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CM'),
            'rev'                  => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CN'),
            'tiras_final'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CO'),
            'pasadastotal'         => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CQ'),
            'folio_codificacion'   => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CR'),
            'peso_rollo'           => $this->obtenerPesoRolloDesdeRegistro($worksheet, $filaAUsar),

            // Campos extra para compatibilidad con código
            'clave_sistema'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'H')
                ?: $this->obtenerValorCelda($worksheet, $filaAUsar, 'N'),
            'descripcion'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'M'),
            'rs_bata_nov'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'M'),
            'calidad'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'CT'),
            'plano_tipo'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'X'),
            'plano_largo'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'Y'),
            'plano_rizo'           => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AD'),
            'cuentas'              => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AF'),
            'minimo'               => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AC'),
            'tamano_cenefa'        => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AT'),
            'mts_rollo'            => '',
            'programa_corte_rollo' => '',
            'toallas_rollo'        => '',
            'nombre_archivo'       => $this->obtenerValorCelda($worksheet, $filaAUsar, 'M'),
            'version'              => '',
            'talon_total'          => $this->obtenerValorCelda($worksheet, $filaAUsar, 'AW') ?: '4',
            'fecha_formato'        => $this->formatearFecha($this->obtenerValorCelda($worksheet, $filaAUsar, 'B')),
            'hora_impresion'       => $horaActual,
        ];

        // Derivados
        $largo        = $datos['largo'] ?? '';
        $repeticiones = $datos['repeticiones'] ?? '';
        $tiras        = $datos['tiras'] ?? '';

        // Metros de rollo
        if (!empty($largo) && !empty($repeticiones)) {
            $largoNum = (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM'], '', (string) $largo);
            $repNum   = (float) $repeticiones;

            if ($largoNum > 0 && $repNum > 0) {
                $datos['mts_rollo'] = (string) round($largoNum * $repNum / 100, 2);
            }
        }

        // Programa corte de rollo = mts_rollo
        $datos['programa_corte_rollo'] = $datos['mts_rollo'] ?? '';

        // Toallas por rollo
        if (!empty($repeticiones) && !empty($tiras)) {
            $repNum   = (float) $repeticiones;
            $tirasNum = (float) $tiras;

            if ($repNum > 0 && $tirasNum > 0) {
                $datos['toallas_rollo'] = (string) round($repNum * $tirasNum, 0);
            }
        }

        return $datos;
    }

    /**
     * Valor de celda como string.
     */
    protected function obtenerValorCelda(Worksheet $worksheet, int $fila, string $columna): string
    {
        $valor = $worksheet->getCell($columna . $fila)->getValue();
        return $valor !== null ? (string) $valor : '';
    }

    /**
     * Formatear fecha a d-m-Y (acepta serial Excel o string).
     */
    protected function formatearFecha($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        if (is_numeric($fecha)) {
            try {
                $fechaPhp = Date::excelToDateTimeObject($fecha);
                return $fechaPhp->format('d-m-Y');
            } catch (\Exception $e) {
                return (string) $fecha;
            }
        }

        if (is_string($fecha)) {
            $timestamp = strtotime($fecha);
            if ($timestamp !== false) {
                return date('d-m-Y', $timestamp);
            }
            return $fecha;
        }

        return (string) $fecha;
    }

    /**
     * Hora actual en formato "g:i a. m./p. m."
     */
    protected function obtenerHoraActual(): string
    {
        $hora    = date('g:i');
        $periodo = date('A') === 'AM' ? 'a. m.' : 'p. m.';

        return $hora . ' ' . $periodo;
    }

    /**
     * Crear/asegurar hoja REGISTRO y sus encabezados.
     */
    protected function generarTablaRegistro($spreadsheet): Worksheet
    {
        /** @var Worksheet|null $worksheet */
        $worksheet = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (strtoupper($sheet->getTitle()) === 'REGISTRO') {
                $worksheet = $sheet;
                break;
            }
        }

        if (!$worksheet) {
            $worksheet = $spreadsheet->createSheet();
            $worksheet->setTitle('REGISTRO');
        }

        $primerEncabezado = $worksheet->getCell('A1')->getValue();
        if (!empty($primerEncabezado) && $primerEncabezado === 'Num de Orden') {
            return $worksheet;
        }

        $encabezados = [
            'A'  => 'Num de Orden',
            'B'  => 'Fecha Orden',
            'C'  => 'Fecha Cumplimiento',
            'D'  => 'Departamento',
            'E'  => 'Telar Actual',
            'F'  => 'Prioridad',
            'G'  => 'Modelo',
            'H'  => 'CLAVE MODELO',
            'I'  => 'CLAVE AX',
            'J'  => 'Tolerancia',
            'K'  => 'CODIGO DE DIBUJO',
            'L'  => 'Fecha Compromiso',
            'M'  => 'Nombre de Formato Logístico',
            'N'  => 'Clave',
            'O'  => 'Cantidad a Producir',
            'P'  => 'Peine',
            'Q'  => 'Ancho',
            'R'  => 'Largo',
            'S'  => 'P_crudo',
            'T'  => 'Luchaje',
            'U'  => 'Tra',
            'V'  => 'Hilo (Tra)',
            'W'  => 'OBS. (Tra)',
            'X'  => 'Tipo plano',
            'Y'  => 'Med plano',
            'Z'  => 'TIPO DE RIZO',
            'AA' => 'ALTURA DE RIZO',
            'AB' => 'OBS (Rizo)',
            'AC' => 'Veloc. Mínima',
            'AD' => 'Rizo',
            'AE' => 'Hilo (Rizo)',
            'AF' => 'CUENTA (Rizo)',
            'AG' => 'OBS. (Rizo)',
            'AH' => 'Pie',
            'AI' => 'Hilo (Pie)',
            'AJ' => 'CUENTA (Pie)',
            'AK' => 'OBS. (Pie)',
            'AL' => 'C1',
            'AM' => 'OBS (C1)',
            'AN' => 'C2',
            'AO' => 'OBS (C2)',
            'AP' => 'C3',
            'AQ' => 'OBS (C3)',
            'AR' => 'C4',
            'AS' => 'OBS (C4)',
            'AT' => 'Med. de Cenefa',
            'AU' => 'Med de inicio de rizo a cenefa',
            'AV' => 'RASURADA',
            'AW' => 'TIRAS',
            'AX' => 'Repeticiones p/corte',
            'AY' => 'No. De Marbetes',
            'AZ' => 'Cambio de repaso',
            'BA' => 'Vendedor',
            'BB' => 'No. Orden',
            'BC' => 'Observaciones',
            'BD' => 'TRAMA (Ancho Peine)',
            'BE' => 'LOG. DE LUCHA TOTAL',
            'BF' => 'C1 trama de Fondo',
            'BG' => 'Hilo (C1 trama)',
            'BH' => 'OBS. (C1 trama)',
            'BI' => 'PASADAS (C1)',
            'BJ' => 'C1 (PASADAS)',
            'BK' => 'Hilo (C1 PASADAS)',
            'BL' => 'OBS. (C1 PASADAS)',
            'BM' => 'PASADAS (C2)',
            'BN' => 'C2 (PASADAS)',
            'BO' => 'Hilo (C2 PASADAS)',
            'BP' => 'OBS. (C2 PASADAS)',
            'BQ' => 'PASADAS (C3)',
            'BR' => 'C3 (PASADAS)',
            'BS' => 'Hilo (C3 PASADAS)',
            'BT' => 'OBS. (C3 PASADAS)',
            'BU' => 'PASADAS (C4)',
            'BV' => 'C4 (PASADAS)',
            'BW' => 'Hilo (C4 PASADAS)',
            'BX' => 'OBS. (C4 PASADAS)',
            'BY' => 'PASADAS (C5)',
            'BZ' => 'C5 (PASADAS)',
            'CA' => 'Hilo (C5 PASADAS)',
            'CB' => 'OBS. (C5 PASADAS)',
            'CC' => 'TOTAL (PASADAS)',
            'CD' => 'Contraccion',
            'CE' => 'Tramas cm/Tejido',
            'CF' => 'Contrac Rizo',
            'CG' => 'Clasificación(KG)',
            'CH' => 'KG/Día',
            'CI' => 'Densidad',
            'CJ' => 'Pzas/Día/ pasadas',
            'CK' => 'Pzas/Día/ formula',
            'CL' => 'DIF',
            'CM' => 'EFIC.',
            'CN' => 'Rev',
            'CO' => 'TIRAS',
            'CP' => 'TIRAS (dup)',
            'CQ' => 'PASADAS (total)',
            'CR' => 'FOLIO CODIFICACION',
            'CS' => 'Peso Rollo',
            'CT' => 'Calidad',
        ];

        foreach ($encabezados as $columna => $titulo) {
            $cell = $worksheet->getCell($columna . '1');
            $cell->setValue($titulo);

            $style = $worksheet->getStyle($columna . '1');
            $style->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
            $style->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $style->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $worksheet->getColumnDimension($columna)->setWidth(18);
        }

        $worksheet->getRowDimension(1)->setRowHeight(30);
        $worksheet->freezePane('A2');

        return $worksheet;
    }

    /**
     * Establecer fórmula en celda preservando formato.
     */
    protected function establecerFormulaCelda(Worksheet $sheet, string $celda, string $formula): void
    {
        try {
            $cell       = $sheet->getCell($celda);
            $estilo     = $cell->getStyle();
            $estiloData = $estilo->exportArray();

            $cell->setValue($formula);
            $estilo->applyFromArray($estiloData);
        } catch (\Exception $e) {
            Log::warning('Error al establecer fórmula en celda', [
                'celda'   => $celda,
                'formula' => $formula,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fórmulas para plano de dobladillo.
     */
    protected function establecerPlanoDobladilloFormulas(Worksheet $sheet, int $filaRegistro): void
    {
        try {
            // Tipo plano - X
            $this->establecerFormulaCelda($sheet, 'F11', '=REGISTRO!X' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'G11', '=REGISTRO!X' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'H11', '=REGISTRO!X' . $filaRegistro);

            // Altura de rizo - AA
            $this->establecerFormulaCelda($sheet, 'F14', '=REGISTRO!AD' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'G14', '=REGISTRO!AA' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'H14', '=REGISTRO!Z' . $filaRegistro);

            // Obs rizo - AB
            $this->establecerFormulaCelda($sheet, 'F15', '=REGISTRO!AG' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'I15', '=REGISTRO!AG' . $filaRegistro);
          } catch (\Exception $e) {
            Log::warning('Error al establecer plano de dobladillo con fórmulas', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fórmulas para hilos (Rizo, Tra).
     */
    protected function establecerHilosFormulas(Worksheet $sheet, int $filaRegistro): void
    {
        try {
            // Tipo rizo - Z
            $this->establecerFormulaCelda($sheet, 'I11', '=REGISTRO!Z' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'J11', '=REGISTRO!Z' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'K11', '=REGISTRO!Z' . $filaRegistro);

            // Tra: U, hilo V, obs W
            $this->establecerFormulaCelda($sheet, 'I14', '=REGISTRO!AH' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'J14', '=REGISTRO!U' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'K14', '=REGISTRO!AL' . $filaRegistro);
        } catch (\Exception $e) {
            Log::warning('Error al establecer hilos con fórmulas', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fórmulas para cenefa trama (C1, C4).
     */
    protected function establecerCenefaTramaFormulas(Worksheet $sheet, int $filaRegistro): void
    {
        try {
            // C1 - AL, AM
            $this->establecerFormulaCelda($sheet, 'L11', '=REGISTRO!AL' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'M11', '=REGISTRO!AL' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'N11', '=REGISTRO!AL' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'O11', '=REGISTRO!AM' . $filaRegistro);

            // C4 - AR, AS
            $this->establecerFormulaCelda($sheet, 'L14', '=REGISTRO!AN' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'M14', '=REGISTRO!AP' . $filaRegistro);
            $this->establecerFormulaCelda($sheet, 'N14', '=REGISTRO!AR' . $filaRegistro);
        } catch (\Exception $e) {
            Log::warning('Error al establecer cenefa trama con fórmulas', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mapear ReqProgramaTejido a datos REGISTRO.
     * Obtiene datos de ReqModelosCodificados cuando están disponibles.
     */
    protected function mapearDatosBDaRegistro(ReqProgramaTejido $registro, string $horaActual): array
    {
        // Obtener modelo codificado si existe
        $modeloCodificado = $this->obtenerModeloCodificado($registro);

        // Fechas
        $fechaOrden = '';
        if ($registro->ProgramarProd) {
            if ($registro->ProgramarProd instanceof \Carbon\Carbon) {
                $fechaOrden = $registro->ProgramarProd->format('d-m-Y');
            } elseif (is_string($registro->ProgramarProd)) {
                try {
                    $fechaOrden = \Carbon\Carbon::parse($registro->ProgramarProd)->format('d-m-Y');
                } catch (\Exception $e) {
                    $fechaOrden = $registro->ProgramarProd;
                }
            }
        }

        $fechaCumplimiento = '';
        $fechaCompromiso   = '';
        if ($registro->FechaFinal) {
            if ($registro->FechaFinal instanceof \Carbon\Carbon) {
                $fechaCumplimiento = $registro->FechaFinal->format('d-m-Y');
                $fechaCompromiso   = $registro->FechaFinal->format('d-m-Y');
            } elseif (is_string($registro->FechaFinal)) {
                try {
                    $dt                = \Carbon\Carbon::parse($registro->FechaFinal);
                    $fechaCumplimiento = $dt->format('d-m-Y');
                    $fechaCompromiso   = $dt->format('d-m-Y');
                } catch (\Exception $e) {
                    $fechaCumplimiento = $registro->FechaFinal;
                    $fechaCompromiso   = $registro->FechaFinal;
                }
            }
        }

        // Metros de rollo y toallas por rollo
        // Usar datos de ReqProgramaTejido (NO de ReqModelosCodificados)
        $largo        = $registro->LargoToalla ?? $registro->AnchoToalla ?? $registro->LargoCrudo ?? '';
        // Repeticiones se toma desde BD
        $repeticiones = $registro->Repeticiones ?? '';
        $tiras        = $registro->NoTiras ?? 2;

        $mtsRollo = '';
        if (!empty($largo) && !empty($repeticiones)) {
            $largoNum = (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM'], '', (string) $largo);
            $repNum   = (float) $repeticiones;
            if ($largoNum > 0 && $repNum > 0) {
                $mtsRollo = (string) round($largoNum * $repNum / 100, 2);
            }
        }

        $toallasRollo = '';
        if (!empty($repeticiones) && !empty($tiras)) {
            $repNum   = (float) $repeticiones;
            $tirasNum = (float) $tiras;
            if ($repNum > 0 && $tirasNum > 0) {
                $toallasRollo = (string) round($repNum * $tirasNum, 0);
            }
        }

        $noMarbetes = '0';
        if (isset($registro->SaldoMarbete) && is_numeric($registro->SaldoMarbete)) {
            $noMarbetes = (string) (int) $registro->SaldoMarbete;
        }

        return [
            'orden_numero'         => $registro->NoProduccion ?? '',
            'fecha_orden'          => $fechaOrden,
            'fecha_cumplimiento'   => $fechaCumplimiento,
            'departamento'         => $registro->SalonTejidoId ?? '',
            'telar'                => $registro->NoTelarId ?? '',
            'prioridad'            => $modeloCodificado?->Prioridad ?? $registro->Prioridad ?? '',
            'modelo'               => $modeloCodificado?->Nombre ?? $registro->NombreProducto ?? $registro->NombreProyecto ?? '',
            'clave_modelo'         => $modeloCodificado?->ClaveModelo ?? $registro->TamanoClave ?? '',
            'clave_ax'             => $modeloCodificado?->ItemId ?? $registro->ItemId ?? '',
            'tolerancia'           => $modeloCodificado?->Tolerancia ?? $registro->Tolerancia ?? '',
            'codigo_dibujo'        => $modeloCodificado?->CodigoDibujo ?? $registro->CodigoDibujo ?? '',
            'fecha_compromiso'     => $fechaCompromiso,
            'nombre_formato_logistico' => $modeloCodificado?->NombreProyecto ?? $registro->NombreProyecto ?? '',
            'clave'                => $modeloCodificado?->Clave ?? '',
            'cantidad_producir'    => $registro->SaldoPedido ?? '',
            'peine'                => $modeloCodificado?->Peine ?? $registro->Peine ?? '',
            'ancho'                => ($modeloCodificado?->AnchoToalla ?? $registro->Ancho) ? (string) ($modeloCodificado?->AnchoToalla ?? $registro->Ancho) : '',
            'largo'                => $modeloCodificado?->LargoToalla ?? $registro->LargoToalla ?? '',
            'p_crudo'              => $modeloCodificado?->PesoCrudo ?? $registro->PesoCrudo ?? '',
            'luchaje'              => $modeloCodificado?->Luchaje ?? $registro->Luchaje ?? '',
            'tra'                  => $modeloCodificado?->CalibreTrama ?? $registro->Tra ?? '',
            'hilo_tra'             => $modeloCodificado?->FibraId ?? $registro->FibraTrama ?? '',
            'obs_tra'              => $modeloCodificado?->Obs ?? '',
            'tipo_plano'           => '',
            'med_plano'            => $modeloCodificado?->MedidaPlano ?? $registro->MedidaPlano ?? '',
            'tipo_rizo'            => $modeloCodificado?->TipoRizo ?? $registro->TipoRizo ?? '',
            'alt_rizo'             => $modeloCodificado?->AlturaRizo ?? '',
            'obs_rizo'             => $modeloCodificado?->Obs ?? '',
            'velocidad_minima'     => $modeloCodificado?->VelocidadSTD ?? $registro->VelocidadSTD ?? '',
            'rizo'                 => $modeloCodificado?->CalibreRizo ?? $registro->CalibreRizo ?? '',
            'hilo_rizo'            => $modeloCodificado?->FibraRizo ?? $registro->FibraRizo ?? '',
            'cuenta_rizo'          => $modeloCodificado?->CuentaRizo ?? $registro->CuentaRizo ?? '',
            'obs_rizo_detalle'     => $modeloCodificado?->Obs ?? '',
            'pie'                  => $modeloCodificado?->CalibrePie ?? $registro->CalibrePie ?? '',
            'hilo_pie'             => $modeloCodificado?->FibraPie ?? $registro->FibraPie ?? '',
            'cuenta_pie'           => $modeloCodificado?->CuentaPie ?? $registro->CuentaPie ?? '',
            'obs_pie'              => $modeloCodificado?->Obs ?? '',
            'c1'                   => $modeloCodificado?->CalibreComb1 ?? $registro->CalibreComb1 ?? '',
            'obs_c1'               => $modeloCodificado?->Obs1 ?? '',
            'c2'                   => $modeloCodificado?->CalibreComb2 ?? $registro->CalibreComb2 ?? '',
            'obs_c2'               => $modeloCodificado?->Obs2 ?? '',
            'c3'                   => $modeloCodificado?->CalibreComb3 ?? $registro->CalibreComb3 ?? '',
            'obs_c3'               => $modeloCodificado?->Obs3 ?? '',
            'c4'                   => $modeloCodificado?->CalibreComb4 ?? $registro->CalibreComb4 ?? '',
            'obs_c4'               => $modeloCodificado?->Obs4 ?? '',
            'med_cenefa'           => $modeloCodificado?->MedidaCenefa ?? '',
            'med_inicio_rizo_cenefa' => $modeloCodificado?->MedIniRizoCenefa ?? '',
            'rasurada'             => $registro->Rasurado ?? 'NO',
            'tiras'                => $tiras,
            'repeticiones'         => $repeticiones,
            'no_marbetes'          => $noMarbetes,
            'cambio_repaso'        => $modeloCodificado?->CambioRepaso ?? $registro->CambioHilo ?? 'NO',
            'vendedor'             => $modeloCodificado?->Vendedor ?? '',
            'no_orden'             => $registro->NoProduccion ?? '',
            'observaciones'        => $modeloCodificado?->Obs5 ?? $registro->Observaciones ?? '',
            'trama_ancho_peine'    => $modeloCodificado?->AnchoPeineTrama ?? $registro->Ancho ?? '',
            'log_lucha_total'      => $modeloCodificado?->LogLuchaTotal ?? '',
            'c1_trama_fondo'       => $modeloCodificado?->CalTramaFondoC1 ?? '',
            'hilo_c1_trama'        => $modeloCodificado?->FibraTramaFondoC1 ?? '',
            'obs_c1_trama'         => '',
            'pasadasc1'            => $modeloCodificado?->PasadasComb1 ?? $registro->PasadasComb1 ?? '',
            'c1_pasadas'           => $modeloCodificado?->CalibreComb1 ?? $registro->CalibreComb1 ?? '',
            'hilo_c1_pasadas'      => $modeloCodificado?->FibraComb1 ?? $registro->FibraComb1 ?? '',
            'obs_c1_pasadas'       => $modeloCodificado?->Obs1 ?? '',
            'pasadasc2'            => $modeloCodificado?->PasadasComb2 ?? $registro->PasadasComb2 ?? '',
            'c2_pasadas'           => $modeloCodificado?->CalibreComb2 ?? $registro->CalibreComb2 ?? '',
            'hilo_c2_pasadas'      => $modeloCodificado?->FibraComb2 ?? $registro->FibraComb2 ?? '',
            'obs_c2_pasadas'       => $modeloCodificado?->Obs2 ?? '',
            'pasadasc3'            => $modeloCodificado?->PasadasComb3 ?? $registro->PasadasComb3 ?? '',
            'c3_pasadas'           => $modeloCodificado?->CalibreComb3 ?? $registro->CalibreComb3 ?? '',
            'hilo_c3_pasadas'      => $modeloCodificado?->FibraComb3 ?? $registro->FibraComb3 ?? '',
            'obs_c3_pasadas'       => $modeloCodificado?->Obs3 ?? '',
            'pasadasc4'            => $modeloCodificado?->PasadasComb4 ?? $registro->PasadasComb4 ?? '',
            'c4_pasadas'           => $modeloCodificado?->CalibreComb4 ?? $registro->CalibreComb4 ?? '',
            'hilo_c4_pasadas'      => $modeloCodificado?->FibraComb4 ?? $registro->FibraComb4 ?? '',
            'obs_c4_pasadas'       => $modeloCodificado?->Obs4 ?? '',
            'pasadasc5'            => $modeloCodificado?->PasadasComb5 ?? $registro->PasadasComb5 ?? '',
            'c5_pasadas'           => $modeloCodificado?->CalibreComb5 ?? $registro->CalibreComb5 ?? '',
            'hilo_c5_pasadas'      => $modeloCodificado?->FibraComb5 ?? $registro->FibraComb5 ?? '',
            'obs_c5_pasadas'       => $modeloCodificado?->Obs5 ?? '',
            'total_pasadas'        => $modeloCodificado?->Total ?? '',
            'contraccion'          => $modeloCodificado?->Contraccion ?? '',
            'tramas_cm_tejido'     => $modeloCodificado?->TramasCMTejido ?? '',
            'contrac_rizo'         => $modeloCodificado?->ContracRizo ?? '',
            'clasificacion_kg'     => $modeloCodificado?->ClasificacionKG ?? '',
            'kg_dia'               => $modeloCodificado?->KGDia ?? '',
            'densidad'             => $modeloCodificado?->Densidad ?? $registro->PesoGRM2 ?? '',
            'pzas_dia_pasadas'     => $modeloCodificado?->PzasDiaPasadas ?? '',
            'pzas_dia_formula'     => $modeloCodificado?->PzasDiaFormula ?? '',
            'dif'                  => $modeloCodificado?->DIF ?? '',
            'efic'                 => $modeloCodificado?->EFIC ?? $registro->EficienciaSTD ?? '',
            'rev'                  => $modeloCodificado?->Rev ?? '',
            'tiras_final'          => $tiras,
            'pasadastotal'         => $modeloCodificado?->PASADAS ?? '',
            'folio_codificacion'   => $registro->NoProduccion ?? '',
            'peso_rollo'           => $this->obtenerPesoRolloDesdeBD($registro) ?? 0,
            'calidad'              => $registro->CategoriaCalidad ?? $registro->CatCalidad ?? '',
            'hora_impresion'       => $horaActual,
            'mts_rollo'            => $mtsRollo,
            'programa_corte_rollo' => $mtsRollo,
            'toallas_rollo'        => $toallasRollo,
        ];
    }

    /**
     * Obtener PesoRollo desde REGISTRO, si está vacío calcularlo desde BD usando ItemId.
     */
    protected function obtenerPesoRolloDesdeRegistro(Worksheet $worksheet, int $fila): ?float
    {
        try {
            // Primero intentar leer desde la columna CS
            $pesoRollo = $this->obtenerValorCelda($worksheet, $fila, 'CS');

            if (!empty($pesoRollo) && is_numeric($pesoRollo)) {
                return (float) $pesoRollo;
            }

            // Si está vacío, calcularlo desde BD usando ItemId (columna I)
            $itemId = trim($this->obtenerValorCelda($worksheet, $fila, 'I'));

            if (!empty($itemId)) {
                $pesoRollo = ReqPesosRollosTejido::where('ItemId', $itemId)
                    ->orderBy('Id')
                    ->first();

                if ($pesoRollo && $pesoRollo->PesoRollo) {
                    return (float) $pesoRollo->PesoRollo;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtener PesoRollo desde ReqPesosRolloTejido usando ItemId e InventSizeId desde datos de BD.
     */
    protected function obtenerPesoRolloDesdeBD(ReqProgramaTejido $registro): ?float
    {
        try {
            $itemId = trim($registro->ItemId ?? '');
            $inventSizeId = trim($registro->InventSizeId ?? '');

            // Buscar por ItemId e InventSizeId si ambos están disponibles
            if (!empty($itemId) && !empty($inventSizeId)) {
                $pesoRollo = ReqPesosRollosTejido::where('ItemId', $itemId)
                    ->where('InventSizeId', $inventSizeId)
                    ->first();

                if ($pesoRollo && $pesoRollo->PesoRollo !== null) {
                    return (float) $pesoRollo->PesoRollo;
                }
            }

            // Si no se encuentra con ambos, buscar solo por ItemId
            if (!empty($itemId)) {
                $pesoRollo = ReqPesosRollosTejido::where('ItemId', $itemId)
                    ->orderBy('Id')
                    ->first();
                if ($pesoRollo && $pesoRollo->PesoRollo !== null) {
                    return (float) $pesoRollo->PesoRollo;
                }
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener PesoRollo desde BD', [
                'item_id' => $registro->ItemId ?? '',
                'invent_size_id' => $registro->InventSizeId ?? '',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtener modelo codificado desde ReqModelosCodificados usando TamanoClave y SalonTejidoId (Departamento).
     * Busca primero por TamanoClave + SalonTejidoId, luego solo por SalonTejidoId, y finalmente solo por TamanoClave.
     * Retorna un objeto vacío si no se encuentra.
     */
    protected function obtenerModeloCodificado(ReqProgramaTejido $registro): ?ReqModelosCodificados
    {
        try {
            $tamanoClave = $registro->TamanoClave ?? '';
            $salonTejidoId = $registro->SalonTejidoId ?? '';

            if (empty($tamanoClave) && empty($salonTejidoId)) {
                return null;
            }

            $cacheKey = strtoupper(trim((string)$tamanoClave)) . '|' . strtoupper(trim((string)$salonTejidoId));
            if (array_key_exists($cacheKey, self::$modeloCodificadoCache)) {
                return self::$modeloCodificadoCache[$cacheKey];
            }

            $modelo = null;

            // 1. Buscar por TamanoClave + SalonTejidoId (Departamento)
            if (!empty($tamanoClave) && !empty($salonTejidoId)) {
                $query = ReqModelosCodificados::query()
                    ->where('TamanoClave', $tamanoClave)
                    ->where('SalonTejidoId', $salonTejidoId)
                    ->orderByDesc('FechaTejido');
                $modelo = $query->first();
            }

            // 2. Si no se encuentra, buscar solo por SalonTejidoId (Departamento)
            if (!$modelo && !empty($salonTejidoId)) {
                $query = ReqModelosCodificados::query()
                    ->where('SalonTejidoId', $salonTejidoId)
                    ->orderByDesc('FechaTejido');
                $modelo = $query->first();
            }

            // 3. Si no se encuentra, buscar solo por TamanoClave
            if (!$modelo && !empty($tamanoClave)) {
                $query = ReqModelosCodificados::query()
                    ->where('TamanoClave', $tamanoClave)
                    ->orderByDesc('FechaTejido');
                $modelo = $query->first();
            }

            self::$modeloCodificadoCache[$cacheKey] = $modelo;
            return $modelo;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Crear nuevo registro en CatCodificados con los datos del registro seleccionado.
     * Solo crea nuevos registros, no actualiza existentes.
     */
    protected function crearOActualizarModeloCodificado(ReqProgramaTejido $registro, array $datosRegistro): void
    {
        try {
            $tamanoClave = $registro->TamanoClave ?? '';
            $salonTejidoId = $registro->SalonTejidoId ?? '';

            if (empty($tamanoClave) && empty($salonTejidoId)) {
                return;
            }

            // Obtener modelo codificado para campos que no están en ReqProgramaTejido
            $modeloCodificado = $this->obtenerModeloCodificado($registro);

            // Buscar registro existente primero
            $noProduccion = $registro->NoProduccion ?? '';
            $noTelarId = $registro->NoTelarId ?? '';

            $catCodificado = CatCodificados::query()
                ->where('OrdenTejido', $noProduccion)
                ->where('TelarId', $noTelarId)
                ->first();

            // Si no existe, crear nuevo registro
            if (!$catCodificado) {
                $catCodificado = new CatCodificados();
            }

            // Mapear datos desde ReqProgramaTejido y datosRegistro a CatCodificados
            $catCodificado->OrdenTejido = $registro->NoProduccion ?? null;

            // Fechas - usar setAttribute para que Laravel maneje el cast correctamente
            if ($registro->ProgramarProd) {
                $fechaTejido = $registro->ProgramarProd instanceof \Carbon\Carbon
                    ? $registro->ProgramarProd
                    : \Carbon\Carbon::parse($registro->ProgramarProd);
                $catCodificado->setAttribute('FechaTejido', $fechaTejido);
            } else {
                $catCodificado->setAttribute('FechaTejido', now());
            }

            if ($registro->FechaFinal) {
                $fechaFinal = $registro->FechaFinal instanceof \Carbon\Carbon
                    ? $registro->FechaFinal
                    : \Carbon\Carbon::parse($registro->FechaFinal);
                $catCodificado->setAttribute('FechaCumplimiento', $fechaFinal);
                $catCodificado->setAttribute('FechaCompromiso', $fechaFinal);
            } else {
                $catCodificado->FechaCumplimiento = null;
                $catCodificado->FechaCompromiso = null;
            }



            $catCodificado->Departamento = $salonTejidoId;
            $catCodificado->TelarId = $registro->NoTelarId ?? null;
            // Prioridad viene del formulario (puede ser del registro anterior o editada por el usuario)
            $catCodificado->Prioridad = $registro->Prioridad ?? null;
            $catCodificado->Nombre = $registro->NombreProducto ?? $registro->NombreProyecto ?? null;
            $catCodificado->ClaveModelo = $tamanoClave;
            $catCodificado->ItemId = $registro->ItemId ?? null;
            $catCodificado->InventSizeId = $registro->InventSizeId ?? null;
            $catCodificado->Tolerancia = $modeloCodificado?->Tolerancia ?? $registro->Tolerancia ?? null;
            $catCodificado->CodigoDibujo = $modeloCodificado?->CodigoDibujo ?? $registro->CodigoDibujo ?? null;
            $catCodificado->FlogsId = $registro->FlogsId ?? null;
            $catCodificado->NombreProyecto = $registro->NombreProyecto ?? null;
            $catCodificado->Clave = $tamanoClave;
            $catCodificado->Cantidad = $registro->SaldoPedido ?? null;
            $catCodificado->Peine = $registro->Peine ?? null;

            // Ancho desde ancho (quitar " Cms." si existe)
            $ancho = $datosRegistro['ancho'] ?? $registro->Ancho ?? null;
            if ($ancho) {
                $anchoNum = (int) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $ancho);
                $catCodificado->Ancho = $anchoNum ?: null;
            }

            // Largo desde LargoCrudo según mapeo
            $largo = $registro->LargoCrudo ?? null;
            if ($largo) {
                $largoNum = is_numeric($largo) ? (int) $largo : (int) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);
                $catCodificado->Largo = $largoNum ?: null;
            }

            $catCodificado->P_crudo = $registro->PesoCrudo ?? null;
            $catCodificado->Luchaje = $registro->Luchaje ?? null;
            $catCodificado->Tra = $registro->CalibreTrama ?? null;
            $catCodificado->CalibreTrama2 = $registro->CalibreTrama ?? null;
            // Campos de color de trama - usar ReqModelosCodificados si no está en ReqProgramaTejido
            $catCodificado->CodColorTrama = $registro->CodColorTrama ?? $modeloCodificado?->CodColorTrama ?? null;
            $catCodificado->ColorTrama = $registro->ColorTrama ?? null;
            $catCodificado->FibraId = $registro->FibraTrama ?? null;
            $catCodificado->DobladilloId = $modeloCodificado?->DobladilloId ?? $registro->DobladilloId ?? null;
            $catCodificado->MedidaPlano = $registro->MedidaPlano ?? null;
            $catCodificado->TipoRizo = $registro->TipoRizo ?? null;
            $catCodificado->AlturaRizo = $modeloCodificado?->AlturaRizo ?? null;
            $catCodificado->Obs = $modeloCodificado?->Obs ?? $registro->Observaciones ?? null;
            $catCodificado->VelocidadSTD = $registro->VelocidadSTD ?? null;
            $catCodificado->EficienciaSTD = $registro->EficienciaSTD ?? null;
            $catCodificado->CalibreRizo = $registro->CalibreRizo ?? null;
            $catCodificado->CalibreRizo2 = $registro->CalibreRizo2 ?? null;
            $catCodificado->CuentaRizo = $registro->CuentaRizo ?? null;
            $catCodificado->FibraRizo = $registro->FibraRizo ?? null;
            $catCodificado->CalibrePie = $registro->CalibrePie ?? null;
            $catCodificado->CalibrePie2 = $registro->CalibrePie2 ?? null;
            $catCodificado->CuentaPie = $registro->CuentaPie ?? null;
            $catCodificado->FibraPie = $registro->FibraPie ?? null;
            $catCodificado->Comb1 = $registro->CalibreComb1 ?? null;
            $catCodificado->Obs1 = null;
            $catCodificado->Comb2 = $registro->CalibreComb2 ?? null;
            $catCodificado->Obs2 = null;
            $catCodificado->Comb3 = $registro->CalibreComb3 ?? null;
            $catCodificado->Obs3 = null;
            $catCodificado->Comb4 = $registro->CalibreComb4 ?? null;
            $catCodificado->Obs4 = null;
            $catCodificado->MedidaCenefa = null;
            $catCodificado->MedIniRizoCenefa = null;
            $catCodificado->Razurada = $registro->Rasurado ?? 'NO';
            $catCodificado->NoTiras = $datosRegistro['tiras'] ?? $registro->NoTiras ?? null;

            // Repeticiones viene de la fórmula AX (TRUNCAR((41.5/S)/AW*1000)) calculada en el Excel
            // Debe venir de $datosRegistro que ya tiene el valor calculado de la fórmula
            $repeticiones = isset($datosRegistro['repeticiones']) && is_numeric($datosRegistro['repeticiones'])
                ? (int) $datosRegistro['repeticiones']
                : null;
            $catCodificado->Repeticiones = $repeticiones;

            // NoMarbete viene de la fórmula AY (TRUNCAR(O/AW/AX)) calculada en el Excel
            // Debe venir de $datosRegistro que ya tiene el valor calculado de la fórmula
            $noMarbete = isset($datosRegistro['no_marbetes']) && is_numeric($datosRegistro['no_marbetes'])
                ? (float) $datosRegistro['no_marbetes']
                : null;
            $catCodificado->NoMarbete = $noMarbete;
            $catCodificado->CambioRepaso = $registro->CambioHilo ?? 'NO';
            $catCodificado->Vendedor = null;
            $catCodificado->NoOrden = $registro->NoProduccion ?? null;
            $catCodificado->Obs5 = $modeloCodificado?->Obs5 ??
            $catCodificado->TramaAnchoPeine = $registro->Ancho ?? null;
            $catCodificado->LogLuchaTotal = $modeloCodificado?->LogLuchaTotal ?? null;
            $catCodificado->CalTramaFondoC1 = $modeloCodificado?->CalTramaFondoC1 ?? null;
            $catCodificado->CalTramaFondoC12 = $modeloCodificado?->CalTramaFondoC12 ?? null;
            $catCodificado->FibraTramaFondoC1 = $modeloCodificado?->FibraTramaFondoC1 ?? null;
            // PasadasTrama desde ReqProgramaTejido según mapeo de imagen
            $catCodificado->PasadasTramaFondoC1 = $registro->PasadasTrama ?? null;

            // Campos de combinaciones según mapeo
            // Combinación 1 - asignar directamente desde ReqProgramaTejido
            $catCodificado->CalibreComb1 = $registro->CalibreComb1 ?? null;
            $catCodificado->CalibreComb12 = $registro->CalibreComb12 ?? null;
            $catCodificado->FibraComb1 = $registro->FibraComb1;
            $catCodificado->CodColorC1 = $registro->CodColorComb1;
            $catCodificado->NomColorC1 = $registro->NombreCC1;
            $catCodificado->PasadasComb1 = $registro->PasadasComb1 ?? null;

            // Combinación 2 - asignar directamente desde ReqProgramaTejido
            $catCodificado->CalibreComb2 = $registro->CalibreComb2 ?? null;
            $catCodificado->CalibreComb22 = $registro->CalibreComb22 ?? null;
            $catCodificado->FibraComb2 = $registro->FibraComb2;
            $catCodificado->CodColorC2 = $registro->CodColorComb2;
            $catCodificado->NomColorC2 = $registro->NombreCC2;
            $catCodificado->PasadasComb2 = $registro->PasadasComb2 ?? null;

            // Combinación 3 - asignar directamente desde ReqProgramaTejido
            $catCodificado->CalibreComb3 = $registro->CalibreComb3 ?? null;
            $catCodificado->CalibreComb32 = $registro->CalibreComb32 ?? null;
            $catCodificado->FibraComb3 = $registro->FibraComb3;
            $catCodificado->CodColorC3 = $registro->CodColorComb3;
            $catCodificado->NomColorC3 = $registro->NombreCC3;
            $catCodificado->PasadasComb3 = $registro->PasadasComb3 ?? null;

            // Combinación 4 - asignar directamente desde ReqProgramaTejido
            $catCodificado->CalibreComb4 = $registro->CalibreComb4 ?? null;
            $catCodificado->CalibreComb42 = $registro->CalibreComb42 ?? null;
            $catCodificado->FibraComb4 = $registro->FibraComb4;
            $catCodificado->CodColorC4 = $registro->CodColorComb4;
            $catCodificado->NomColorC4 = $registro->NombreCC4;
            $catCodificado->PasadasComb4 = $registro->PasadasComb4 ?? null;

            // Combinación 5 - asignar directamente desde ReqProgramaTejido
            $catCodificado->CalibreComb5 = $registro->CalibreComb5 ?? null;
            $catCodificado->CalibreComb52 = $registro->CalibreComb52 ?? null;
            $catCodificado->FibraComb5 = $registro->FibraComb5;
            $catCodificado->CodColorC5 = $registro->CodColorComb5;
            $catCodificado->NomColorC5 = $registro->NombreCC5;
            $catCodificado->PasadasComb5 = $registro->PasadasComb5 ?? null;

            $catCodificado->Total = null; // No disponible en ReqProgramaTejido
            $catCodificado->Densidad = $registro->PesoGRM2 ?? null;
            $catCodificado->Pedido = $registro->TotalPedido ?? null;
            $catCodificado->Produccion = $registro->Produccion ?? null;
            $catCodificado->Saldos = $registro->SaldoPedido ?? null;
            $catCodificado->OrdCompartida = $registro->OrdCompartida ?? null;
            $catCodificado->OrdCompartidaLider = $registro->OrdCompartidaLider ?? null;

            // Campos de rollos - usar valores de ReqProgramaTejido si están disponibles, sino de datosRegistro
            $catCodificado->MtsRollo = $registro->MtsRollo ?? (isset($datosRegistro['mts_rollo']) && is_numeric($datosRegistro['mts_rollo']) ? (float) $datosRegistro['mts_rollo'] : null);
            $catCodificado->PzasRollo = $registro->PzasRollo ?? (isset($datosRegistro['toallas_rollo']) && is_numeric($datosRegistro['toallas_rollo']) ? (float) $datosRegistro['toallas_rollo'] : null);
            $catCodificado->TotalRollos = $registro->TotalRollos !== null ? (float)$registro->TotalRollos : null;
            $catCodificado->TotalPzas = $registro->TotalPzas !== null ? (float)$registro->TotalPzas : null;
            $catCodificado->Repeticiones = $registro->Repeticiones ?? null;
            $catCodificado->NoMarbete = $registro->SaldoMarbete ?? null; // SaldoMarbete en ReqProgramaTejido = NoMarbete en CatCodificados
            $catCodificado->CombinaTram = $registro->CombinaTram ?? null;
            $catCodificado->BomId = $registro->BomId ?? null;
            $catCodificado->BomName = $registro->BomName ?? null;
            $catCodificado->CreaProd = $registro->CreaProd ?? 1;
            $catCodificado->HiloAX = $registro->HiloAX ?? null;
            $catCodificado->ActualizaLmat = $registro->ActualizaLmat ?? 0;
            $catCodificado->CategoriaCalidad = $registro->CategoriaCalidad ?? null;
            $catCodificado->CustName = $registro->CustName ?? null;
            $catCodificado->PesoMuestra = $registro->PesoMuestra ?? null;
            $catCodificado->OrdPrincipal = $registro->OrdPrincipal ?? null;

            // Densidad: usar del registro si está disponible, sino calcular o usar PesoGRM2
            $catCodificado->Densidad = $registro->Densidad ?? null;

            // Campos de auditoría
            $usuario = Auth::check() && Auth::user() ? (Auth::user()->nombre ?? Auth::user()->numero_empleado ?? 'Sistema') : 'Sistema';
            $fechaActual = now();

            // Si es un registro nuevo, establecer campos de creación
            $esNuevo = !$catCodificado->exists;
            if ($esNuevo) {
                $catCodificado->setAttribute('FechaCreacion', $fechaActual);
                $catCodificado->HoraCreacion = $fechaActual->format('H:i:s');
                $catCodificado->UsuarioCrea = $usuario;
            } elseif (empty($catCodificado->UsuarioCrea)) {
                // Si el registro existe pero no tiene UsuarioCrea, asignarlo
                $catCodificado->UsuarioCrea = $usuario;
            }

            // Siempre actualizar campos de modificación
            $catCodificado->setAttribute('FechaModificacion', $fechaActual);
            $catCodificado->HoraModificacion = $fechaActual->format('H:i:s');
            $catCodificado->UsuarioModifica = $usuario;

            $catCodificado->save();

            // Actualizar ReqModelosCodificados con OrdPrincipal y PesoMuestra
            $this->actualizarReqModelosCodificadosDesdeLiberacion($registro);

        } catch (\Exception $e) {
            Log::error('Error al crear registro en CatCodificados', [
                'tamano_clave' => $registro->TamanoClave ?? '',
                'salon_tejido_id' => $registro->SalonTejidoId ?? '',
                'orden_tejido' => $registro->NoProduccion ?? '',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Actualiza ReqModelosCodificados con OrdPrincipal y PesoMuestra desde ReqProgramaTejido.
     * Busca por TamanoClave, ClaveModelo o OrdenTejido (NoProduccion).
     *
     * @param ReqProgramaTejido $registro
     * @return void
     */
    protected function actualizarReqModelosCodificadosDesdeLiberacion(ReqProgramaTejido $registro): void
    {
        try {
            $tamanoClave = trim((string) ($registro->TamanoClave ?? ''));
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $salonTejidoId = trim((string) ($registro->SalonTejidoId ?? ''));

            if (empty($tamanoClave) && empty($noProduccion)) {
                return;
            }

            $query = ReqModelosCodificados::query();

            // Buscar por OrdenTejido (NoProduccion) si está disponible
            if (!empty($noProduccion)) {
                $query->where('OrdenTejido', $noProduccion);
            } elseif (!empty($tamanoClave)) {
                // Si no hay OrdenTejido, buscar por TamanoClave
                $query->where('TamanoClave', $tamanoClave);
                // Si hay SalonTejidoId, filtrar por él también
                if (!empty($salonTejidoId)) {
                    $query->where('SalonTejidoId', $salonTejidoId);
                }
            } else {
                return;
            }

            $modelos = $query->get();

            if ($modelos->isEmpty()) {
                return;
            }

            // Obtener valores a actualizar
            $pesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
            $ordPrincipalRaw = $registro->OrdPrincipal;
            $ordPrincipal = null;
            if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                // Si es numérico, convertir a int; si no, intentar parsearlo
                if (is_numeric($ordPrincipalStr)) {
                    $ordPrincipal = (int) $ordPrincipalStr;
                } elseif ($ordPrincipalStr !== '') {
                    // Si no es numérico pero tiene valor, intentar guardarlo (puede fallar si la columna es INT)
                    $ordPrincipal = $ordPrincipalStr;
                }
            }

            // Actualizar todos los registros encontrados
            foreach ($modelos as $modelo) {
                $updated = false;
                if ($pesoMuestra !== null) {
                    $modelo->PesoMuestra = $pesoMuestra;
                    $updated = true;
                }
                if ($ordPrincipal !== null) {
                    $modelo->OrdPrincipal = $ordPrincipal;
                    $updated = true;
                }
                if ($updated) {
                    $modelo->save();
                }
            }
        } catch (\Throwable $e) {
            // Loggear error pero no fallar la operación principal
            Log::warning('Error al actualizar ReqModelosCodificados desde liberación (OrdenDeCambioFelpa)', [
                'no_produccion' => $registro->NoProduccion ?? null,
                'tamano_clave' => $registro->TamanoClave ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Llenar fila de REGISTRO con datos de BD.
     */
    protected function llenarFilaRegistroDesdeBD($spreadsheet, array $datos, int $fila): void
    {
        /** @var Worksheet|null $worksheet */
        $worksheet = null;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (strtoupper($sheet->getTitle()) === 'REGISTRO') {
                $worksheet = $sheet;
                break;
            }
        }

        if (!$worksheet) {
            return;
        }

        $mapeo = [
            'A'  => 'orden_numero',
            'B'  => 'fecha_orden',
            'C'  => 'fecha_cumplimiento',
            'D'  => 'departamento',
            'E'  => 'telar',
            'F'  => 'prioridad',
            'G'  => 'modelo',
            'H'  => 'clave_modelo',
            'I'  => 'clave_ax',
            'J'  => 'tolerancia',
            'K'  => 'codigo_dibujo',
            'L'  => 'fecha_compromiso',
            'M'  => 'nombre_formato_logistico',
            'N'  => 'clave',
            'O'  => 'cantidad_producir',
            'P'  => 'peine',
            'Q'  => 'ancho',
            'R'  => 'largo',
            'S'  => 'p_crudo',
            'T'  => 'luchaje',
            'U'  => 'tra',
            'V'  => 'hilo_tra',
            'W'  => 'obs_tra',
            'X'  => 'tipo_plano',
            'Y'  => 'med_plano',
            'Z'  => 'tipo_rizo',
            'AA' => 'alt_rizo',
            'AB' => 'obs_rizo',
            'AC' => 'velocidad_minima',
            'AD' => 'rizo',
            'AE' => 'hilo_rizo',
            'AF' => 'cuenta_rizo',
            'AG' => 'obs_rizo_detalle',
            'AH' => 'pie',
            'AI' => 'hilo_pie',
            'AJ' => 'cuenta_pie',
            'AK' => 'obs_pie',
            'AL' => 'c1',
            'AM' => 'obs_c1',
            'AN' => 'c2',
            'AO' => 'obs_c2',
            'AP' => 'c3',
            'AQ' => 'obs_c3',
            'AR' => 'c4',
            'AS' => 'obs_c4',
            'AT' => 'med_cenefa',
            'AU' => 'med_inicio_rizo_cenefa',
            'AV' => 'rasurada',
            'AW' => 'tiras',
            'AX' => 'repeticiones',
            'AY' => 'no_marbetes',
            'AZ' => 'cambio_repaso',
            'BA' => 'vendedor',
            'BB' => 'no_orden',
            'BC' => 'observaciones',
            'BD' => 'trama_ancho_peine',
            'BE' => 'log_lucha_total',
            'BF' => 'c1_trama_fondo',
            'BG' => 'hilo_c1_trama',
            'BH' => 'obs_c1_trama',
            'BI' => 'pasadasc1',
            'BJ' => 'c1_pasadas',
            'BK' => 'hilo_c1_pasadas',
            'BL' => 'obs_c1_pasadas',
            'BM' => 'pasadasc2',
            'BN' => 'c2_pasadas',
            'BO' => 'hilo_c2_pasadas',
            'BP' => 'obs_c2_pasadas',
            'BQ' => 'pasadasc3',
            'BR' => 'c3_pasadas',
            'BS' => 'hilo_c3_pasadas',
            'BT' => 'obs_c3_pasadas',
            'BU' => 'pasadasc4',
            'BV' => 'c4_pasadas',
            'BW' => 'hilo_c4_pasadas',
            'BX' => 'obs_c4_pasadas',
            'BY' => 'pasadasc5',
            'BZ' => 'c5_pasadas',
            'CA' => 'hilo_c5_pasadas',
            'CB' => 'obs_c5_pasadas',
            'CC' => 'total_pasadas',
            'CD' => 'contraccion',
            'CE' => 'tramas_cm_tejido',
            'CF' => 'contrac_rizo',
            'CG' => 'clasificacion_kg',
            'CH' => 'kg_dia',
            'CI' => 'densidad',
            'CJ' => 'pzas_dia_pasadas',
            'CK' => 'pzas_dia_formula',
            'CL' => 'dif',
            'CM' => 'efic',
            'CN' => 'rev',
            'CO' => 'tiras_final',
            'CQ' => 'pasadastotal',
            'CR' => 'folio_codificacion',
            'CS' => 'peso_rollo',
            'CT' => 'calidad',
        ];

        foreach ($mapeo as $columna => $campo) {
            // Establecer valores directos desde BD
            $valor = $datos[$campo] ?? '';
            // Para peso_rollo, asegurar que sea numérico
            if ($campo === 'peso_rollo') {
                if ($valor === null || $valor === '') {
                    $valor = 0;
                } elseif (is_numeric($valor)) {
                    $valor = (float) $valor;
                } else {
                    // Si no es numérico, intentar convertir o usar 0
                    $valor = is_numeric($valor) ? (float) $valor : 0;
                }
            }
            $worksheet->setCellValue($columna . $fila, $valor);

            $style = $worksheet->getStyle($columna . $fila);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $worksheet->getRowDimension($fila)->setRowHeight(20);
    }

    /**
     * Determinar tipo formato desde datos de BD.
     */
    protected function determinarTipoFormatoDesdeBD(ReqProgramaTejido $registro): string
    {
        $tamanoClave = strtoupper($registro->TamanoClave ?? '');
        if (stripos($tamanoClave, 'FELPA') !== false) {
            return 'felpa';
        }

        $salon          = strtoupper($registro->SalonTejidoId ?? '');
        $nombreProyecto = strtoupper($registro->NombreProyecto ?? '');
        $nombreProducto = strtoupper($registro->NombreProducto ?? '');

        if (
            stripos($salon, 'JACQUARD') !== false ||
            stripos($nombreProyecto, 'JACQUARD') !== false ||
            stripos($nombreProducto, 'JACQUARD') !== false
        ) {
            return 'jacquard';
        }

        if (
            stripos($salon, 'SMIT') !== false ||
            stripos($nombreProyecto, 'SMIT') !== false ||
            stripos($nombreProducto, 'SMIT') !== false
        ) {
            return 'smit';
        }

        return 'felpa';
    }

    /**
     * Obtener hoja plantilla (primera que no sea REGISTRO).
     */
    protected function obtenerHojaPlantilla($spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (strtoupper($sheet->getTitle()) !== 'REGISTRO') {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * Eliminar todas las hojas excepto REGISTRO y la plantilla.
     */
    protected function limpiarHojasNoPlantillaNiRegistro($spreadsheet, Worksheet $hojaPlantilla): void
    {
        $hojasAEliminar = [];

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $titulo = strtoupper($sheet->getTitle());
            if ($titulo !== 'REGISTRO' && $sheet !== $hojaPlantilla) {
                $hojasAEliminar[] = $index;
            }
        }

        rsort($hojasAEliminar);
        foreach ($hojasAEliminar as $index) {
            $spreadsheet->removeSheetByIndex($index);
        }
    }

    /**
     * Activar primera hoja que no sea REGISTRO.
     */
    protected function activarPrimeraHojaNoRegistro($spreadsheet): void
    {
        $primeraHoja = 0;

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            if (strtoupper($sheet->getTitle()) !== 'REGISTRO') {
                $primeraHoja = $index;
                break;
            }
        }

        $spreadsheet->setActiveSheetIndex($primeraHoja);
    }
}
