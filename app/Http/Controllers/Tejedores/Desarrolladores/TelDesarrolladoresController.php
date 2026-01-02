<?php
namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Helpers\TelDesarrolladoresHelper;
use App\Models\TelTelaresOperador;
use App\Models\catDesarrolladoresModel;
use App\Models\catcodificados\CatCodificados;
use App\Models\ReqModelosCodificados;
use App\Models\UrdCatJulios;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\ReqProgramaTejido;

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
        return UrdCatJulios::where('Departamento', 'engomado')
            ->whereNotNull('NoJulio')
            ->select('NoJulio')
            ->distinct()
            ->orderBy('NoJulio')
            ->get();
    }

    public function obtenerProducciones(Request $request, $telarId)
    {
        try {
            $producciones = ReqProgramaTejido::where('NoTelarId', $telarId)
                ->where('EnProceso', 0)
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
            $ordenData = ReqProgramaTejido::where('NoProduccion', $noProduccion)->first();

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
                    'PasadasTrama'
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
	        $datosProduccion = ReqProgramaTejido::where('NoTelarId', $telarId)
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

        private function actualizarReqModelosDesdePrograma(ReqProgramaTejido $programa): void
        {
            $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
            $noTelarId = trim((string) ($programa->NoTelarId ?? ''));

            if ($noProduccion === '' || $noTelarId === '') {
                return;
            }

            $modelo = new CatCodificados();
            $table = $modelo->getTable();
            $columns = Schema::getColumnListing($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }

            if (!$hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (!$registroCodificado) {
                return;
            }

            $payload = [
                'Pedido' => $programa->TotalPedido,
                'Produccion' => $programa->Produccion,
                'Saldos' => $programa->SaldoPedido,
                'OrdCompartida' => $programa->OrdCompartida,
                'OrdCompartidaLider' => $programa->OrdCompartidaLider,
            ];

            foreach ($payload as $column => $value) {
                if (!in_array($column, $columns, true)) {
                    continue;
                }
                $registroCodificado->setAttribute($column, $value);
            }

            if ($registroCodificado->isDirty()) {
                $registroCodificado->save();
            }
        }

        private function moverRegistroConReprogramar(ReqProgramaTejido $registro, $todosLosRegistros, string $reprogramar, string $salonTejido, string $noTelarId): array
        {
            $idsAfectados = [];

            try {
                // Validar que hay al menos 2 registros
                if ($todosLosRegistros->count() < 2) {
                    return $idsAfectados;
                }

                $primero = $todosLosRegistros->first();
                $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
                if (!$inicioOriginal) {
                    return $idsAfectados;
                }

                // Encontrar el índice del registro a mover
                $idx = $todosLosRegistros->search(function ($r) use ($registro) {
                    return $r->Id === $registro->Id;
                });

                if ($idx === false) {
                    return $idsAfectados;
                }

                // Actualizar CatCodificados antes de mover
                $this->actualizarReqModelosDesdePrograma($registro);

                // Reordenar colección en memoria
                $registroMovido = $todosLosRegistros->splice($idx, 1)->first();

                // Calcular la posición de inserción después de remover el elemento
                if ($reprogramar == '1') {
                    // Mover al siguiente registro
                    $posicionAjustada = $idx + 1;
                    // Si ya era el último o penúltimo, insertar al final
                    if ($posicionAjustada > $todosLosRegistros->count()) {
                        $posicionAjustada = $todosLosRegistros->count();
                    }
                } elseif ($reprogramar == '2') {
                    // Mover al último registro
                    $posicionAjustada = $todosLosRegistros->count();
                }

                $todosLosRegistros->splice($posicionAjustada, 0, [$registroMovido]);
                $registrosReordenados = $todosLosRegistros->values();

                // Recalcular fechas para toda la secuencia
                [$updates] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal, true);

                // Actualizar solo los registros de este telar
                foreach ($updates as $idU => $data) {
                    DB::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                    $idsAfectados[] = (int) $idU;
                }

                $registro->EnProceso = 0;
                $registro->save();

            } catch (Exception $e) {
                Log::error('Error al mover registro con Reprogramar: ' . $e->getMessage(), [
                    'id' => $registro->Id ?? null,
                    'reprogramar' => $reprogramar,
                ]);
            }

            return $idsAfectados;
        }

        private function moverRegistroEnProceso(ReqProgramaTejido $registroActualizado): void
        {
            $salonTejido = $registroActualizado->SalonTejidoId;
            $noTelarId = $registroActualizado->NoTelarId;

            if (!$salonTejido || !$noTelarId) {
                return;
            }

            $dispatcher = ReqProgramaTejido::getEventDispatcher();
            $idsAfectados = [];

            try {
                DB::transaction(function () use ($registroActualizado, $salonTejido, $noTelarId, &$idsAfectados) {
                    $registrosEnProceso = ReqProgramaTejido::query()
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->where('EnProceso', 1)
                        ->where('Id', '!=', $registroActualizado->Id)
                        ->lockForUpdate()
                        ->get();

                    // Obtener todos los registros del telar para poder mover si es necesario
                    $todosLosRegistros = ReqProgramaTejido::query()
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->orderBy('FechaInicio', 'asc')
                        ->lockForUpdate()
                        ->get();

                    foreach ($registrosEnProceso as $registroEnProceso) {
                        $reprogramar = $registroEnProceso->Reprogramar;

                        // Si tiene Reprogramar activo, aplicar lógica de mover
                        if (!empty($reprogramar) && ($reprogramar == '1' || $reprogramar == '2')) {
                            // Recargar todos los registros antes de mover para tener el estado actual
                            $todosLosRegistros = ReqProgramaTejido::query()
                                ->where('SalonTejidoId', $salonTejido)
                                ->where('NoTelarId', $noTelarId)
                                ->orderBy('FechaInicio', 'asc')
                                ->lockForUpdate()
                                ->get();

                            $idsMovidos = $this->moverRegistroConReprogramar($registroEnProceso, $todosLosRegistros, $reprogramar, $salonTejido, $noTelarId);
                            $idsAfectados = array_merge($idsAfectados, $idsMovidos);
                        } else {
                            // Si no tiene Reprogramar, aplicar lógica actual (actualizar CatCodificados y eliminar)
                            $this->actualizarReqModelosDesdePrograma($registroEnProceso);
                            $registroEnProceso->delete();
                        }
                    }

                    ReqProgramaTejido::query()
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->update(['EnProceso' => 0]);

                    ReqProgramaTejido::query()
                        ->where('Id', $registroActualizado->Id)
                        ->update(['EnProceso' => 1]);

                    $registros = ReqProgramaTejido::query()
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->orderBy('FechaInicio', 'asc')
                        ->lockForUpdate()
                        ->get();

                    if ($registros->isEmpty()) {
                        return;
                    }

                    $registroEnLista = $registros->firstWhere('Id', $registroActualizado->Id) ?: $registroActualizado;
                    $ordenados = collect([$registroEnLista])
                        ->merge($registros->filter(function ($registro) use ($registroEnLista) {
                            return $registro->Id !== $registroEnLista->Id;
                        }))
                        ->values();

                    $inicioOriginal = null;
                    if (!empty($registroEnLista->FechaInicio)) {
                        $inicioOriginal = Carbon::parse($registroEnLista->FechaInicio);
                    } else {
                        $primeroConFecha = $ordenados->first(function ($registro) {
                            return !empty($registro->FechaInicio);
                        });
                        if ($primeroConFecha) {
                            $inicioOriginal = Carbon::parse($primeroConFecha->FechaInicio);
                        }
                    }

                    if (!$inicioOriginal) {
                        return;
                    }

                    ReqProgramaTejido::unsetEventDispatcher();

                    [$updates] = DateHelpers::recalcularFechasSecuencia($ordenados, $inicioOriginal, true);

                    foreach ($updates as $idU => $dataU) {
                        DB::table('ReqProgramaTejido')->where('Id', $idU)->update($dataU);
                        $idsAfectados[] = (int) $idU;
                    }
                });
            } catch (Exception $e) {
                Log::error('Error al mover registro en proceso: ' . $e->getMessage(), [
                    'salonTejidoId' => $salonTejido,
                    'noTelarId' => $noTelarId,
                    'registroId' => $registroActualizado->Id,
                ]);
            } finally {
                if ($dispatcher) {
                    ReqProgramaTejido::setEventDispatcher($dispatcher);
                }
            }

            if (!empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver();
                $modelos = ReqProgramaTejido::query()
                    ->whereIn('Id', $idsAfectados)
                    ->get();

                foreach ($modelos as $modelo) {
                    $observer->saved($modelo);
                }
            }

            $registroParaModelo = ReqProgramaTejido::query()
                ->where('Id', $registroActualizado->Id)
                ->first();
            if ($registroParaModelo) {
                $this->actualizarReqModelosDesdePrograma($registroParaModelo);
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
                $ordenData = ReqProgramaTejido::where('NoProduccion', $validated['NoProduccion'])->first();
                $detallePayload = $this->buildDetallePayloadFromOrden($ordenData);
                $minutosCambio = $this->calcularMinutosCambio(
                    $validated['HoraInicio'] ?? null,
                    $validated['HoraFinal'] ?? null
                );

                $fechaInicioProgramada = null;
                if (!empty($validated['HoraFinal'])) {
                    try {
                        $horaFinalCarbon = Carbon::createFromFormat('H:i', $validated['HoraFinal']);
                        $fechaInicioProgramada = Carbon::today()
                            ->setTimeFromTimeString($horaFinalCarbon->format('H:i'))
                            ->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        Log::warning('No se pudo construir FechaInicio para ReqProgramaTejido', [
                            'horaFinal' => $validated['HoraFinal'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $pasadasPayload = [];
                $pasadasFromRequest = $validated['pasadas'] ?? [];
                if (is_array($pasadasFromRequest) && count($pasadasFromRequest) > 0) {
                    foreach ($pasadasFromRequest as $key => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        // Mapear 'PasadasTrama' a 'PasadasTramaFondoC1' para CatCodificados
                        if ($key === 'PasadasTrama') {
                            $pasadasPayload['PasadasTramaFondoC1'] = (int) $value;
                        } else {
                            $pasadasPayload[$key] = (int) $value;
                        }
                    }
                } elseif ($ordenData) {
                    $tramaValue = data_get($ordenData, 'PasadasTrama');
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
                    $permitirActualizarRelacionados = true;
                    if ($registroModelo) {
                        $codigoPrevioModelo = trim((string) ($registroModelo->CodigoDibujo ?? $registroModelo->CodificacionModelo ?? ''));
                        if ($codigoPrevioModelo !== '') {
                            $permitirActualizarRelacionados = false;
                        }
                    }

                    if ($registroModelo && $permitirActualizarRelacionados && $codigoDibujo !== '') {
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
                        $ordenTejido = $registroModelo->OrdenTejido ?: $validated['NoProduccion'];
                        $salonTejido = $registroModelo->SalonTejidoId;
                        if ($ordenTejido && $salonTejido) {
                            $programas = ReqProgramaTejido::where('NoProduccion', $ordenTejido)
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
                                if ($fechaInicioProgramada) {
                                    $payloadPrograma['FechaInicio'] = $fechaInicioProgramada;
                                }
                                foreach ($programas as $programa) {
                                    foreach ($payloadPrograma as $column => $value) {
                                        if (!in_array($column, $columnasPrograma, true)) {
                                            continue;
                                        }
                                        $programa->setAttribute($column, $value);
                                    }
                                    $programa->save();
                                }
                                $programaEnTelar = $programas->firstWhere('NoTelarId', $validated['NoTelarId']);
                                if ($programaEnTelar) {
                                    $this->moverRegistroEnProceso($programaEnTelar);

                                    // Enviar notificación a Telegram después de completar todo el proceso
                                    $this->enviarNotificacionTelegram($validated, $programaEnTelar, $codigoDibujo);
                                }
                            }
                        }
                    }
                }
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

    /**
     * Enviar notificación a Telegram cuando se complete el proceso de desarrollador
     */
    private function enviarNotificacionTelegram(array $validated, ReqProgramaTejido $programa, string $codigoDibujo): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (empty($botToken) || empty($chatId)) {
                Log::warning('No se pudo enviar notificación a Telegram: credenciales no configuradas');
                return;
            }

            // Construir el mensaje con formato
            $mensaje = " *PROCESO DE DESARROLLADOR COMPLETADO* \n\n";
            $mensaje .= " *Telar:* {$validated['NoTelarId']}\n";
            $mensaje .= " *Producción:* {$validated['NoProduccion']}\n";

            if (!empty($validated['Desarrollador'])) {
                $mensaje .= " *Desarrollador:* {$validated['Desarrollador']}\n";
            }

            $mensaje .= " *Código Dibujo:* {$codigoDibujo}\n";
            $mensaje .= " *Total Pasadas:* {$validated['TotalPasadasDibujo']}\n";

            if (!empty($validated['NumeroJulioRizo'])) {
                $mensaje .= " *Julio Rizo:* {$validated['NumeroJulioRizo']}\n";
            }

            if (!empty($validated['NumeroJulioPie'])) {
                $mensaje .= " *Julio Pie:* {$validated['NumeroJulioPie']}\n";
            }

            if (!empty($validated['HoraInicio'])) {
                $mensaje .= " *Hora Inicio:* {$validated['HoraInicio']}\n";
            }

            if (!empty($validated['HoraFinal'])) {
                $mensaje .= " *Hora Final:* {$validated['HoraFinal']}\n";
            }

            if (isset($validated['EficienciaInicio']) && $validated['EficienciaInicio'] !== null) {
                $mensaje .= " *Eficiencia Inicio:* {$validated['EficienciaInicio']}%\n";
            }

            if (isset($validated['EficienciaFinal']) && $validated['EficienciaFinal'] !== null) {
                $mensaje .= " *Eficiencia Final:* {$validated['EficienciaFinal']}%\n";
            }

            if (!empty($programa->FechaInicio)) {
                $fechaInicio = Carbon::parse($programa->FechaInicio)->format('d/m/Y H:i');
                $mensaje .= "📅 *Fecha Inicio Programada:* {$fechaInicio}\n";
            }

            if (!empty($programa->FechaFinal)) {
                $fechaFinal = Carbon::parse($programa->FechaFinal)->format('d/m/Y H:i');
                $mensaje .= "📅 *Fecha Final Programada:* {$fechaFinal}\n";
            }

            $mensaje .= "\n *Estado:* Registro actualizado y puesto en proceso";
            $mensaje .= "\n *Fechas:* Actualizadas para el telar {$validated['NoTelarId']}";

            // Enviar mensaje a Telegram
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'Markdown'
            ]);

            if ($response->successful()) {
                $data = $response->json();
            }
            } catch (Exception $e) {
                Log::error('Error al enviar notificación de desarrollador a Telegram', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'telar' => $validated['NoTelarId'],
                ]);
            }
    }
}
