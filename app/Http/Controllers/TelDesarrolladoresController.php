<?php
namespace App\Http\Controllers;

use App\Helpers\TelDesarrolladoresHelper;
use App\Models\TelTelaresOperador;
use App\Models\catDesarrolladoresModel;
use App\Models\ReqModelosCodificados;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TelDesarrolladoresController extends Controller
{
    public function index(Request $request)
    {

        $telares = $this->obtenerTelares();
        $juliosRizo = $this->obtenerJuliosEngomado();
        $juliosPie = $this->obtenerJuliosEngomado();
        $desarrolladores = catDesarrolladoresModel::all();

        return view('modulos.desarrolladores.desarrolladores', compact( 'telares', 'juliosRizo', 'juliosPie', 'desarrolladores'));
    }

    protected function obtenerTelares()
    {
        return TelTelaresOperador::select('NoTelarId')
            ->whereNotNull('NoTelarId')
            ->groupBy('NoTelarId')
            ->orderBy('NoTelarId')
            ->get();
    }

    protected function obtenerJuliosEngomado()
    {
        return \App\Models\UrdCatJulios::where('Departamento', 'engomado')
            ->whereNotNull('NoJulio')
            ->select('NoJulio')
            ->distinct()
            ->orderBy('NoJulio')
            ->get();
    }

    public function obtenerProducciones(Request $request, $telarId)
    {
        try {
            $producciones = \App\Models\ReqProgramaTejido::where('NoTelarId', $telarId)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->select('SalonTejidoId', 'NoProduccion', 'FechaInicio', 'TamanoClave', 'NombreProducto')
                ->distinct()
                ->orderBy('NoProduccion')
                ->get();

            return response()->json([
                'success' => true,
                'producciones' => $producciones
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las producciones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function obtenerDetallesOrden($noProduccion)
    {
        try {
            $ordenData = \App\Models\ReqProgramaTejido::where('NoProduccion', $noProduccion)->first();

            $detalles = [];

            $isZeroish = static function ($value): bool {
                $text = trim((string) ($value ?? ''));
                if ($text === '') {
                    return true;
                }

                return (bool) preg_match('/^0+(?:\.0+)?$/', $text);
            };

            $shouldIncludeDetalle = static function (array $fila) use ($isZeroish): bool {
                $calibre = trim((string) ($fila['Calibre'] ?? ''));
                if ($calibre === '') {
                    return false;
                }

                $keys = ['Calibre', 'Hilo', 'Fibra', 'CodColor', 'NombreColor', 'Pasadas'];
                foreach ($keys as $key) {
                    if (!$isZeroish($fila[$key] ?? '')) {
                        return true;
                    }
                }

                return false;
            };

            if ($ordenData) {
                $filaTrama = TelDesarrolladoresHelper::mapDetalleFila(
                    $ordenData,
                    'CalibreTrama',
                    'CalibreTrama2',
                    'FibraTrama',
                    'CodColorTrama',
                    'ColorTrama',
                    'PasadasTramaFondoC1'
                );

                if ($shouldIncludeDetalle($filaTrama)) {
                    $detalles[] = $filaTrama;
                }

                for ($i = 1; $i <= 5; $i++) {
                    $filaComb = TelDesarrolladoresHelper::mapDetalleFila(
                        $ordenData,
                        "CalibreComb{$i}",
                        "CalibreComb{$i}2",
                        "FibraComb{$i}",
                        "CodColorComb{$i}",
                        $ordenData->{"NombreCC{$i}"} !== null
                            ? "NombreCC{$i}"
                            : "NomColorC{$i}",
                        "PasadasComb{$i}"
                    );

                    if ($shouldIncludeDetalle($filaComb)) {
                        $detalles[] = $filaComb;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'detalles' => $detalles
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ], 500);
        }
    }

    

    public function obtenerCodigoDibujo(Request $request, $salonTejidoId, $tamanoClave)
    {
        try {
            $codigoDibujo = ReqModelosCodificados::query()
                ->where('SalonTejidoId', $salonTejidoId)
                ->where('TamanoClave', $tamanoClave)
                ->whereNotNull('CodigoDibujo')
                ->orderByDesc('Id')
                ->value('CodigoDibujo');

            if (!$codigoDibujo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró CodigoDibujo para los parámetros proporcionados.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'codigoDibujo' => $codigoDibujo
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener CodigoDibujo: ' . $e->getMessage(), [
                'salonTejidoId' => $salonTejidoId,
                'tamanoClave' => $tamanoClave,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener CodigoDibujo'
            ], 500);
        }
    }

    public function formularioDesarrollador(Request $request, $telarId, $noProduccion)
    {
        $datosProduccion = \App\Models\ReqProgramaTejido::where('NoTelarId', $telarId)
            ->where('NoProduccion', $noProduccion)
            ->first();
        
        return view('modulos.desarrolladores.formulario', compact('datosProduccion', 'telarId', 'noProduccion'));
    }

    protected function store(Request $request){
        try {
            $validated = $request->validate([
                'NoTelarId' => 'required|string',
                'NumeroJulioRizo' => 'required|string|max:50',
                'NumeroJulioPie' => 'required|string|max:50',
                'TotalPasadasDibujo' => 'required|numeric|min:0',
                'HoraInicio' => 'required|date_format:H:i',
                'EficienciaInicio' => 'required|numeric|min:0|max:100',
                'HoraFinal' => 'required|date_format:H:i',
                'EficienciaFinal' => 'required|numeric|min:0|max:100',
                'Desarrollador' => 'required|string|max:100',
                'TramaAnchoPeine' => 'required|numeric|min:0',
                'DesperdicioTrama' => 'required|numeric|min:0',
                'LongitudLuchaTot' => 'required|numeric|min:0',
                'CodificacionModelo' => 'required|string|max:100'
            ]);

            Log::info('Datos de desarrollador guardados:', $validated);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Datos guardados correctamente'
                ]);
            }

            return redirect()->route('desarrolladores')
                ->with('success', 'Datos guardados correctamente');
        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            Log::error('Error al guardar datos de desarrollador: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error al guardar los datos'
                ], 500);
            }
            
            return back()->with('error', 'Ocurrió un error al guardar los datos')->withInput();
        }
    }


}
