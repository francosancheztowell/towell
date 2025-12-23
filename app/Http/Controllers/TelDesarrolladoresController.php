<?php
namespace App\Http\Controllers;

use App\Helpers\TelDesarrolladoresHelper;
use App\Models\TelTelaresOperador;
use App\Models\catDesarrolladoresModel;
use App\Models\catcodificados\CatCodificados;
use App\Models\ReqModelosCodificados;
use Carbon\Carbon;
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

    public function obtenerRegistroCatCodificado($telarId, $noProduccion)
    {
        try {
            $modelo = new CatCodificados();
            $table = $modelo->getTable();
            $columns = Schema::getColumnListing($table);

            $query = CatCodificados::query();
            $hasOrderFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasOrderFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasOrderFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $telarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $telarId);
            }

            if (!$hasOrderFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registro = $query->select([
                'JulioRizo',
                'JulioPie',
                'EfiInicial',
                'EfiFinal',
                'DesperdicioTrama',
            ])->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información registrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'registro' => $registro,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener datos de CatCodificados: ' . $e->getMessage(), [
                'telarId' => $telarId,
                'noProduccion' => $noProduccion,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la información'
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

	        $colorTrama = data_get($ordenData, 'ColorTrama');
	        $fibraTrama = data_get($ordenData, 'FibraTrama');
	        
	        // Si ColorTrama está vacío, usar FibraTrama
	        if (empty($colorTrama)) {
	            $colorTrama = $fibraTrama;
	        }

	        $payload = [
	            'Tra' => data_get($ordenData, 'CalibreTrama'),
	            'CalibreTrama2' => data_get($ordenData, 'CalibreTrama2'),
	            'CodColorTrama' => data_get($ordenData, 'CodColorTrama'),
	            'ColorTrama' => $colorTrama,
	            'FibraId' => $fibraTrama,
	            'CalTramaFondoC1' => data_get($ordenData, 'CalibreTrama'),
	            'CalTramaFondoC12' => data_get($ordenData, 'CalibreTrama2'),
	            'FibraTramaFondoC1' => $fibraTrama,
	        ];

	        for ($i = 1; $i <= 5; $i++) {
	            $nombreKey = $ordenData->{"NombreCC{$i}"} !== null ? "NombreCC{$i}" : "NomColorC{$i}";
	            $nombreColor = data_get($ordenData, $nombreKey);
	            $fibraComb = data_get($ordenData, "FibraComb{$i}");
	            
	            // Si NomColorC está vacío, usar FibraComb
	            if (empty($nombreColor)) {
	                $nombreColor = $fibraComb;
	            }

	            $payload["CalibreComb{$i}"] = data_get($ordenData, "CalibreComb{$i}");
	            $payload["CalibreComb{$i}2"] = data_get($ordenData, "CalibreComb{$i}2");
	            $payload["FibraComb{$i}"] = $fibraComb;
	            $payload["CodColorC{$i}"] = data_get($ordenData, "CodColorComb{$i}");
	            $payload["NomColorC{$i}"] = $nombreColor;
	        }

	        return $payload;
	    }

        private function calcularMinutosCambio(?string $horaInicio, ?string $horaFinal): ?int
        {
            if (!$horaInicio || !$horaFinal) {
                return null;
            }

            try {
                $inicio = Carbon::createFromFormat('H:i', $horaInicio);
                $final = Carbon::createFromFormat('H:i', $horaFinal);

                if ($final->lt($inicio)) {
                    $final->addDay();
                }

                return max(0, $inicio->diffInMinutes($final));
            } catch (Exception $e) {
                Log::warning('No se pudo calcular MinutosCambio', [
                    'horaInicio' => $horaInicio,
                    'horaFinal' => $horaFinal,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
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
                $minutosCambio = $this->calcularMinutosCambio(
                    $validated['HoraInicio'] ?? null,
                    $validated['HoraFinal'] ?? null
                );

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
                    'MinutosCambio' => $minutosCambio,
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
	                'FechaCumplimiento' => now()->format('Y-m-d H:i:s'),
                ], $detallePayload, $pasadasPayload);

	            foreach ($payload as $column => $value) {
	                if (!in_array($column, $columns, true)) {
	                    continue;
	                }
	                $registro->setAttribute($column, $value);
	            }

	            $registro->save();

                // Buscar y actualizar registro en ReqModelosCodificados
                $claveModelo = $registro->getAttribute('ClaveModelo') ?: data_get($ordenData, 'TamanoClave');
                $departamento = $registro->getAttribute('Departamento') ?: data_get($ordenData, 'SalonTejidoId');
	            
                if ($claveModelo || $departamento) {
                    $queryModelos = ReqModelosCodificados::query();
	                
                    if ($claveModelo) {
                        $queryModelos->where('TamanoClave', $claveModelo);
                    }
	                
                    if ($departamento) {
                        $queryModelos->where('SalonTejidoId', $departamento);
                    }
	                
                    $registroModelo = $queryModelos->first();
	                
                    if ($registroModelo) {
                        // Preparar payload para actualizar ReqModelosCodificados
                        $payloadModelo = array_merge([
                            'TamanoClave' => $claveModelo,
                            'SalonTejidoId' => $departamento,
                            'NoTelarId' => $validated['NoTelarId'],
                            'OrdenTejido' => $validated['NoProduccion'],
                            'CodigoDibujo' => $codigoDibujo,
                            'AnchoPeineTrama' => $validated['TramaAnchoPeine'] ?? null,
                            'LogLuchaTotal' => $validated['LongitudLuchaTot'] ?? null,
                            'Total' => $validated['TotalPasadasDibujo'],
                            'FechaCumplimiento' => now()->format('Y-m-d H:i:s'),
                        ], $detallePayload, $pasadasPayload);
	                    
                        // Obtener columnas disponibles en ReqModelosCodificados
                        $columnasModelo = Schema::getColumnListing($registroModelo->getTable());
	                    
                        // Actualizar solo las columnas que existen
                        foreach ($payloadModelo as $column => $value) {
                            if (!in_array($column, $columnasModelo, true)) {
                                continue;
                            }
                            $registroModelo->setAttribute($column, $value);
                        }
	                    
                        $registroModelo->save();
	                    
                        Log::info('Registro actualizado en ReqModelosCodificados', [
                            'Id' => $registroModelo->Id,
                            'TamanoClave' => $registroModelo->TamanoClave,
                            'SalonTejidoId' => $registroModelo->SalonTejidoId,
                        ]);

                        $ordenTejido = $registroModelo->OrdenTejido ?: $validated['NoProduccion'];
                        $salonTejido = $registroModelo->SalonTejidoId;
                        if ($ordenTejido && $salonTejido) {
                            $programas = \App\Models\ReqProgramaTejido::where('NoProduccion', $ordenTejido)
                                ->where('SalonTejidoId', $salonTejido)
                                ->get();

                            if ($programas->isNotEmpty()) {
                                $columnasPrograma = Schema::getColumnListing($programas->first()->getTable());
                                $payloadPrograma = [
                                    'CalibreTrama' => $registroModelo->CalibreTrama,
                                    'CalibreTrama2' => $registroModelo->CalibreTrama2,
                                    'FibraTrama' => $registroModelo->FibraId,
                                    'PasadasTrama' => $registroModelo->PasadasTramaFondoC1,
                                    'PasadasComb1' => $registroModelo->PasadasComb1,
                                    'PasadasComb2' => $registroModelo->PasadasComb2,
                                    'PasadasComb3' => $registroModelo->PasadasComb3,
                                    'PasadasComb4' => $registroModelo->PasadasComb4,
                                    'PasadasComb5' => $registroModelo->PasadasComb5,
                                    'CodColorTrama' => $registroModelo->CodColorTrama,
                                    'ColorTrama' => $registroModelo->ColorTrama,
                                    'CalibreComb1' => $registroModelo->CalibreComb1,
                                    'CalibreComb12' => $registroModelo->CalibreComb12,
                                    'FibraComb1' => $registroModelo->FibraComb1,
                                    'CodColorComb1' => $registroModelo->CodColorC1,
                                    'NombreCC1' => $registroModelo->NomColorC1,
                                    'CalibreComb2' => $registroModelo->CalibreComb2,
                                    'CalibreComb22' => $registroModelo->CalibreComb22,
                                    'FibraComb2' => $registroModelo->FibraComb2,
                                    'CodColorComb2' => $registroModelo->CodColorC2,
                                    'NombreCC2' => $registroModelo->NomColorC2,
                                    'CalibreComb3' => $registroModelo->CalibreComb3,
                                    'CalibreComb32' => $registroModelo->CalibreComb32,
                                    'FibraComb3' => $registroModelo->FibraComb3,
                                    'CodColorComb3' => $registroModelo->CodColorC3,
                                    'NombreCC3' => $registroModelo->NomColorC3,
                                    'CalibreComb4' => $registroModelo->CalibreComb4,
                                    'CalibreComb42' => $registroModelo->CalibreComb42,
                                    'FibraComb4' => $registroModelo->FibraComb4,
                                    'CodColorComb4' => $registroModelo->CodColorC4,
                                    'NombreCC4' => $registroModelo->NomColorC4,
                                    'CalibreComb5' => $registroModelo->CalibreComb5,
                                    'CalibreComb52' => $registroModelo->CalibreComb52,
                                    'FibraComb5' => $registroModelo->FibraComb5,
                                    'CodColorComb5' => $registroModelo->CodColorC5,
                                    'NombreCC5' => $registroModelo->NomColorC5,
                                ];

                                foreach ($programas as $programa) {
                                    foreach ($payloadPrograma as $column => $value) {
                                        if (!in_array($column, $columnasPrograma, true)) {
                                            continue;
                                        }
                                        $programa->setAttribute($column, $value);
                                    }
                                    $programa->save();
                                }

                                Log::info('Registro actualizado en ReqProgramaTejido', [
                                    'NoProduccion' => $ordenTejido,
                                    'SalonTejidoId' => $salonTejido,
                                    'total' => $programas->count(),
                                ]);
                            }
                        }
                    }
                }

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
