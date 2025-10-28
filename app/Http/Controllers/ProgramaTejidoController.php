<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqProgramaTejido;
use App\Models\ReqModelosCodificados;
use Illuminate\Support\Facades\DB;

class ProgramaTejidoController extends Controller
{
    /**
     * Obtener opciones de SalonTejidoId desde ambas tablas
     */
    public function getSalonTejidoOptions()
    {
        // Obtener valores únicos de SalonTejidoId de ReqProgramaTejido
        $programaTejido = ReqProgramaTejido::select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->get()
            ->pluck('SalonTejidoId')
            ->filter()
            ->values();

        // Obtener valores únicos de SalonTejidoId de ReqModelosCodificados
        $modelosCodificados = ReqModelosCodificados::select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->get()
            ->pluck('SalonTejidoId')
            ->filter()
            ->values();

        // Combinar y eliminar duplicados
        $opciones = $programaTejido->merge($modelosCodificados)
            ->unique()
            ->sort()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de TamanoClave (Clave Modelo)
     */
    public function getTamanoClaveOptions()
    {
        // Obtener valores únicos de TamanoClave SOLO de ReqModelosCodificados
        $opciones = ReqModelosCodificados::select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave', '!=', '')
            ->distinct()
            ->get()
            ->pluck('TamanoClave')
            ->filter()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de TamanoClave filtradas por SalonTejidoId
     */
    public function getTamanoClaveBySalon(Request $request)
    {
        $salonTejidoId = $request->input('salon_tejido_id');
        $search = $request->input('search', '');

        $query = ReqModelosCodificados::select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave', '!=', '');

        // Filtrar por SalonTejidoId si se proporciona
        if ($salonTejidoId) {
            $query->where('SalonTejidoId', $salonTejidoId);
        }

        // Filtrar por búsqueda si se proporciona
        if ($search) {
            $query->where('TamanoClave', 'LIKE', '%' . $search . '%'); // Buscar en cualquier parte del texto
        }

        // Limitar resultados para mejor rendimiento
        $opciones = $query->distinct()
            ->limit(50) // Limitar a 50 resultados máximo
            ->get()
            ->pluck('TamanoClave')
            ->filter()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de FlogsId (IdFlog)
     */
    public function getFlogsIdOptions()
    {
        // Obtener valores únicos de FlogsId de ReqProgramaTejido
        $programaTejido = ReqProgramaTejido::select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->get()
            ->pluck('FlogsId')
            ->filter()
            ->values();

        // Obtener valores únicos de FlogsId de ReqModelosCodificados
        $modelosCodificados = ReqModelosCodificados::select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->get()
            ->pluck('FlogsId')
            ->filter()
            ->values();

        // Combinar y eliminar duplicados
        $opciones = $programaTejido->merge($modelosCodificados)
            ->unique()
            ->sort()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de CalendarioId (Calendario) desde ReqCalendarioTab
     */
    public function getCalendarioIdOptions()
    {
        // Obtener valores únicos de CalendarioId desde ReqCalendarioTab
        $opciones = DB::table('ReqCalendarioTab')
            ->select('CalendarioId')
            ->whereNotNull('CalendarioId')
            ->where('CalendarioId', '!=', '')
            ->distinct()
            ->pluck('CalendarioId')
            ->filter()
            ->values();

        // Ordenar las opciones obtenidas de la base de datos
        $opciones = $opciones->sort()->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de AplicacionId (Aplicación)
     */
    public function getAplicacionIdOptions()
    {
        try {
            // Obtener valores únicos de AplicacionId de ReqProgramaTejido
            $opciones = ReqProgramaTejido::select('AplicacionId')
                ->whereNotNull('AplicacionId')
                ->where('AplicacionId', '!=', '')
                ->distinct()
                ->pluck('AplicacionId')
                ->filter()
                ->values();

            // Si no hay datos en la base, devolver mensaje
            if ($opciones->isEmpty()) {
                return response()->json([
                    'mensaje' => 'No se encontraron opciones de aplicación disponibles'
                ]);
            }

            return response()->json($opciones);
        } catch (\Exception $e) {
            // En caso de error, devolver mensaje de error
            return response()->json([
                'error' => 'Error al cargar opciones de aplicación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener datos relacionados por SalonTejidoId y TamanoClave
     */
    public function getDatosRelacionados(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');
            $tamanoClave = $request->input('tamano_clave');

            if (!$salonTejidoId) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            $query = ReqModelosCodificados::where('SalonTejidoId', $salonTejidoId);

            // Si se proporciona TamanoClave, filtrar por él también
            if ($tamanoClave) {
                $query->where('TamanoClave', $tamanoClave);
            }

            // Obtener solo los campos que existen en la tabla
            $datos = $query->select(
                // Campos básicos
                'TamanoClave', 'SalonTejidoId', 'FlogsId', 'Nombre', 'NombreProyecto', 'InventSizeId',

                // Rizo
                'CuentaRizo', 'CalibreRizo', 'CalibreRizo2', 'FibraRizo',

                // Trama
                'CalibreTrama', 'CalibreTrama2', 'CodColorTrama', 'ColorTrama', 'FibraId',

                // Pie
                'CalibrePie', 'CalibrePie2', 'CuentaPie', 'FibraPie',

                // Colores C1-C5
                'CodColorC1', 'NomColorC1', 'CodColorC2', 'NomColorC2',
                'CodColorC3', 'NomColorC3', 'CodColorC4', 'NomColorC4',
                'CodColorC5', 'NomColorC5',

                // Combinaciones C1-C5 - Calibres
                'CalibreComb1', 'CalibreComb12', 'FibraComb1',
                'CalibreComb2', 'CalibreComb22', 'FibraComb2',
                'CalibreComb3', 'CalibreComb32', 'FibraComb3',
                'CalibreComb4', 'CalibreComb42', 'FibraComb4',
                'CalibreComb5', 'CalibreComb52', 'FibraComb5',

                // Medidas y especificaciones
                'AnchoToalla', 'LargoToalla', 'PesoCrudo', 'Luchaje', 'Peine',
                'NoTiras', 'Repeticiones', 'TotalMarbetes', 'CambioRepaso', 'Vendedor',
                'CatCalidad', 'AnchoPeineTrama', 'LogLuchaTotal', 'MedidaPlano', 'Rasurado',

                // Trama Fondo C1
                'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1',

                // Pasadas
                'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',

                // Otros campos
                'DobladilloId', 'Obs', 'Obs1', 'Obs2', 'Obs3', 'Obs4', 'Obs5',

                // Campo Total para las fórmulas
                'Total'
            )->first();


            return response()->json([
                'datos' => $datos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTelaresBySalon(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');

            if (!$salonTejidoId) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            // Obtener telares únicos para el salón seleccionado
            $telares = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->distinct()
                ->whereNotNull('NoTelarId')
                ->pluck('NoTelarId')
                ->sort()
                ->values()
                ->toArray();

            return response()->json($telares);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener telares: ' . $e->getMessage()], 500);
        }
    }

    public function getUltimaFechaFinalTelar(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');
            $noTelarId = $request->input('no_telar_id');

            if (!$salonTejidoId || !$noTelarId) {
                return response()->json(['error' => 'SalonTejidoId y NoTelarId son requeridos'], 400);
            }

            // Obtener la última fecha final del telar seleccionado
            $ultimaFecha = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->where('NoTelarId', $noTelarId)
                ->whereNotNull('FechaFinal')
                ->orderBy('FechaFinal', 'desc')
                ->value('FechaFinal');

            return response()->json([
                'ultima_fecha_final' => $ultimaFecha
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener última fecha final: ' . $e->getMessage()], 500);
        }
    }
}
