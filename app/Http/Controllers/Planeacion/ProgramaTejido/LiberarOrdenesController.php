<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiberarOrdenesController extends Controller
{
    /** TwSalon en AX para BOM CRUDO de tejido (solo SMIT / Jacquard). */
    private const BOM_CRUDO_TW_SALONES = ['SMIT', 'JACQUARD', 'JACUARD'];

    /**
     * Muestra los registros de ReqProgramaTejido que no tienen orden de producción
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        try {
            // Obtener el rango de días del parámetro o de la sesión
            $dias = $request->input('dias');
            if ($dias !== null) {
                // Validar y guardar en sesión
                $dias = floatval($dias);
                if ($dias < 0 || $dias > 999.999) {
                    $dias = 10.999;
                }
                // Redondear a 3 decimales
                $dias = round($dias, 3);
                session(['liberar_ordenes_dias' => $dias]);
            } else {
                // Obtener de la sesión o usar valor por defecto
                $dias = session('liberar_ordenes_dias', 10.999);
            }
            // Obtener registros que NO tienen orden de producción
            $registros = ReqProgramaTejido::query()
                ->where(function ($query) {
                    $query->whereNull('NoProduccion')
                        ->orWhere('NoProduccion', '');
                })
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->orderBy('FechaInicio')
                ->get();

            // Aplicar fórmula INN: =SI(FechaInicio <= (HOY()+dias),HOY(),"")
            $hoy = Carbon::now()->startOfDay();
            // Calcular fechaFormula = HOY + días configurados por el usuario
            $fechaFormula = $hoy->copy()->addDays($dias);

            $registros->each(function ($registro) use ($hoy, $fechaFormula) {
                if ($registro->FechaInicio) {
                    try {
                        $fechaInicio = $registro->FechaInicio instanceof Carbon
                            ? $registro->FechaInicio->copy()->startOfDay()
                            : Carbon::parse($registro->FechaInicio)->startOfDay();

                        $cumple = $fechaInicio->lte($fechaFormula);
                        // Si FechaInicio <= fechaFormula (HOY + días configurados), asignar HOY, sino null
                        if ($cumple) {
                            $registro->ProgramadoCalculado = $hoy->copy();
                        } else {
                            $registro->ProgramadoCalculado = null;
                        }
                    } catch (\Exception $e) {
                        Log::error('Error al procesar fecha', [
                            'registro_id' => $registro->Id,
                            'error' => $e->getMessage(),
                        ]);
                        $registro->ProgramadoCalculado = null;
                    }
                } else {
                    $registro->ProgramadoCalculado = null;
                }
            });

            // Filtrar solo los registros que tienen fecha INN (ProgramadoCalculado no nulo)
            // y que NO tengan valor en NoExisteBase
            $registros = $registros->filter(function ($registro) {
                return $registro->ProgramadoCalculado !== null
                    && (empty($registro->NoExisteBase) || is_null($registro->NoExisteBase));
            })->values();

            // Calcular prioridad del registro anterior para cada registro
            // Prioridad = "CHECAR + NombreProducto" del registro anterior en ReqProgramaTejido
            // Buscar el registro anterior que tenga el mismo NoTelarId según el ordenamiento original
            $registros->each(function ($registro) {
                $noTelarId = $registro->NoTelarId ?? null;
                $salonTejidoId = $registro->SalonTejidoId ?? '';
                $fechaInicio = $registro->FechaInicio ?? null;
                $idActual = $registro->Id ?? null;

                if (! $noTelarId || ! $idActual) {
                    $registro->PrioridadAnterior = '';

                    return;
                }

                // Buscar el registro anterior en ReqProgramaTejido con el mismo NoTelarId
                // El ordenamiento original es: SalonTejidoId, NoTelarId, FechaInicio (asc)
                $query = ReqProgramaTejido::query()
                    ->select(['Id', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'FechaInicio'])
                    ->where('NoTelarId', $noTelarId)
                    ->where('SalonTejidoId', $salonTejidoId);

                // Construir condiciones para encontrar el registro anterior
                if ($fechaInicio) {
                    // Buscar registros con FechaInicio menor, o si es igual, con Id menor
                    $query->where(function ($q) use ($fechaInicio, $idActual) {
                        $q->where('FechaInicio', '<', $fechaInicio)
                            ->orWhere(function ($q2) use ($fechaInicio, $idActual) {
                                $q2->where('FechaInicio', '=', $fechaInicio)
                                    ->where('Id', '<', $idActual);
                            });
                    });
                } else {
                    // Si no tiene FechaInicio, buscar por Id menor
                    $query->where('Id', '<', $idActual);
                }

                // Ordenar por FechaInicio DESC y Id DESC para obtener el registro más cercano anterior
                $registroAnterior = $query->orderByDesc('FechaInicio')
                    ->orderByDesc('Id')
                    ->first();

                if ($registroAnterior && ! empty($registroAnterior->NombreProducto)) {
                    $nombreProductoAnterior = $registroAnterior->NombreProducto;
                    // Formar prioridad: "CHECAR + NombreProducto"
                    $prioridadFormada = 'SALDAR '.$nombreProductoAnterior;
                    $registro->PrioridadAnterior = $prioridadFormada;
                } else {
                    $registro->PrioridadAnterior = '';
                }
            });

            // Calcular campos en la carga para mostrarlos en la vista
            $registros->each(function ($registro) {
                $pCrudo = $registro->PesoCrudo ?? null;
                $tiras = $registro->NoTiras ?? null;
                $pesoRollo = $this->obtenerPesoRollo($registro) ?? 41.5;
                $repeticiones = null;
                if ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    $repeticiones = (int) ceil((($pesoRollo / (float) $pCrudo) / (float) $tiras) * 1000);
                }

                $saldoMarbeteInt = 0;
                if (isset($registro->SaldoMarbete) && is_numeric($registro->SaldoMarbete)) {
                    $saldoMarbeteInt = (int) ceil((float) $registro->SaldoMarbete);
                } else {
                    $cantidadProducir = $registro->SaldoPedido ?? null;
                    $saldoMarbete = 0;
                    if ($cantidadProducir !== null && $tiras && $repeticiones !== null &&
                        is_numeric($cantidadProducir) && is_numeric($tiras) && is_numeric($repeticiones) &&
                        $tiras > 0 && $repeticiones > 0) {
                        $saldoMarbete = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;
                    }
                    if (is_numeric($saldoMarbete)) {
                        $saldoMarbeteInt = (int) ceil((float) $saldoMarbete);
                    }
                }
                // MtsRollo: fórmula = medida de largo * repeticiones (convertir cm a metros)
                // MtsRollo se mantiene como decimal sin redondear
                $mtsRollo = null;
                if (isset($registro->MtsRollo) && is_numeric($registro->MtsRollo)) {
                    $mtsRollo = (float) $registro->MtsRollo;
                } else {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo) ? (float) $largo : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                            // Sin redondear para mantener todos los decimales
                            $mtsRollo = (float) (($largoNum * $repeticiones) / 100);
                        }
                    }
                }

                $pzasRollo = null;
                if (isset($registro->PzasRollo) && is_numeric($registro->PzasRollo)) {
                    $pzasRollo = (float) $registro->PzasRollo;
                } else {
                    if ($repeticiones !== null && $tiras && is_numeric($repeticiones) && is_numeric($tiras) && $repeticiones > 0 && $tiras > 0) {
                        $pzasRollo = round($repeticiones * $tiras, 0);
                    }
                }

                $totalRollos = null;
                // Nueva fórmula: TotalRollos = ceil(totalPedido / pzasRollo)
                // Esto calcula cuántos rollos se necesitan para cumplir el pedido
                $totalPedido = $registro->SaldoPedido ?? null;

                // Siempre intentar calcular con la fórmula si hay datos disponibles
                if ($pzasRollo !== null && $totalPedido !== null &&
                    is_numeric($pzasRollo) && is_numeric($totalPedido) &&
                    $pzasRollo > 0 && $totalPedido > 0) {
                    // Calcular con la nueva fórmula: totalPedido / pzasRollo
                    $totalRollos = (float) ceil((float) $totalPedido / (float) $pzasRollo);
                } else {
                    // Si no se puede calcular, usar el valor existente o fallback
                    if (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos) && $registro->TotalRollos > 0) {
                        $totalRollos = (float) ceil((float) $registro->TotalRollos);
                    } else {
                        // Fallback a saldoMarbeteInt si no hay datos
                        $totalRollos = $saldoMarbeteInt > 0 ? (float) $saldoMarbeteInt : null;
                    }
                }

                $totalPzas = null;
                if (isset($registro->TotalPzas) && is_numeric($registro->TotalPzas)) {
                    $totalPzas = (float) $registro->TotalPzas;
                } else {
                    if ($totalRollos !== null && $pzasRollo !== null && is_numeric($totalRollos) && is_numeric($pzasRollo)) {
                        $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
                    }
                }

                $registro->Repeticiones = $repeticiones;
                $registro->PesoRollo = $pesoRollo;
                $registro->SaldoMarbete = $saldoMarbeteInt;
                $registro->MtsRollo = $mtsRollo;
                $registro->PzasRollo = $pzasRollo;
                $registro->TotalRollos = $totalRollos;
                $registro->TotalPzas = $totalPzas;

                // Densidad: fórmula = peso_crudo / ((ancho * largo) / 10)
                $densidad = null;
                $peso = $registro->PesoCrudo ?? null;
                $ancho = $registro->Ancho ?? null;
                $largo = $registro->LargoCrudo ?? null;

                if ($peso !== null && $ancho !== null && $largo !== null &&
                    is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                    $ancho > 0 && $largo > 0) {
                    // Limpiar largo si tiene texto (ej: "50 Cms.")
                    $largoLimpio = is_numeric($largo)
                        ? (float) $largo
                        : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

                    // Fórmula: densidad = peso_crudo / ((ancho * largo) / 10)
                    $denominador = ($ancho * $largoLimpio) / 10;
                    if ($denominador > 0) {
                        $densidad = round((float) $peso / $denominador, 4);
                    }
                }
                $registro->Densidad = $densidad;

                $bomOpciones = $this->resolverBomCrudoOpciones($registro);
                $registro->BomOpciones = $bomOpciones;

                if (count($bomOpciones) === 1) {
                    $registro->BomId = trim((string) $bomOpciones[0]['bomId']);
                    $registro->BomName = trim((string) $bomOpciones[0]['bomName']);
                } else {
                    $registro->BomId = null;
                    $registro->BomName = null;
                }
            });

            // Obtener opciones de hilos para el select desde INVENTTABLE (TwTipoHiloId)
            $hilosOptions = DB::connection('sqlsrv_ti')
                ->table('INVENTTABLE')
                ->select('TwTipoHiloId')
                ->whereNotNull('TwTipoHiloId')
                ->where('TwTipoHiloId', '!=', '')
                ->distinct()
                ->pluck('TwTipoHiloId')
                ->filter(function ($value) {
                    return ! empty(trim((string) $value));
                })
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return view('modulos.programa-tejido.liberar-ordenes.index', compact('registros', 'dias', 'hilosOptions'));
        } catch (\Throwable $e) {

            return view('modulos.programa-tejido.liberar-ordenes.index', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Libera las órdenes seleccionadas: genera folio, actualiza campos y devuelve Excel
     */
    public function liberar(Request $request)
    {
        set_time_limit(0);

        $data = $request->validate([
            'registros' => 'required|array|min:1',
            'registros.*.id' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
            'registros.*.prioridad' => 'nullable|string|max:150',
            'registros.*.bomId' => 'required|string|max:30',
            'registros.*.bomName' => 'required|string|max:100',
            'registros.*.hiloAX' => 'nullable|string|max:30',
            'registros.*.pesoRollo' => 'nullable|numeric|min:0',
            'registros.*.mtsRollo' => 'nullable|numeric',
            'registros.*.pzasRollo' => 'nullable|numeric',
            'registros.*.totalRollos' => 'nullable|numeric',
            'registros.*.totalPzas' => 'nullable|numeric',
            'registros.*.repeticiones' => 'nullable|numeric',
            'registros.*.saldoMarbete' => 'nullable|numeric',
            'registros.*.densidad' => 'nullable|numeric',
            'registros.*.observaciones' => 'nullable|string|max:200',
            'registros.*.cambioRepaso' => ['nullable', 'string', Rule::in(['SI', 'NO', 'Si', 'No', 'si', 'no'])],
            'registros.*.combinaTram' => 'nullable|string|max:80',
            'registros.*.noProduccion' => 'nullable|string|max:15',
            'registros.*.codigoDibujo' => 'nullable|string|max:500',
        ], [
            'registros.required' => 'Debes seleccionar al menos un registro.',
            'registros.*.id.exists' => 'Uno de los registros seleccionados no existe.',
            'registros.*.bomId.required' => 'L.Mat es obligatorio en cada registro seleccionado.',
            'registros.*.bomName.required' => 'Nombre L.Mat es obligatorio en cada registro seleccionado.',
        ]);

        $registrosInput = collect($data['registros'])->unique('id');

        if ($registrosInput->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar al menos un registro válido.',
            ], 422);
        }

        $dias = session('liberar_ordenes_dias', 10.999);
        $hoy = Carbon::now()->startOfDay();
        $fechaFormula = $hoy->copy()->addDays($dias);

        DB::beginTransaction();

        try {
            $actualizados = collect();
            $foliosUsadosEnLote = [];

            foreach ($registrosInput as $item) {
                $id = (int) ($item['id'] ?? 0);
                if (! $id) {
                    continue;
                }

                $prioridad = trim((string) ($item['prioridad'] ?? ''));

                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (! $registro) {
                    continue;
                }

                // Generar folio o usar el valor manual ingresado por el usuario
                $noProduccionManual = trim((string) ($item['noProduccion'] ?? ''));
                $folio = $noProduccionManual !== '' ? $noProduccionManual : FolioHelper::obtenerSiguienteFolio('Planeacion', 5);

                $folio = trim((string) $folio);
                if ($folio === '') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo obtener un número de orden válido.',
                    ], 422);
                }

                if (isset($foliosUsadosEnLote[$folio])) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'El número de orden "'.$folio.'" está duplicado entre los registros seleccionados.',
                    ], 422);
                }

                $errorUnico = $this->validarOrdenTejidoUnicoParaLiberacion($folio, $registro);
                if ($errorUnico !== null) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $errorUnico,
                    ], 422);
                }

                $foliosUsadosEnLote[$folio] = true;

                $programado = $this->calcularFechaProgramada($registro, $hoy, $fechaFormula);

                // Configurar valores básicos del registro
                $registro->Prioridad = $prioridad !== '' ? $prioridad : null;
                $registro->NoProduccion = $folio;
                if ($programado) {
                    $registro->Programado = $programado;
                }

                // Calcular campos de producción
                $pCrudo = $registro->PesoCrudo ?? null;
                $tiras = $registro->NoTiras ?? null;

                // Repeticiones siempre se calcula con formula; no se toma de ReqProgramaTejido.
                $repeticiones = null;
                if ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    $pesoRollo = $this->obtenerPesoRollo($registro) ?? 41.5;
                    if (isset($item['pesoRollo']) && $item['pesoRollo'] !== null && $item['pesoRollo'] !== '' && is_numeric($item['pesoRollo'])) {
                        $pesoRollo = (float) $item['pesoRollo'];
                    }
                    $repeticiones = (int) ceil((($pesoRollo / (float) $pCrudo) / (float) $tiras) * 1000);
                }

                // SaldoMarbete: usar del request, existente o calcular
                $saldoMarbeteInt = $item['saldoMarbete'] ?? $registro->SaldoMarbete;
                if ($saldoMarbeteInt !== null && is_numeric($saldoMarbeteInt)) {
                    $saldoMarbeteInt = (int) ceil((float) $saldoMarbeteInt);
                } elseif ($saldoMarbeteInt === null) {
                    $cantidadProducir = $registro->SaldoPedido ?? null;
                    $saldoMarbeteCalc = 0;
                    if ($cantidadProducir !== null && $tiras && $repeticiones !== null &&
                        is_numeric($cantidadProducir) && is_numeric($tiras) && is_numeric($repeticiones) &&
                        $tiras > 0 && $repeticiones > 0) {
                        $saldoMarbeteCalc = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;
                    }
                    if (is_numeric($saldoMarbeteCalc)) {
                        $saldoMarbeteInt = (int) ceil((float) $saldoMarbeteCalc);
                    }
                }

                // MtsRollo: usar del request, existente o calcular
                // MtsRollo se mantiene como decimal sin redondear
                $mtsRollo = $item['mtsRollo'] ?? $registro->MtsRollo;
                if ($mtsRollo === null) {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo)
                            ? (float) $largo
                            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                            // Sin redondear para mantener todos los decimales
                            $mtsRollo = (float) (($largoNum * $repeticiones) / 100);
                        }
                    }
                }

                // PzasRollo: usar del request, existente o calcular
                $pzasRollo = $item['pzasRollo'] ?? $registro->PzasRollo;
                if ($pzasRollo === null && $repeticiones !== null && $tiras &&
                    is_numeric($repeticiones) && is_numeric($tiras) && $repeticiones > 0 && $tiras > 0) {
                    $pzasRollo = round($repeticiones * $tiras, 0);
                }

                // TotalRollos: priorizar valor del request, sino calcular con nueva fórmula
                $totalRollos = $item['totalRollos'] ?? null;
                if ($totalRollos !== null && is_numeric($totalRollos) && $totalRollos > 0) {
                    // Si viene del request, usar ese valor redondeado hacia arriba
                    $totalRollos = (float) ceil((float) $totalRollos);
                } else {
                    // Nueva fórmula: TotalRollos = ceil(totalPedido / pzasRollo)
                    // Esto calcula cuántos rollos se necesitan para cumplir el pedido
                    $totalPedido = $registro->SaldoPedido ?? null;
                    if ($pzasRollo !== null && $totalPedido !== null &&
                        is_numeric($pzasRollo) && is_numeric($totalPedido) &&
                        $pzasRollo > 0 && $totalPedido > 0) {
                        // Calcular con la nueva fórmula: totalPedido / pzasRollo
                        $totalRollos = (float) ceil((float) $totalPedido / (float) $pzasRollo);
                    } elseif (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos) && $registro->TotalRollos > 0) {
                        // Si no se puede calcular, usar el valor existente redondeado hacia arriba
                        $totalRollos = (float) ceil((float) $registro->TotalRollos);
                    } else {
                        // Fallback a saldoMarbeteInt
                        $totalRollos = $saldoMarbeteInt > 0 ? (float) $saldoMarbeteInt : null;
                    }
                }

                // TotalPzas: usar del request, existente o calcular
                $totalPzas = $item['totalPzas'] ?? $registro->TotalPzas;
                if ($totalPzas === null && $totalRollos !== null && $pzasRollo !== null &&
                    is_numeric($totalRollos) && is_numeric($pzasRollo)) {
                    $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
                }

                // Asignar campos calculados
                $registro->Repeticiones = $repeticiones;
                $registro->SaldoMarbete = $saldoMarbeteInt ?? 0;
                $registro->MtsRollo = $mtsRollo;
                $registro->PzasRollo = $pzasRollo;
                $registro->TotalRollos = $totalRollos;
                $registro->TotalPzas = $totalPzas;

                // Aplicar valores del request con lógica de fallback
                // Campos de texto
                $camposTexto = [
                    'combinaTram' => 'CombinaTram',
                    'bomId' => 'BomId',
                    'bomName' => 'BomName',
                    'hiloAX' => 'HiloAX',
                    'observaciones' => 'Observaciones',
                    'cambioRepaso' => 'CambioHilo',
                ];
                foreach ($camposTexto as $campoRequest => $campoBD) {
                    $valor = $item[$campoRequest] ?? null;
                    if ($valor !== null && $valor !== '') {
                        $valorNormalizado = trim((string) $valor);
                        if ($campoBD === 'CambioHilo') {
                            $valorNormalizado = strtoupper($valorNormalizado) === 'SI' ? 'SI' : 'NO';
                        }
                        $registro->$campoBD = $valorNormalizado;
                    } elseif (empty($registro->$campoBD)) {
                        $registro->$campoBD = $campoBD === 'CambioHilo' ? 'NO' : null;
                    }
                }

                // Campos numéricos
                if (isset($item['mtsRollo']) && $item['mtsRollo'] !== null && $item['mtsRollo'] !== '') {
                    $registro->MtsRollo = (float) $item['mtsRollo'];
                }
                if (isset($item['pzasRollo']) && $item['pzasRollo'] !== null && $item['pzasRollo'] !== '') {
                    $registro->PzasRollo = (float) $item['pzasRollo'];
                }
                if (isset($item['totalRollos']) && $item['totalRollos'] !== null && $item['totalRollos'] !== '') {
                    $registro->TotalRollos = (float) $item['totalRollos'];
                }
                if (isset($item['totalPzas']) && $item['totalPzas'] !== null && $item['totalPzas'] !== '') {
                    $registro->TotalPzas = (float) $item['totalPzas'];
                }

                // Asegurar que BomName se guarde correctamente si hay BomId pero no BomName
                // Solo buscar en BD si el usuario NO ingresó manualmente el BomName
                $bomNameIngresadoManual = isset($item['bomName']) && $item['bomName'] !== null && $item['bomName'] !== '';

                if (empty($registro->BomName) && ! empty($registro->BomId) && ! $bomNameIngresadoManual) {
                    try {
                        $result = $this->resolverBomCrudoExacto($registro);

                        if ($result && ! empty($result->bomName)) {
                            if (! empty($result->bomId)) {
                                $registro->BomId = trim((string) $result->bomId);
                            }
                            $registro->BomName = trim($result->bomName);
                        }
                    } catch (\Exception $e) {
                        // Silenciar error, simplemente no se actualiza el BomName
                    }
                }

                // Configurar campos adicionales
                $registro->CreaProd = $registro->CreaProd ?? 1;
                $registro->EficienciaSTD = $registro->EficienciaSTD ?? null;

                // Densidad: usar del request si viene, sino calcular
                if (isset($item['densidad']) && $item['densidad'] !== null && $item['densidad'] !== '') {
                    $registro->Densidad = round((float) $item['densidad'], 4);
                } else {
                    // Densidad: fórmula = peso_crudo / ((ancho * largo) / 10)
                    $densidad = null;
                    $peso = $registro->PesoCrudo ?? null;
                    $ancho = $registro->Ancho ?? null;
                    $largo = $registro->LargoCrudo ?? null;

                    if ($peso !== null && $ancho !== null && $largo !== null &&
                        is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                        $ancho > 0 && $largo > 0) {
                        // Limpiar largo si tiene texto (ej: "50 Cms.")
                        $largoLimpio = is_numeric($largo)
                            ? (float) $largo
                            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

                        // Fórmula: densidad = peso_crudo / ((ancho * largo) / 10)
                        $denominador = ($ancho * $largoLimpio) / 10;
                        if ($denominador > 0) {
                            $densidad = round((float) $peso / $denominador, 4);
                        }
                    }
                    $registro->Densidad = $densidad;
                }
                $registro->ActualizaLmat = $registro->ActualizaLmat ?? 0;

                // Campos de auditoría usando el helper
                AuditoriaHelper::aplicarCamposAuditoria($registro);

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                // Actualizar CatCodificados con los mismos campos (incl. CodigoDibujo: lo mostrado en pantalla o resuelto desde catálogo)
                $codigoDibujoLiberacion = trim((string) ($item['codigoDibujo'] ?? ''));
                $this->actualizarCatCodificados(
                    $registro,
                    $codigoDibujoLiberacion !== '' ? $codigoDibujoLiberacion : null
                );

                // Actualizar ReqModelosCodificados con OrdPrincipal y PesoMuestra
                $this->actualizarReqModelosCodificados($registro);

                // Recargar el registro con los campos necesarios para la orden de cambio
                $registroActualizado = ReqProgramaTejido::find($id);

                if ($registroActualizado) {
                    $actualizados->push($registroActualizado);
                }
            }

            if ($actualizados->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No fue posible actualizar los registros seleccionados.',
                ], 422);
            }

            DB::commit();

            // Generar Excel usando el sistema de orden de cambio
            $ordenCambioController = new OrdenDeCambioFelpaController;
            $response = $ordenCambioController->generarExcelDesdeBD($actualizados);

            // Si la respuesta es un StreamedResponse, convertirla a base64
            if ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();
                $excelBinary = ob_get_clean();

                return response()->json([
                    'success' => true,
                    'message' => 'Órdenes liberadas correctamente.',
                    'fileName' => 'ORDEN_CAMBIO_MODELO_'.now()->format('Ymd_His').'.xlsx',
                    'fileData' => base64_encode($excelBinary),
                    'redirectUrl' => route('catalogos.req-programa-tejido'),
                ]);
            }

            // Si hay error, retornar la respuesta JSON directamente
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al liberar órdenes', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al liberar las órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene L.Mat (BOM) y Nombre Mat (BomName) para una o múltiples combinaciones
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerBomYNombre(Request $request)
    {
        $combinationsParam = trim((string) $request->query('combinations', ''));
        $itemId = trim((string) $request->query('itemId', ''));
        $inventSizeId = trim((string) $request->query('inventSizeId', ''));
        $term = trim((string) $request->query('term', ''));
        $allowFallback = filter_var($request->query('fallback', false), FILTER_VALIDATE_BOOLEAN);
        $freeMode = filter_var($request->query('freeMode', false), FILTER_VALIDATE_BOOLEAN);

        try {
            // Si freeMode está activo, búsqueda completamente libre (sin filtrar por ItemId ni InventSizeId)
            if ($freeMode) {
                return response()->json([
                    'success' => true,
                    'data' => $this->queryBomFallback($term, null),
                ]);
            }

            // Si viene 'combinations', buscar múltiples combinaciones
            if ($combinationsParam !== '') {
                $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

                if (empty($combinations)) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                    ]);
                }

                // Parsear combinaciones: "itemId::inventSizeId" o "itemId::inventSizeId::..." (tercer segmento ignorado).
                // Legado: "itemId:inventSizeId".
                // BT+BV, ITEMID (-1), TWINVENTSIZEID, ITEMGROUPID = CRUDO, TwSalon ∈ SMIT/JACQUARD (±JACUARD en AX).
                $pairs = [];
                foreach ($combinations as $combo) {
                    $itemIdCombo = '';
                    $inventSizeIdCombo = '';
                    if (str_contains($combo, '::')) {
                        $parts = array_map('trim', explode('::', $combo, 3));
                        $itemIdCombo = $parts[0] ?? '';
                        $inventSizeIdCombo = $parts[1] ?? '';
                    } else {
                        $parts = array_map('trim', explode(':', $combo, 2));
                        if (count($parts) === 2) {
                            $itemIdCombo = $parts[0];
                            $inventSizeIdCombo = $parts[1];
                        }
                    }
                    if ($itemIdCombo === '' || $inventSizeIdCombo === '') {
                        continue;
                    }
                    $pairs[] = [
                        'itemIdWithSuffix' => $itemIdCombo.'-1',
                        'inventSizeId' => $inventSizeIdCombo,
                    ];
                }

                if (empty($pairs)) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                    ]);
                }

                // Consulta única optimizada para múltiples combinaciones
                $results = DB::connection('sqlsrv_ti')
                    ->table('BOMTABLE as BT')
                    ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                    ->select('BV.ITEMID', 'BT.TWINVENTSIZEID', 'BT.BOMID as bomId', 'BT.NAME as bomName')
                    ->where('BT.ITEMGROUPID', 'CRUDO')
                    ->whereIn('BT.TwSalon', self::BOM_CRUDO_TW_SALONES)
                    ->where(function ($query) use ($pairs) {
                        foreach ($pairs as $pair) {
                            $query->orWhere(function ($q) use ($pair) {
                                $q->where('BV.ITEMID', $pair['itemIdWithSuffix'])
                                    ->where('BT.TWINVENTSIZEID', $pair['inventSizeId']);
                            });
                        }
                    })
                    ->orderBy('BT.BOMID')
                    ->get();

                // Clave: item|talla (mismo criterio que el autofill en Blade).
                $map = [];
                foreach ($results as $result) {
                    $itemIdOriginal = str_replace('-1', '', (string) $result->ITEMID);
                    $size = (string) $result->TWINVENTSIZEID;

                    $matchingPairs = array_values(array_filter(
                        $pairs,
                        static function (array $p) use ($result): bool {
                            return $p['itemIdWithSuffix'] === $result->ITEMID && $p['inventSizeId'] === $result->TWINVENTSIZEID;
                        }
                    ));

                    if ($matchingPairs === []) {
                        continue;
                    }

                    $key = $itemIdOriginal.'|'.$size;

                    if (! isset($map[$key])) {
                        $map[$key] = [
                            [
                                'bomId' => $result->bomId,
                                'bomName' => $result->bomName,
                            ],
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => $map,
                ]);
            }

            // Búsqueda individual (autocompletado)
            if ($itemId === '' || $inventSizeId === '') {
                if ($allowFallback && $term !== '') {
                    return response()->json([
                        'success' => true,
                        'data' => $this->queryBomFallback($term, $inventSizeId),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $itemIdWithSuffix = $itemId.'-1';
            $query = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where('BV.ITEMID', $itemIdWithSuffix)
                ->where('BT.ITEMGROUPID', 'CRUDO')
                ->where('BT.TWINVENTSIZEID', $inventSizeId)
                ->whereIn('BT.TwSalon', self::BOM_CRUDO_TW_SALONES);

            if ($term !== '') {
                $query->where(function ($q) use ($term) {
                    $q->where('BT.BOMID', 'like', '%'.$term.'%')
                        ->orWhere('BT.NAME', 'like', '%'.$term.'%');
                });
            }

            $results = $query->orderBy('BT.BOMID')->limit(20)->get();
            if ($results->isEmpty() && $allowFallback && $term !== '') {
                $results = $this->queryBomFallback($term, $inventSizeId);
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener BOM y Nombre', [
                'combinations' => $combinationsParam,
                'item_id' => $itemId,
                'invent_size_id' => $inventSizeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar L.Mat.',
            ], 500);
        }
    }

    private function queryBomFallback(string $term, ?string $inventSizeId = null, int $limit = 50)
    {
        $query = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->whereIn('BT.TwSalon', self::BOM_CRUDO_TW_SALONES);

        // Filtrar por tamaño si está disponible
        if ($inventSizeId !== null && $inventSizeId !== '') {
            $query->where('BT.TWINVENTSIZEID', $inventSizeId);
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('BT.BOMID', 'like', '%'.$term.'%')
                    ->orWhere('BT.NAME', 'like', '%'.$term.'%');
            });
        }

        return $query->orderBy('BT.BOMID')->limit($limit)->get();
    }

    /**
     * Obtiene el tipo de hilo (TipoHilo) desde INVENTTABLE para uno o múltiples items
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerTipoHilo(Request $request)
    {
        $itemIdsParam = trim((string) $request->query('itemIds', ''));

        if ($itemIdsParam === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $itemIds = array_filter(array_map('trim', explode(',', $itemIdsParam)));
            $itemIdsWithSuffix = array_map(fn ($id) => $id.'-1', $itemIds);

            $results = DB::connection('sqlsrv_ti')
                ->table('INVENTTABLE')
                ->select('ITEMID', 'TwTipoHiloId')
                ->whereIn('ITEMID', $itemIdsWithSuffix)
                ->get();

            $map = [];
            foreach ($results as $result) {
                $itemIdOriginal = str_replace('-1', '', $result->ITEMID);
                $map[$itemIdOriginal] = $result->TwTipoHiloId ?? null;
            }

            return response()->json([
                'success' => true,
                'data' => $map,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener TipoHilo', [
                'item_ids' => $itemIdsParam,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Tipo Hilo.',
            ], 500);
        }
    }

    /**
     * Guarda un campo editable desde la vista de liberar órdenes
     * Actualiza tanto ReqProgramaTejido como CatCodificados
     */
    public function guardarCamposEditables(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
                'field' => 'required|string|in:MtsRollo,PzasRollo,TotalRollos,TotalPzas,Repeticiones,SaldoMarbete,Densidad,CombinaTrama',
                'value' => 'nullable',
            ]);

            $id = (int) $data['id'];
            $field = $data['field'];
            $value = $data['value'];

            DB::beginTransaction();

            try {
                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (! $registro) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Registro no encontrado.',
                    ], 404);
                }

                // Validar y convertir el valor según el tipo de campo
                if ($field === 'CombinaTrama') {
                    // Campo string
                    $registro->CombinaTram = $value !== null ? trim((string) $value) : null;
                } elseif ($field === 'SaldoMarbete') {
                    // SaldoMarbete es entero, redondear hacia arriba si hay decimal
                    $registro->SaldoMarbete = $value !== null ? (int) ceil((float) $value) : null;
                } elseif ($field === 'Repeticiones') {
                    // Repeticiones es entero, redondear hacia arriba si hay decimal
                    $registro->Repeticiones = $value !== null ? (int) ceil((float) $value) : null;
                } elseif ($field === 'Densidad') {
                    // Densidad es float con 4 decimales
                    $registro->Densidad = $value !== null ? round((float) $value, 4) : null;
                } elseif ($field === 'TotalRollos') {
                    // TotalRollos es float, redondear hacia arriba si hay decimal
                    $registro->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
                } else {
                    // MtsRollo, PzasRollo, TotalPzas son float
                    $registro->{$field} = $value !== null ? (float) $value : null;
                }

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                // Actualizar CatCodificados si existe
                $this->actualizarCatCodificadosCampo($registro, $field, $value);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Campo actualizado correctamente.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al guardar campo editable', [
                    'id' => $id,
                    'field' => $field,
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el campo: '.$e->getMessage(),
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error general al guardar campo editable', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al guardar el campo.',
            ], 500);
        }
    }

    /**
     * OrdenTejido / NoProduccion debe ser único: no puede repetirse en otro registro de programa
     * ni en CatCodificados para otro telar (mismo telar = fila que se actualizará al liberar).
     *
     * @return string|null mensaje de error para JSON, o null si el folio es válido
     */
    private function validarOrdenTejidoUnicoParaLiberacion(string $folio, ReqProgramaTejido $registro): ?string
    {
        $folio = trim($folio);
        if ($folio === '') {
            return 'No se pudo validar el número de orden.';
        }

        $duplicadoPrograma = ReqProgramaTejido::query()
            ->where('NoProduccion', $folio)
            ->where('Id', '!=', $registro->Id)
            ->exists();

        if ($duplicadoPrograma) {
            return 'El número de orden "'.$folio.'" ya está asignado en otro registro del programa de tejido.';
        }

        try {
            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = Schema::getColumnListing($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $folio);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter && in_array('NoProduccion', $columns, true)) {
                $query->where('NoProduccion', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter) {
                return null;
            }

            $codificados = $query->get();
            if ($codificados->isEmpty()) {
                return null;
            }

            $telarCol = null;
            if (in_array('TelarId', $columns, true)) {
                $telarCol = 'TelarId';
            } elseif (in_array('NoTelarId', $columns, true)) {
                $telarCol = 'NoTelarId';
            }

            $noTelarSesion = trim((string) ($registro->NoTelarId ?? ''));

            if ($telarCol === null) {
                return 'El número de orden "'.$folio.'" ya existe en catálogo codificados.';
            }

            foreach ($codificados as $c) {
                $telarCod = trim((string) ($c->{$telarCol} ?? ''));
                if ($telarCod !== $noTelarSesion) {
                    return 'El número de orden "'.$folio.'" ya existe en codificados para otro telar.';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('validarOrdenTejidoUnicoParaLiberacion', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo validar la unicidad del número de orden en codificados.';
        }

        return null;
    }

    /**
     * Actualiza un campo específico en CatCodificados basado en ReqProgramaTejido
     */
    private function actualizarCatCodificadosCampo(ReqProgramaTejido $registro, string $field, $value): void
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            // Si no hay NoProduccion, no podemos actualizar CatCodificados
            if (empty($noProduccion)) {
                return;
            }

            $modelo = new CatCodificados;
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

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                return;
            }

            // Mapear el campo de ReqProgramaTejido a CatCodificados
            $campoCatCodificados = null;
            if ($field === 'SaldoMarbete') {
                $campoCatCodificados = 'NoMarbete';
            } elseif ($field === 'CombinaTrama') {
                $campoCatCodificados = 'CombinaTram';
            } elseif ($field === 'CambioHilo' || $field === 'CambioRepaso') {
                $campoCatCodificados = 'CambioRepaso';
            } else {
                $campoCatCodificados = $field;
            }

            // Verificar que el campo existe en CatCodificados
            if (! in_array($campoCatCodificados, $columns, true)) {
                return;
            }

            // Asignar el valor según el tipo de campo
            if ($campoCatCodificados === 'NoMarbete') {
                // NoMarbete (SaldoMarbete), redondear hacia arriba si hay decimal
                $registroCodificado->NoMarbete = $value !== null ? (float) ceil((float) $value) : null;
            } elseif ($campoCatCodificados === 'Repeticiones') {
                // Repeticiones, redondear hacia arriba si hay decimal
                $registroCodificado->Repeticiones = $value !== null ? (int) ceil((float) $value) : null;
            } elseif ($campoCatCodificados === 'Densidad') {
                $registroCodificado->Densidad = $value !== null ? round((float) $value, 4) : null;
            } elseif ($campoCatCodificados === 'CombinaTram') {
                $registroCodificado->CombinaTram = $value !== null ? trim((string) $value) : null;
            } elseif ($campoCatCodificados === 'CambioRepaso') {
                $registroCodificado->CambioRepaso = $value !== null && strtoupper(trim((string) $value)) === 'SI' ? 'SI' : 'NO';
            } elseif ($campoCatCodificados === 'TotalRollos') {
                // TotalRollos, redondear hacia arriba si hay decimal
                $registroCodificado->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
            } else {
                // MtsRollo, PzasRollo, TotalPzas
                $registroCodificado->{$campoCatCodificados} = $value !== null ? (float) $value : null;
            }

            $registroCodificado->save();
        } catch (\Exception $e) {
            Log::error('Error al actualizar CatCodificados campo editable', [
                'no_produccion' => $registro->NoProduccion ?? null,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
            // No lanzar excepción para no interrumpir el guardado en ReqProgramaTejido
        }
    }

    /**
     * Actualiza CatCodificados con los campos de ReqProgramaTejido después de liberar.
     *
     * @param  string|null  $codigoDibujoDesdePantalla  Valor de la columna Codigo Dibujo al liberar (mismo que en la vista); si es null/vacío se intenta resolver con CatCodificados.
     */
    private function actualizarCatCodificados(ReqProgramaTejido $registro, ?string $codigoDibujoDesdePantalla = null): void
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            if (empty($noProduccion)) {
                return;
            }

            $modelo = new CatCodificados;
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

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                return;
            }

            // Código de dibujo: priorizar lo enviado desde la grilla (autollenado o edición); si no, último CatCodificados (misma lógica que obtenerCodigoDibujo)
            $codigoDibujoFinal = null;
            $explicito = $codigoDibujoDesdePantalla !== null ? trim($codigoDibujoDesdePantalla) : '';
            if ($explicito !== '') {
                $codigoDibujoFinal = $explicito;
            } elseif (in_array('CodigoDibujo', $columns, true)) {
                $resuelto = $this->resolverCodigoDibujoCatCodificados(
                    trim((string) ($registro->ItemId ?? '')),
                    trim((string) ($registro->InventSizeId ?? '')),
                    trim((string) ($registro->SalonTejidoId ?? ''))
                );
                if ($resuelto !== null && $resuelto !== '') {
                    $codigoDibujoFinal = $resuelto;
                }
            }

            // Campos a actualizar desde ReqProgramaTejido
            // Convertir valores null a 0 para campos numéricos si es necesario
            // Aplicar ceil() a Repeticiones, SaldoMarbete y TotalRollos
            $payload = [
                'BomId' => $registro->BomId,
                'BomName' => $registro->BomName,
                'HiloAX' => $registro->HiloAX,
                'MtsRollo' => $registro->MtsRollo,
                'PzasRollo' => $registro->PzasRollo,
                'TotalRollos' => $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null,
                'TotalPzas' => $registro->TotalPzas,
                'Repeticiones' => $registro->Repeticiones !== null ? (int) ceil((float) $registro->Repeticiones) : null,
                'NoMarbete' => $registro->SaldoMarbete !== null ? (float) ceil((float) $registro->SaldoMarbete) : null, // SaldoMarbete en ReqProgramaTejido = NoMarbete en CatCodificados
                'CombinaTram' => $registro->CombinaTram,
                'CambioRepaso' => $registro->CambioHilo,
                'Densidad' => $registro->Densidad !== null ? (float) $registro->Densidad : null,
                'Obs5' => $registro->Observaciones,
                'CreaProd' => $registro->CreaProd ?? 1,
                'ActualizaLmat' => $registro->ActualizaLmat ?? 0,
                'CategoriaCalidad' => $registro->CategoriaCalidad,
                'CustName' => $registro->CustName,
                'PesoMuestra' => $registro->PesoMuestra,
                'OrdPrincipal' => $registro->OrdPrincipal,
            ];

            $updated = false;

            // Asignar todos los campos del payload, EXCEPTO TotalRollos y TotalPzas que se manejarán después
            foreach ($payload as $column => $value) {
                // Saltar TotalRollos y TotalPzas, se manejarán después
                if ($column === 'TotalRollos' || $column === 'TotalPzas') {
                    continue;
                }

                if (! in_array($column, $columns, true)) {
                    continue;
                }

                // Asignar siempre, sin comparación (para asegurar que se actualicen)
                $registroCodificado->setAttribute($column, $value);
                $updated = true;
            }

            // FORZAR asignación de TotalRollos y TotalPzas SIEMPRE (incluso si son null)
            // Aplicar ceil() a TotalRollos si hay decimal
            $valorTotalRollos = $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null;
            $valorTotalPzas = $registro->TotalPzas !== null ? (float) $registro->TotalPzas : null;

            if (in_array('TotalRollos', $columns, true)) {
                $registroCodificado->TotalRollos = $valorTotalRollos;
                $updated = true;
            }
            if (in_array('TotalPzas', $columns, true)) {
                $registroCodificado->TotalPzas = $valorTotalPzas;
                $updated = true;
            }

            // FORZAR asignación de CategoriaCalidad SIEMPRE (incluso si es null)
            if (in_array('CategoriaCalidad', $columns, true)) {
                $registroCodificado->CategoriaCalidad = $registro->CategoriaCalidad;
                $updated = true;
            }

            // FORZAR asignación de CustName SIEMPRE (incluso si es null)
            if (in_array('CustName', $columns, true)) {
                $registroCodificado->CustName = $registro->CustName;
                $updated = true;
            }

            // FORZAR asignación de PesoMuestra SIEMPRE (incluso si es null)
            if (in_array('PesoMuestra', $columns, true)) {
                $registroCodificado->PesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
                $updated = true;
            }

            // FORZAR asignación de OrdPrincipal SIEMPRE (incluso si es null)
            if (in_array('OrdPrincipal', $columns, true)) {
                $ordPrincipalRaw = $registro->OrdPrincipal;
                $ordPrincipalValue = null;
                if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                    $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                    if (is_numeric($ordPrincipalStr)) {
                        $ordPrincipalValue = (int) $ordPrincipalStr;
                    } elseif ($ordPrincipalStr !== '') {
                        $ordPrincipalValue = $ordPrincipalStr;
                    }
                }
                $registroCodificado->OrdPrincipal = $ordPrincipalValue;
                $updated = true;
            }

            if ($codigoDibujoFinal !== null && in_array('CodigoDibujo', $columns, true)) {
                $registroCodificado->CodigoDibujo = $codigoDibujoFinal;
                $updated = true;
            }

            // Aplicar campos de auditoría: primero creación si no existen, luego modificación
            // Usar false para aplicar ambos (creación y modificación)
            AuditoriaHelper::aplicarCamposAuditoria($registroCodificado, false);

            // Forzar UsuarioCrea si no existe y la columna existe
            if (in_array('UsuarioCrea', $columns, true)) {
                $usuarioActual = trim((string) ($registroCodificado->UsuarioCrea ?? ''));
                if (empty($usuarioActual)) {
                    $usuario = AuditoriaHelper::obtenerUsuarioActual();
                    $registroCodificado->UsuarioCrea = $usuario;
                    $updated = true;
                }
            }

            if ($updated || $registroCodificado->isDirty()) {
                $registroCodificado->save();
                $registroCodificado->refresh();
            }
        } catch (\Throwable $e) {

        }
    }

    /**
     * Actualiza ReqModelosCodificados con OrdPrincipal y PesoMuestra desde ReqProgramaTejido.
     * Busca por TamanoClave, ClaveModelo o OrdenTejido (NoProduccion).
     */
    private function actualizarReqModelosCodificados(ReqProgramaTejido $registro): void
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
            if (! empty($noProduccion)) {
                $query->where('OrdenTejido', $noProduccion);
            } elseif (! empty($tamanoClave)) {
                // Si no hay OrdenTejido, buscar por TamanoClave
                $query->where('TamanoClave', $tamanoClave);
                // Si hay SalonTejidoId, filtrar por él también
                if (! empty($salonTejidoId)) {
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
        }
    }

    /**
     * Obtiene el peso del rollo desde la tabla ReqPesosRolloTejido.
     * Orden de búsqueda: 1) InventSizeId exacto del registro; 2) FEL (si aplica); 3) DEF.
     * Si no encuentra nada, retorna null (para usar 41.5 como default).
     */
    private function obtenerPesoRollo(ReqProgramaTejido $registro): ?float
    {
        try {
            $inventSizeId = trim((string) ($registro->InventSizeId ?? ''));

            // 1) Primero buscar por coincidencia exacta de InventSizeId
            if (! empty($inventSizeId)) {
                $pesoRollo = $this->obtenerPesoRolloPorInventSizeId($inventSizeId);

                if ($pesoRollo !== null) {
                    return $pesoRollo;
                }
            }

            // 2) Si el registro tiene FEL en InventSizeId, buscar por FEL
            $buscarFel = ! empty($inventSizeId) && (stripos($inventSizeId, 'FEL') !== false);

            if ($buscarFel) {
                $pesoRollo = $this->obtenerPesoRolloPorInventSizeId('FEL');

                if ($pesoRollo !== null) {
                    return $pesoRollo;
                }
            }

            // 3) Buscar por DEF (default)
            $pesoRollo = $this->obtenerPesoRolloPorInventSizeId('DEF');

            if ($pesoRollo !== null) {
                return $pesoRollo;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function obtenerPesoRolloPorInventSizeId(string $inventSizeId): ?float
    {
        $pesoRollo = ReqPesosRollosTejido::where('InventSizeId', trim($inventSizeId))
            ->whereNotNull('PesoRollo')
            ->orderByDesc('FechaModificacion')
            ->orderByDesc('FechaCreacion')
            ->orderByDesc('Id')
            ->first();

        return $pesoRollo && $pesoRollo->PesoRollo !== null
            ? (float) $pesoRollo->PesoRollo
            : null;
    }

    private function resolverBomCrudoExacto(ReqProgramaTejido $registro): ?object
    {
        $opciones = $this->resolverBomCrudoOpciones($registro);

        return $opciones !== []
            ? (object) $opciones[0]
            : null;
    }

    /**
     * @return array<int, array{bomId: string, bomName: string}>
     */
    private function resolverBomCrudoOpciones(ReqProgramaTejido $registro): array
    {
        $itemId = trim((string) ($registro->ItemId ?? ''));
        $inventSizeId = trim((string) ($registro->InventSizeId ?? ''));
        $salon = $this->normalizarSalonBomCrudo($registro);

        if ($itemId === '' || $inventSizeId === '' || $salon === '') {
            return [];
        }

        return DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
            ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
            ->where('BV.ITEMID', $itemId.'-1')
            ->where('BT.TWINVENTSIZEID', $inventSizeId)
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->where('BT.TWSALON', $salon)
            ->orderBy('BT.BOMID')
            ->get()
            ->map(fn ($row) => [
                'bomId' => trim((string) ($row->bomId ?? '')),
                'bomName' => trim((string) ($row->bomName ?? '')),
            ])
            ->filter(fn (array $row) => $row['bomId'] !== '')
            ->values()
            ->all();
    }

    private function normalizarSalonBomCrudo(ReqProgramaTejido $registro): string
    {
        $salon = strtoupper(trim((string) ($registro->SalonTejidoId ?? '')));

        return match ($salon) {
            'JACUARD' => 'JACQUARD',
            'JACQUARD', 'SMIT' => $salon,
            default => $salon,
        };
    }

    /**
     * Calcula la fecha programada basada en la fórmula INN
     */
    private function calcularFechaProgramada(ReqProgramaTejido $registro, Carbon $hoy, Carbon $fechaFormula): ?Carbon
    {
        if (! $registro->FechaInicio) {
            return null;
        }

        $fechaInicio = $registro->FechaInicio instanceof Carbon
            ? $registro->FechaInicio->copy()->startOfDay()
            : Carbon::parse($registro->FechaInicio)->startOfDay();

        return $fechaInicio->lte($fechaFormula) ? $hoy->copy() : null;
    }

    /**
     * Obtiene el código de dibujo (CodigoDibujo) desde CatCodificados.
     * Parámetro combinations: valores separados por coma; cada valor es
     *   itemId::inventSizeId::salonTejidoId (salon = Departamento en CatCodificados), o legado itemId:inventSizeId.
     * Orden de búsqueda por combinación (primer CodigoDibujo no vacío con Id más alto en cada intento):
     *   1) ItemId + InventSizeId + Departamento (si hay invent y salón)
     *   2) ItemId + Departamento (si hay salón; cubre cuando InventSizeId del programa no coincide con CatCodificados)
     *   3) ItemId + InventSizeId (sin filtrar departamento)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerCodigoDibujo(Request $request)
    {
        $combinationsParam = trim((string) $request->query('combinations', ''));

        if ($combinationsParam === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

            if (empty($combinations)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $pairs = [];
            foreach ($combinations as $combo) {
                $itemId = '';
                $inventSizeId = '';
                $departamento = '';

                if (str_contains($combo, '::')) {
                    $parts = explode('::', $combo, 3);
                    $itemId = trim((string) ($parts[0] ?? ''));
                    $inventSizeId = trim((string) ($parts[1] ?? ''));
                    $departamento = trim((string) ($parts[2] ?? ''));
                } else {
                    $parts = explode(':', $combo, 2);
                    $itemId = trim((string) ($parts[0] ?? ''));
                    $inventSizeId = trim((string) ($parts[1] ?? ''));
                }

                if ($itemId === '' || ($inventSizeId === '' && $departamento === '')) {
                    continue;
                }

                $cacheKey = $itemId.'|'.$inventSizeId.'|'.$departamento;
                $pairs[$cacheKey] = [
                    'itemId' => $itemId,
                    'inventSizeId' => $inventSizeId,
                    'departamento' => $departamento,
                    'cacheKey' => $cacheKey,
                ];
            }

            if (empty($pairs)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $map = [];
            foreach ($pairs as $pair) {
                $codigo = $this->resolverCodigoDibujoCatCodificados(
                    $pair['itemId'],
                    $pair['inventSizeId'],
                    $pair['departamento']
                );
                if ($codigo !== null && $codigo !== '') {
                    $map[$pair['cacheKey']] = $codigo;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $map,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Código de Dibujo.',
            ], 500);
        }
    }

    /**
     * @return string|null Primer CodigoDibujo no vacío con Id más alto por cada criterio aplicado
     */
    private function resolverCodigoDibujoCatCodificados(string $itemId, string $inventSizeId, string $departamento): ?string
    {
        $try = function (\Illuminate\Database\Eloquent\Builder $q): ?string {
            foreach ($q->orderByDesc('Id')->get(['Id', 'CodigoDibujo']) as $row) {
                $c = trim((string) ($row->CodigoDibujo ?? ''));
                if ($c !== '') {
                    return $c;
                }
            }

            return null;
        };

        if ($itemId !== '' && $inventSizeId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        if ($itemId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        if ($itemId !== '' && $inventSizeId !== '') {
            return $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId));
        }

        return null;
    }

    /**
     * Obtiene las opciones de hilos para el select desde INVENTTABLE (TwTipoHiloId)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerOpcionesHilos()
    {
        try {
            $hilos = DB::connection('sqlsrv_ti')
                ->table('TwTipoHilo')
                ->select('TipoHilo')
                ->where('TipoHilo', '!=', '')
                ->distinct()
                ->pluck('TipoHilo')
                ->filter(function ($value) {
                    return ! empty(trim((string) $value));
                })
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $hilos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener opciones de hilos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener opciones de hilos.',
            ], 500);
        }
    }
}
