<?php

namespace App\Http\Controllers\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DescargarProgramaController extends Controller
{
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

            // Obtener columnas de ReqProgramaTejido (excluyendo Id y EnProceso)
            $columnasPrograma = $this->getColumnasProgramaTejido();
            // Excluir Id y EnProceso
            $columnasPrograma = array_filter($columnasPrograma, function($col) {
                return $col !== 'Id' && $col !== 'EnProceso';
            });
            // Reindexar el array
            $columnasPrograma = array_values($columnasPrograma);

            // Columnas de ReqProgramaTejidoLine
            $columnasLine = [
                'Id', 'Fecha', 'Cantidad', 'Kilos', 'Aplicacion',
                'Trama', 'Combina1', 'Combina2', 'Combina3', 'Combina4', 'Combina5',
                'Pie', 'Rizo', 'MtsRizo', 'MtsPie'
            ];

            // Generar contenido del TXT
            $lineasTxt = [];

            // Agregar encabezado con nombres de columnas
            $encabezados = [];
            // Agregar ProgramaId primero (es el Id de ReqProgramaTejido)
            $encabezados[] = 'ProgramaId';
            // Encabezados de ReqProgramaTejido (sin Id y EnProceso)
            foreach ($columnasPrograma as $columna) {
                $encabezados[] = $columna;
            }
            // Encabezados de ReqProgramaTejidoLine
            foreach ($columnasLine as $columna) {
                $encabezados[] = $columna;
            }
            $lineasTxt[] = implode('|', $encabezados);

            foreach ($lineas as $linea) {
                $valores = [];

                // Obtener el programa relacionado
                $programa = $linea->programa;

                if (!$programa) {
                    continue; // Saltar si no hay programa relacionado
                }

                // Agregar ProgramaId (Id de ReqProgramaTejido)
                $valores[] = $this->formatearValor($programa->Id, 'Id');

                // Agregar valores de ReqProgramaTejido (sin Id y EnProceso)
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
     * Obtiene todas las columnas de ReqProgramaTejido
     */
    private function getColumnasProgramaTejido(): array
    {
        // Obtener todas las columnas de la tabla
        $columnas = DB::getSchemaBuilder()->getColumnListing('ReqProgramaTejido');

        // Retornar todas las columnas en orden
        return $columnas;
    }

    /**
     * Formatea un valor para el TXT
     */
    private function formatearValor($valor, string $columna, bool $esFechaSimple = false): string
    {
        if ($valor === null) {
            return '';
        }

        // Detectar si es una fecha por el nombre de la columna
        $esFecha = $esFechaSimple || in_array($columna, [
            'FechaInicio', 'FechaFinal', 'ProgramarProd', 'Programado',
            'EntregaProduc', 'EntregaPT', 'EntregaCte', 'CreatedAt', 'UpdatedAt'
        ]);

        if ($esFecha) {
            try {
                if ($valor instanceof Carbon) {
                    return $esFechaSimple
                        ? $valor->format('Y-m-d')
                        : $valor->format('Y-m-d H:i:s');
                } elseif (is_string($valor) && !empty($valor)) {
                    $carbon = Carbon::parse($valor);
                    return $esFechaSimple
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

        // Convertir a string y limpiar
        $valor = (string) $valor;
        // Remover saltos de línea y pipes que podrían romper el formato
        $valor = str_replace(["\n", "\r", "|"], [' ', ' ', ''], $valor);
        return trim($valor);
    }
}

