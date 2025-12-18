<?php
namespace App\Http\Controllers;

use App\Helpers\TelDesarrolladoresHelper;
use App\Models\TelTelaresOperador;
use App\Models\catDesarrolladoresModel;
use App\Models\catcodificados\CatCodificados;
use App\Models\ReqModelosCodificados;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

	    private function normalizeCodigoDibujo(?string $value): string
	    {
	        $normalized = Str::of((string) ($value ?? ''))
	            ->trim()
	            ->upper()
	            ->replaceMatches('/\s+/', '')
	            ->replaceMatches('/\.(?:JC5|JCS)$/i', '')
	            ->toString();

	        return $normalized === '' ? '' : ($normalized . '.JCS');
	    }

	    private function buildDetallePayloadFromOrden($ordenData): array
	    {
	        if (!$ordenData) {
	            return [];
	        }

	        $payload = [
	            'Tra' => data_get($ordenData, 'CalibreTrama'),
	            'CalibreTrama2' => data_get($ordenData, 'CalibreTrama2'),
	            'CodColorTrama' => data_get($ordenData, 'CodColorTrama'),
	            'ColorTrama' => data_get($ordenData, 'ColorTrama'),
	            'FibraId' => data_get($ordenData, 'FibraTrama'),
	            'CalTramaFondoC1' => data_get($ordenData, 'CalibreTrama'),
	            'CalTramaFondoC12' => data_get($ordenData, 'CalibreTrama2'),
	            'FibraTramaFondoC1' => data_get($ordenData, 'FibraTrama'),
	        ];

	        for ($i = 1; $i <= 5; $i++) {
	            $nombreKey = $ordenData->{"NombreCC{$i}"} !== null ? "NombreCC{$i}" : "NomColorC{$i}";

	            $payload["CalibreComb{$i}"] = data_get($ordenData, "CalibreComb{$i}");
	            $payload["CalibreComb{$i}2"] = data_get($ordenData, "CalibreComb{$i}2");
	            $payload["FibraComb{$i}"] = data_get($ordenData, "FibraComb{$i}");
	            $payload["CodColorC{$i}"] = data_get($ordenData, "CodColorComb{$i}");
	            $payload["NomColorC{$i}"] = data_get($ordenData, $nombreKey);
	        }

	        return $payload;
	    }

	    public function store(Request $request){
	        try {
	            $validated = $request->validate([
	                'NoTelarId' => 'required|string',
	                'NoProduccion' => 'required|string|max:80',
	                'NumeroJulioRizo' => 'required|string|max:50',
	                'NumeroJulioPie' => 'nullable|string|max:50',
	                'TotalPasadasDibujo' => 'required|integer|min:1',
	                'HoraInicio' => 'nullable|date_format:H:i',
	                'EficienciaInicio' => 'nullable|integer|min:0|max:100',
	                'HoraFinal' => 'nullable|date_format:H:i',
	                'EficienciaFinal' => 'nullable|integer|min:0|max:100',
	                'Desarrollador' => 'nullable|string|max:100',
	                'TramaAnchoPeine' => 'nullable|numeric|min:0',
	                'DesperdicioTrama' => 'nullable|numeric|min:0',
	                'LongitudLuchaTot' => 'nullable|numeric|min:0',
	                'CodificacionModelo' => 'required|string|max:100',
                    'pasadas' => 'nullable|array',
                    'pasadas.*' => 'nullable|integer|min:1',
	            ]);

	            $codigoDibujo = $this->normalizeCodigoDibujo($validated['CodificacionModelo'] ?? '');
	            $ordenData = \App\Models\ReqProgramaTejido::where('NoProduccion', $validated['NoProduccion'])->first();
	            $detallePayload = $this->buildDetallePayloadFromOrden($ordenData);

                $pasadasPayload = [];
                $pasadasFromRequest = $validated['pasadas'] ?? [];
                if (is_array($pasadasFromRequest) && count($pasadasFromRequest) > 0) {
                    foreach ($pasadasFromRequest as $key => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $pasadasPayload[$key] = (int) $value;
                    }
                } elseif ($ordenData) {
                    $tramaValue = data_get($ordenData, 'PasadasTramaFondoC1');
                    if ($tramaValue !== null && $tramaValue !== '') {
                        $pasadasPayload['PasadasTramaFondoC1'] = (int) $tramaValue;
                    }
	
                    for ($i = 1; $i <= 5; $i++) {
                        $field = "PasadasComb{$i}";
                        $combValue = data_get($ordenData, $field);
                        if ($combValue === null || $combValue === '') {
                            continue;
                        }
                        $pasadasPayload[$field] = (int) $combValue;
                    }
                }
	            $modelo = new CatCodificados();
	            $table = $modelo->getTable();
	            $columns = Schema::getColumnListing($table);

	            $query = CatCodificados::query();
	            $hasKeyFilter = false;
	            if (in_array('OrdenTejido', $columns, true)) {
	                $query->where('OrdenTejido', $validated['NoProduccion']);
	                $hasKeyFilter = true;
	            } elseif (in_array('NumOrden', $columns, true)) {
	                $query->where('NumOrden', $validated['NoProduccion']);
	                $hasKeyFilter = true;
	            }

	            if (in_array('TelarId', $columns, true)) {
	                $query->where('TelarId', $validated['NoTelarId']);
	                $hasKeyFilter = true;
	            } elseif (in_array('NoTelarId', $columns, true)) {
	                $query->where('NoTelarId', $validated['NoTelarId']);
	                $hasKeyFilter = true;
	            }

                $registro = $hasKeyFilter ? ($query->first() ?? $modelo) : $modelo;
                $wasUpdate = (bool) $registro->exists;

                $payload = array_merge([
	                'TelarId' => $validated['NoTelarId'],
	                'NoTelarId' => $validated['NoTelarId'],
	                'OrdenTejido' => $validated['NoProduccion'],
	                'CodigoDibujo' => $codigoDibujo,
	                'CodificacionModelo' => $codigoDibujo,
	                'RespInicio' => $validated['Desarrollador'] ?? null,
	                'HrInicio' => $validated['HoraInicio'] ?? null,
	                'HrTermino' => $validated['HoraFinal'] ?? null,
	                'TramaAnchoPeine' => $validated['TramaAnchoPeine'] ?? null,
	                'AnchoPeineTrama' => $validated['TramaAnchoPeine'] ?? null,
	                'LogLuchaTotal' => $validated['LongitudLuchaTot'] ?? null,
	                'LongitudLuchaTot' => $validated['LongitudLuchaTot'] ?? null,
	                'Total' => $validated['TotalPasadasDibujo'],
	                'TotalPasadasDibujo' => $validated['TotalPasadasDibujo'],
                    'NumeroJulioRizo' => $validated['NumeroJulioRizo'],
                    'NumeroJulioPie' => $validated['NumeroJulioPie'] ?? null,
                    'JulioRizo' => $validated['NumeroJulioRizo'],
                    'JulioPie' => $validated['NumeroJulioPie'] ?? null,
                    'EficienciaInicio' => $validated['EficienciaInicio'] ?? null,
                    'EficienciaFinal' => $validated['EficienciaFinal'] ?? null,
                    'EfiInicial' => $validated['EficienciaInicio'] ?? null,
                    'EfiFinal' => $validated['EficienciaFinal'] ?? null,
	                'DesperdicioTrama' => $validated['DesperdicioTrama'] ?? null,
                ], $detallePayload, $pasadasPayload);

	            foreach ($payload as $column => $value) {
	                if (!in_array($column, $columns, true)) {
	                    continue;
	                }
	                $registro->setAttribute($column, $value);
	            }

	            $registro->save();

	            Log::info('Datos de desarrollador guardados en CatCodificados', [
	                'table' => $table,
	                'id' => $registro->getAttribute($registro->getKeyName()),
                    'accion' => $wasUpdate ? 'update' : 'insert',
	                'NoProduccion' => $validated['NoProduccion'],
	                'NoTelarId' => $validated['NoTelarId'],
	            ]);

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
