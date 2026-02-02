<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DescargarProgramaController extends Controller
{
    private const COLUMNAS_FECHA_HORA = [
        'FechaInicio',
        'FechaFinal',
    ];

    private const COLUMNAS_FECHA = [
        'Fecha',
        'ProgramarProd',
        'Programado',
        'EntregaProduc',
        'EntregaPT',
        'EntregaCte',
        'ProgramarProd',
    ];

    private const COLUMNAS_DECIMALES = [
        'Cantidad', 'Kilos', 'Aplicacion', 'Trama',
        'Combina1', 'Combina2', 'Combina3', 'Combina4', 'Combina5',
        'Pie', 'Rizo', 'MtsRizo', 'MtsPie',
        'TotalPedido', 'Produccion', 'SaldoPedido', 'SaldoMarbete',
        'LargoCrudo', 'PesoCrudo', 'Luchaje', 'MedidaPlano',
        'CalibreTrama2', 'CalibrePie2', 'CalibreRizo2',
        'CalibreComb12', 'CalibreComb22', 'CalibreComb32', 'CalibreComb42', 'CalibreComb52',
        'AnchoToalla', 'PesoGRM2', 'DiasEficiencia',
        'ProdKgDia', 'StdDia', 'ProdKgDia2', 'StdToaHra',
        'DiasJornada', 'HorasProd', 'StdHrsEfect',
        'Calc4', 'Calc5', 'Calc6', 'ProdKgDia',
    ];
    /**
     * Descarga el programa de tejido como archivo TXT
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function descargar(Request $request)
    {
        try {
            // Validar fecha inicial
            $request->validate([
                'fecha_inicial' => 'required|date'
            ]);

            $fechaInicial = $request->input('fecha_inicial');

            // Ruta donde se guardará el archivo
            $rutaArchivo = '\\\\192.168.2.11\\txts\\ProgramaTejido.txt';

            // Obtener todos los registros de ReqProgramaTejidoLine con fecha >= fecha_inicial
            // y cargar la relación con ReqProgramaTejido
            $lineas = ReqProgramaTejidoLine::with('programa')
                ->where('Fecha', '>=', $fechaInicial)
                ->orderBy('ProgramaId')
                ->orderBy('Fecha')
                ->get();

            if ($lineas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros para la fecha especificada.'
                ], 404);
            }

            // Obtener mapeo de columnas
            $mapeoColumnas = $this->getMapeoColumnas();

            // Utilizar la lista explícita de columnas del ProgramaTejidoController para mantener el orden y consistencia
            $columnasPrograma = $this->getColumnasOrdenadas();

            // Columnas de ReqProgramaTejidoLine (sin Id)
            $columnasLine = [
                'Fecha', 'Cantidad', 'Kilos', 'Aplicacion',
                'Trama', 'Combina1', 'Combina2', 'Combina3', 'Combina4', 'Combina5',
                'Pie', 'Rizo', 'MtsRizo', 'MtsPie'
            ];

            // Generar contenido del TXT
            $lineasTxt = [];

            // Agregar encabezado con nombres de columnas
            $encabezados = [];
            // Agregar columna Id como primera columna (consecutivo)
            $encabezados[] = 'Id';
            // Encabezados de ReqProgramaTejido: aplicar mapeo solo si existe, sino usar nombre original
            foreach ($columnasPrograma as $columna) {
                $encabezados[] = $mapeoColumnas[$columna] ?? $columna;
            }
            // Encabezados de ReqProgramaTejidoLine
            foreach ($columnasLine as $columna) {
                $encabezados[] = $columna;
            }
            $lineasTxt[] = implode('|', $encabezados);

            // Contador consecutivo para el Id (agrupa por TamanoClave + NoProduccion)
            $consecutivo = 0;
            $claveAnterior = null;

            foreach ($lineas as $linea) {
                $valores = [];

                // Obtener el programa relacionado
                $programa = $linea->programa;

                if (!$programa) {
                    continue; // Saltar si no hay programa relacionado
                }

                // Crear clave única con TamanoClave + NoProduccion
                $claveActual = ($programa->TamanoClave ?? '') . '|' . ($programa->NoProduccion ?? '');

                // Solo incrementar el consecutivo si cambia la combinación TamanoClave + NoProduccion
                if ($claveActual !== $claveAnterior) {
                    $consecutivo++;
                    $claveAnterior = $claveActual;
                }

                // Agregar Id consecutivo como primer valor
                $valores[] = $consecutivo;

                // Agregar valores de ReqProgramaTejido (todas las columnas excepto las excluidas)
                foreach ($columnasPrograma as $columna) {
                    $valor = $programa->{$columna} ?? null;
                    $valores[] = $this->formatearValor($valor, $columna);
                }

                // Agregar valores de ReqProgramaTejidoLine
                foreach ($columnasLine as $columna) {
                    $valor = $linea->{$columna} ?? null;
                    // Para Fecha, usar formato de fecha simple
                    $esFecha = ($columna === 'Fecha');
                    $valores[] = $this->formatearValor($valor, $columna, $esFecha);
                }

                $lineasTxt[] = implode('|', $valores);
            }

            $contenido = implode("\n", $lineasTxt);

            // SobreEscribir el archivo en la ruta de red
            $resultado = @file_put_contents($rutaArchivo, $contenido);

            if ($resultado === false) {
                Log::error('Error al escribir archivo en ruta de red', [
                    'ruta' => $rutaArchivo,
                    'error' => error_get_last()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el archivo. Verifique que la ruta de red sea accesible.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Archivo guardado correctamente. Registros procesados: ' . count($lineasTxt),
                'registros' => count($lineasTxt)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en descargarPrograma', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el mapeo de columnas a etiquetas personalizadas
     * Coincide con getTableColumns de ProgramaTejidoController
     */
    private function getMapeoColumnas(): array
    {
        return [
            'CuentaRizo' => 'Cuenta',
            'CalibreRizo2' => 'Calibre Rizo',
            'SalonTejidoId' => 'Salón',
            'NoTelarId' => 'Telar',
            'Ultimo' => 'Último',
            'CambioHilo' => 'Cambios Hilo',
            'Maquina' => 'Maq',
            'Ancho' => 'Ancho',
            'EficienciaSTD' => 'Ef Std',
            'VelocidadSTD' => 'Vel',
            'FibraRizo' => 'Hilo',
            'CalibrePie2' => 'Calibre Pie',
            'CalendarioId' => 'Jornada',
            'TamanoClave' => 'Clave Mod.',
            'NoExisteBase' => 'Usar cuando no existe en base',
            'ItemId' => 'Clave AX',
            'InventSizeId' => 'Tamaño AX',
            'Rasurado' => 'Rasurado',
            'NombreProducto' => 'Producto',
            'TotalPedido' => 'Pedido',
            'Produccion' => 'Producción',
            'SaldoPedido' => 'Saldos',
            'SaldoMarbete' => 'Saldo Marbetes',
            'ProgramarProd' => 'Día Scheduling',
            'NoProduccion' => 'Orden Prod.',
            'Programado' => 'INN',
            'FlogsId' => 'Id Flog',
            'NombreProyecto' => 'Descrip.',
            'CustName' => 'Nombre Cliente',
            'AplicacionId' => 'Aplic.',
            'Observaciones' => 'Obs',
            'TipoPedido' => 'Tipo Ped.',
            'NoTiras' => 'Tiras',
            'Peine' => 'Pei.',
            'LargoCrudo' => 'Largo Crudo',
            'Luchaje' => 'Luchaje',
            'PesoCrudo' => 'Peso Crudo',
            'CalibreTrama2' => 'Calibre Trama',
            'FibraTrama' => 'Fibra Trama',
            'DobladilloId' => 'Dob',
            'PasadasTrama' => 'Pasadas Tra',
            'PasadasComb1' => 'Pasadas C1',
            'PasadasComb2' => 'Pasadas C2',
            'PasadasComb3' => 'Pasadas C3',
            'PasadasComb4' => 'Pasadas C4',
            'PasadasComb5' => 'Pasadas C5',
            'AnchoToalla' => 'Ancho por Toalla',
            'CodColorTrama' => 'Código Color Tra',
            'ColorTrama' => 'Color Tra',
            'CalibreComb12' => 'Calibre C1',
            'FibraComb1' => 'Fibra C1',
            'CodColorComb1' => 'Código Color C1',
            'NombreCC1' => 'Color C1',
            'CalibreComb22' => 'Calibre C2',
            'FibraComb2' => 'Fibra C2',
            'CodColorComb2' => 'Código Color C2',
            'NombreCC2' => 'Color C2',
            'CalibreComb32' => 'Calibre C3',
            'FibraComb3' => 'Fibra C3',
            'CodColorComb3' => 'Código Color C3',
            'NombreCC3' => 'Color C3',
            'CalibreComb42' => 'Calibre C4',
            'FibraComb4' => 'Fibra C4',
            'CodColorComb4' => 'Código Color C4',
            'NombreCC4' => 'Color C4',
            'CalibreComb52' => 'Calibre C5',
            'FibraComb5' => 'Fibra C5',
            'CodColorComb5' => 'Código Color C5',
            'NombreCC5' => 'Color C5',
            'MedidaPlano' => 'Plano',
            'CuentaPie' => 'Cuenta Pie',

            'CodColorCtaPie' => 'Código Color Pie',
            'NombreCPie' => 'Color Pie',
            'PesoGRM2' => 'Peso (gr/m²)',
            'DiasEficiencia' => 'Días Ef.',
            'ProdKgDia' => 'Prod (Kg)/Día',
            'StdDia' => 'Std/Día',
            'ProdKgDia2' => 'Prod (Kg)/Día 2',
            'StdToaHra' => 'Std (Toa/Hr) 100%',
            'DiasJornada' => 'Días Jornada',
            'HorasProd' => 'Horas',
            'StdHrsEfect' => 'Std/Hr Efectivo',
            'FechaInicio' => 'Inicio',
            'FechaFinal' => 'Fin',
            'EntregaProduc' => 'Fecha Compromiso Prod.',
            'EntregaPT' => 'Fecha Compromiso PT',
            'EntregaCte' => 'Entrega',
            'PTvsCte' => 'Dif vs Compromiso',
        ];
    }

    /**
     * Obtiene el orden específico de las columnas
     * Coincide con ProgramaTejidoController@index
     */
    private function getColumnasOrdenadas(): array
    {
        return [
            'CuentaRizo',
            'CalibreRizo2',
            'SalonTejidoId',
            'NoTelarId',
            'Ultimo',
            'CambioHilo',
            'Maquina',
            'Ancho',
            'EficienciaSTD',
            'VelocidadSTD',
            'FibraRizo',
            'CalibrePie2',
            'CalendarioId',
            'TamanoClave',
            'NoExisteBase',
            'ItemId',
            'InventSizeId',
            'Rasurado',
            'NombreProducto',
            'TotalPedido',
            'SaldoPedido',
            'SaldoMarbete',
            'ProgramarProd',
            'NoProduccion',
            'Programado',
            'FlogsId',
            'NombreProyecto',
            'AplicacionId',
            'Observaciones',
            'TipoPedido',
            'NoTiras',
            'Peine',
            'Luchaje',
            'PesoCrudo',
            'LargoCrudo',
            'CalibreTrama2',
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
            'PTvsCte'
        ];
    }

    /**
     * Formatea un valor para el TXT
     */
    private function formatearValor($valor, string $columna, bool $forzarFechaSimple = false): string
    {
        if ($valor === null) {
            return '';
        }

        $esFechaHora = in_array($columna, self::COLUMNAS_FECHA_HORA, true);
        $esSoloFecha = $forzarFechaSimple || in_array($columna, self::COLUMNAS_FECHA, true);

        if ($esFechaHora || $esSoloFecha) {
            try {
                if ($valor instanceof Carbon) {
                    return $esSoloFecha
                        ? $valor->format('Y-m-d')
                        : $valor->format('Y-m-d H:i:s');
                } elseif (is_string($valor) && !empty($valor)) {
                    $carbon = Carbon::parse($valor);
                    return $esSoloFecha
                        ? $carbon->format('Y-m-d')
                        : $carbon->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // Si falla el parseo, devolver el valor original como string
            }
        }

        // Formatear booleanos
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_numeric($valor) && in_array($columna, self::COLUMNAS_DECIMALES, true)) {
            $valor = round((float) $valor, 3);
            return number_format($valor, 3, '.', '');
        }

        $valor = (string) $valor;
        $valor = str_replace(["\n", "\r", "|"], [' ', ' ', ''], $valor);
        return trim($valor);
    }
}
