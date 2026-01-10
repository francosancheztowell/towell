<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\catcodificados\CatCodificados;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Models\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
class LiberarOrdenesController extends Controller
{
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
                ->where(function($query) {
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

            $registros->each(function($registro) use ($hoy, $fechaFormula) {
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
                            'error' => $e->getMessage()
                        ]);
                        $registro->ProgramadoCalculado = null;
                    }
                } else {
                    $registro->ProgramadoCalculado = null;
                }
            });

            // Filtrar solo los registros que tienen fecha INN (ProgramadoCalculado no nulo)
            // y que NO tengan valor en NoExisteBase
            $registros = $registros->filter(function($registro) {
                return $registro->ProgramadoCalculado !== null
                    && (empty($registro->NoExisteBase) || is_null($registro->NoExisteBase));
            })->values();

            // Calcular prioridad del registro anterior para cada registro
            // Prioridad = "CHECAR + NombreProducto" del registro anterior en ReqProgramaTejido
            // Buscar el registro anterior que tenga el mismo NoTelarId según el ordenamiento original
            $registros->each(function($registro) {
                $noTelarId = $registro->NoTelarId ?? null;
                $salonTejidoId = $registro->SalonTejidoId ?? '';
                $fechaInicio = $registro->FechaInicio ?? null;
                $idActual = $registro->Id ?? null;

                if (!$noTelarId || !$idActual) {
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
                    $query->where(function($q) use ($fechaInicio, $idActual) {
                        $q->where('FechaInicio', '<', $fechaInicio)
                          ->orWhere(function($q2) use ($fechaInicio, $idActual) {
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

                if ($registroAnterior && !empty($registroAnterior->NombreProducto)) {
                    $nombreProductoAnterior = $registroAnterior->NombreProducto;
                    // Formar prioridad: "CHECAR + NombreProducto"
                    $prioridadFormada = 'SALDAR ' . $nombreProductoAnterior;
                    $registro->PrioridadAnterior = $prioridadFormada;
                } else {
                    $registro->PrioridadAnterior = '';
                }
            });

            // Calcular campos en la carga para mostrarlos en la vista
            $registros->each(function ($registro) {
                $pCrudo = $registro->PesoCrudo ?? null;
                $tiras = $registro->NoTiras ?? null;
                $repeticiones = null;
                if (isset($registro->Repeticiones) && is_numeric($registro->Repeticiones)) {
                    $repeticiones = (int) $registro->Repeticiones;
                } elseif ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    // Obtener peso rollo desde BD (buscar FEL primero, luego DEF, default 41.5)
                    $pesoRollo = $this->obtenerPesoRollo($registro) ?? 41.5;
                    $repeticiones = floor((($pesoRollo / (float)$pCrudo) / (float)$tiras) * 1000);
                }

                $saldoMarbeteInt = 0;
                if (isset($registro->SaldoMarbete) && is_numeric($registro->SaldoMarbete)) {
                    $saldoMarbeteInt = (int) $registro->SaldoMarbete;
                } else {
                    $cantidadProducir = $registro->SaldoPedido ?? null;
                    $saldoMarbete = 0;
                    if ($cantidadProducir !== null && $tiras && $repeticiones !== null &&
                        is_numeric($cantidadProducir) && is_numeric($tiras) && is_numeric($repeticiones) &&
                        $tiras > 0 && $repeticiones > 0) {
                        $saldoMarbete = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;
                    }
                    if (is_numeric($saldoMarbete)) {
                        $saldoFloat = (float) $saldoMarbete;
                        $entero = (int) floor($saldoFloat);
                        $decimal = abs($saldoFloat - $entero);
                        $saldoMarbeteInt = $decimal >= 0.5 ? (int) ceil($saldoFloat) : $entero;
                    }
                }
                // MtsRollo: fórmula = medida de largo * repeticiones (convertir cm a metros)
                $mtsRollo = null;
                if (isset($registro->MtsRollo) && is_numeric($registro->MtsRollo)) {
                    $mtsRollo = (float) $registro->MtsRollo;
                } else {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo) ? (float)$largo : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                            $mtsRollo = round(($largoNum * $repeticiones) / 100, 2);
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
                if (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos)) {
                    $totalRollos = (float) $registro->TotalRollos;
                } else {
                    $totalRollos = $saldoMarbeteInt;
                }

                $totalPzas = null;
                if (isset($registro->TotalPzas) && is_numeric($registro->TotalPzas)) {
                    $totalPzas = (float) $registro->TotalPzas;
                } else {
                    if ($totalRollos !== null && $pzasRollo !== null && is_numeric($totalRollos) && is_numeric($pzasRollo)) {
                        $totalPzas = round((float)$totalRollos * (float)$pzasRollo, 0);
                    }
                }

                $registro->Repeticiones = $repeticiones;
                $registro->SaldoMarbete = $saldoMarbeteInt;
                $registro->MtsRollo = $mtsRollo;
                $registro->PzasRollo = $pzasRollo;
                $registro->TotalRollos = $totalRollos;
                $registro->TotalPzas = $totalPzas;

                // Densidad: fórmula = peso / (ancho * largo) / 1 kg m2
                $densidad = $registro->PesoGRM2 ?? null;
                if ($densidad === null) {
                    $peso = $registro->PesoCrudo ?? null;
                    $ancho = $registro->Ancho ?? null;
                    $largo = $registro->LargoCrudo ?? null;

                    if ($peso !== null && $ancho !== null && $largo !== null &&
                        is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                        $ancho > 0 && $largo > 0) {
                        // Convertir largo de cm a metros
                        $largoMetros = is_numeric($largo)
                            ? (float)$largo / 100
                            : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo) / 100;

                        $area = $ancho * $largoMetros; // área en m2
                        if ($area > 0) {
                            // PesoCrudo está en gramos, convertir a kg: peso / 1000
                            $pesoKg = (float)$peso / 1000;
                            $densidad = round($pesoKg / $area, 2);
                        }
                    }
                }
                $registro->Densidad = $densidad;
            });

            return view('modulos.programa-tejido.liberar-ordenes.index', compact('registros', 'dias'));
        } catch (\Throwable $e) {

            return view('modulos.programa-tejido.liberar-ordenes.index', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage(),
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
            'registros.*.id' => 'required|integer|exists:ReqProgramaTejido,Id',
            'registros.*.prioridad' => 'nullable|string|max:100',
            'registros.*.bomId' => 'nullable|string|max:20',
            'registros.*.bomName' => 'nullable|string|max:60',
            'registros.*.hiloAX' => 'nullable|string|max:30',
            'registros.*.mtsRollo' => 'nullable|numeric',
            'registros.*.pzasRollo' => 'nullable|numeric',
            'registros.*.totalRollos' => 'nullable|numeric',
            'registros.*.totalPzas' => 'nullable|numeric',
            'registros.*.repeticiones' => 'nullable|numeric',
            'registros.*.saldoMarbete' => 'nullable|numeric',
            'registros.*.combinaTram' => 'nullable|string|max:60',
        ], [
            'registros.required' => 'Debes seleccionar al menos un registro.',
            'registros.*.id.exists' => 'Uno de los registros seleccionados no existe.',
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

            foreach ($registrosInput as $item) {
                $id = (int) ($item['id'] ?? 0);
                if (!$id) {
                    continue;
                }

                $prioridad = trim((string) ($item['prioridad'] ?? ''));

                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (!$registro) {
                    continue;
                }

                // Generar folio único y configurar valores básicos
                $folio = FolioHelper::obtenerSiguienteFolio('Planeacion', 5);
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

                // Repeticiones: usar del request, existente o calcular
                $repeticiones = $item['repeticiones'] ?? $registro->Repeticiones;
                if ($repeticiones === null && $pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    // Obtener peso rollo desde BD (buscar FEL primero, luego DEF, default 41.5)
                    $pesoRollo = $this->obtenerPesoRollo($registro) ?? 41.5;
                    $repeticiones = floor((($pesoRollo / (float)$pCrudo) / (float)$tiras) * 1000);
                }

                // SaldoMarbete: usar del request, existente o calcular
                $saldoMarbeteInt = $item['saldoMarbete'] ?? $registro->SaldoMarbete;
                if ($saldoMarbeteInt === null) {
                    $cantidadProducir = $registro->SaldoPedido ?? null;
                    $saldoMarbeteCalc = 0;
                    if ($cantidadProducir !== null && $tiras && $repeticiones !== null &&
                        is_numeric($cantidadProducir) && is_numeric($tiras) && is_numeric($repeticiones) &&
                        $tiras > 0 && $repeticiones > 0) {
                        $saldoMarbeteCalc = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;
                    }
                    if (is_numeric($saldoMarbeteCalc)) {
                        $saldoFloat = (float) $saldoMarbeteCalc;
                        $entero = (int) floor($saldoFloat);
                        $decimal = abs($saldoFloat - $entero);
                        $saldoMarbeteInt = $decimal >= 0.5 ? (int) ceil($saldoFloat) : $entero;
                    }
                }

                // MtsRollo: usar del request, existente o calcular
                $mtsRollo = $item['mtsRollo'] ?? $registro->MtsRollo;
                if ($mtsRollo === null) {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo)
                            ? (float)$largo
                            : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                            $mtsRollo = round(($largoNum * $repeticiones) / 100, 2);
                        }
                    }
                }

                // PzasRollo: usar del request, existente o calcular
                $pzasRollo = $item['pzasRollo'] ?? $registro->PzasRollo;
                if ($pzasRollo === null && $repeticiones !== null && $tiras &&
                    is_numeric($repeticiones) && is_numeric($tiras) && $repeticiones > 0 && $tiras > 0) {
                    $pzasRollo = round($repeticiones * $tiras, 0);
                }

                // TotalRollos: usar del request, existente o usar saldoMarbeteInt
                $totalRollos = $item['totalRollos'] ?? $registro->TotalRollos ?? $saldoMarbeteInt;

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
                $camposTexto = ['CombinaTram', 'BomId', 'BomName', 'HiloAX'];
                foreach ($camposTexto as $campo) {
                    $key = lcfirst($campo);
                    $valor = $item[$key] ?? null;
                    if ($valor !== null && $valor !== '') {
                        $registro->$campo = trim((string) $valor);
                    } elseif (empty($registro->$campo)) {
                        $registro->$campo = null;
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

                if (empty($registro->BomName) && !empty($registro->BomId) && !$bomNameIngresadoManual) {
                    $itemId = $registro->ItemId ?? null;
                    $inventSizeId = $registro->InventSizeId ?? null;

                    try {
                        if ($itemId && $inventSizeId) {
                            $itemIdWithSuffix = $itemId . '-1';
                            $result = DB::connection('sqlsrv_ti')
                                ->table('BOMTABLE as BT')
                                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                                ->select('BT.NAME as bomName')
                                ->where('BT.BOMID', $registro->BomId)
                                ->where('BV.ITEMID', $itemIdWithSuffix)
                                ->where('BT.TWINVENTSIZEID', $inventSizeId)
                                ->where('BT.TwSalon', 'SalonTejido')
                                ->first();
                        } else {
                            $result = DB::connection('sqlsrv_ti')
                                ->table('BOMTABLE as BT')
                                ->select('BT.NAME as bomName')
                                ->where('BT.BOMID', $registro->BomId)
                                ->where('BT.TwSalon', 'SalonTejido')
                                ->first();
                        }

                        if ($result && !empty($result->bomName)) {
                            $registro->BomName = trim($result->bomName);
                        }
                    } catch (\Exception $e) {
                        // Silenciar error, simplemente no se actualiza el BomName
                    }
                }

                // Configurar campos adicionales
                $registro->CreaProd = $registro->CreaProd ?? 1;
                $registro->EficienciaSTD = $registro->EficienciaSTD ?? null;

                // Densidad: fórmula = peso / (ancho * largo) / 1 kg m2
                $densidad = $registro->PesoGRM2 ?? null;
                if ($densidad === null) {
                    $peso = $registro->PesoCrudo ?? null;
                    $ancho = $registro->Ancho ?? null;
                    $largo = $registro->LargoCrudo ?? null;

                    if ($peso !== null && $ancho !== null && $largo !== null &&
                        is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                        $ancho > 0 && $largo > 0) {
                        // Convertir largo de cm a metros
                        $largoMetros = is_numeric($largo)
                            ? (float)$largo / 100
                            : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo) / 100;

                        $area = $ancho * $largoMetros; // área en m2
                        if ($area > 0) {
                            // PesoCrudo está en gramos, convertir a kg: peso / 1000
                            $pesoKg = (float)$peso / 1000;
                            $densidad = round($pesoKg / $area, 2);
                        }
                    }
                }
                $registro->Densidad = $densidad;
                $registro->ActualizaLmat = $registro->ActualizaLmat ?? 0;

                // Campos de auditoría usando el helper
                AuditoriaHelper::aplicarCamposAuditoria($registro);

                $registro->save();

                // Actualizar CatCodificados con los mismos campos
                $this->actualizarCatCodificados($registro);

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
            $ordenCambioController = new OrdenDeCambioFelpaController();
            $response = $ordenCambioController->generarExcelDesdeBD($actualizados);

            // Si la respuesta es un StreamedResponse, convertirla a base64
            if ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();
                $excelBinary = ob_get_clean();

                return response()->json([
                    'success' => true,
                    'message' => 'Órdenes liberadas correctamente.',
                    'fileName' => 'ORDEN_CAMBIO_MODELO_' . now()->format('Ymd_His') . '.xlsx',
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
                'message' => 'Error al liberar las órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Obtiene L.Mat (BOM) y Nombre Mat (BomName) para una o múltiples combinaciones
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerBomYNombre(Request $request)
    {
        $combinationsParam = trim((string) $request->query('combinations', ''));
        $itemId = trim((string) $request->query('itemId', ''));
        $inventSizeId = trim((string) $request->query('inventSizeId', ''));
        $term = trim((string) $request->query('term', ''));
        $allowFallback = filter_var($request->query('fallback', false), FILTER_VALIDATE_BOOLEAN);

        try {
            // Si viene 'combinations', buscar múltiples combinaciones
            if ($combinationsParam !== '') {
                $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

                if (empty($combinations)) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                    ]);
                }

                // Parsear combinaciones: "itemId1:inventSizeId1,itemId2:inventSizeId2,..."
                $pairs = [];
                foreach ($combinations as $combo) {
                    $parts = explode(':', $combo);
                    if (count($parts) === 2) {
                        $itemIdCombo = trim($parts[0]);
                        $inventSizeIdCombo = trim($parts[1]);
                        $pairs[] = [
                            'itemIdWithSuffix' => $itemIdCombo . '-1',
                            'inventSizeId' => $inventSizeIdCombo,
                        ];
                    }
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
                    ->where(function($query) use ($pairs) {
                        foreach ($pairs as $pair) {
                            $query->orWhere(function($q) use ($pair) {
                                $q->where('BV.ITEMID', $pair['itemIdWithSuffix'])
                                  ->where('BT.TWINVENTSIZEID', $pair['inventSizeId']);
                            });
                        }
                    })
                    ->orderBy('BT.BOMID')
                    ->get();

                // Agrupar resultados por combinación (solo el primero si hay múltiples)
                $map = [];
                foreach ($results as $result) {
                    $itemIdOriginal = str_replace('-1', '', $result->ITEMID);
                    $key = $itemIdOriginal . '|' . $result->TWINVENTSIZEID;

                    if (!isset($map[$key])) {
                        $map[$key] = [
                            [
                                'bomId' => $result->bomId,
                                'bomName' => $result->bomName
                            ]
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
                        'data' => $this->queryBomFallback($term),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $itemIdWithSuffix = $itemId . '-1';
            $query = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where('BV.ITEMID', $itemIdWithSuffix)
                ->where('BT.TWINVENTSIZEID', $inventSizeId);

            if ($term !== '') {
                $query->where(function ($q) use ($term) {
                    $q->where('BT.BOMID', 'like', '%' . $term . '%')
                      ->orWhere('BT.NAME', 'like', '%' . $term . '%');
                });
            }

            $results = $query->orderBy('BT.BOMID')->limit(20)->get();
            if ($results->isEmpty() && $allowFallback && $term !== '') {
                $results = $this->queryBomFallback($term);
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

    private function queryBomFallback(string $term)
    {
        $query = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->select('BT.BOMID as bomId', 'BT.NAME as bomName');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('BT.BOMID', 'like', '%' . $term . '%')
                  ->orWhere('BT.NAME', 'like', '%' . $term . '%');
            });
        }

        return $query->orderBy('BT.BOMID')->limit(20)->get();
    }

    /**
     * Obtiene el tipo de hilo (TwTipoHiloId) para uno o múltiples items
     *
     * @param Request $request
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
            $itemIdsWithSuffix = array_map(fn($id) => $id . '-1', $itemIds);

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
     * Genera un Excel simple con los registros actualizados
     */
    protected function generarExcel($registros): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headings = [
            'Cuenta',
            'Salon',
            'Telar',
            'Ultimo',
            'Cambios Hilo',
            'Maq',
            'Ancho',
            'Ef Std',
            'Vel',
            'Hilo',
            'Calibre Pie',
            'Jornada',
            'Clave mod.',
            'Usar cuando no existe en base',
            'Producto',
            'Saldos',
            'Day Sheduling',
            'Orden Prod.',
            'INN',
            'Descrip.',
            'Aplic.',
            'Obs',
            'Fecha Fin',
            'Prioridad',
            'Clave AX',
        ];

        $sheet->fromArray($headings, null, 'A1');

        $rowNumber = 2;
        foreach ($registros as $registro) {
            $sheet->fromArray([
                $registro->CuentaRizo,
                $registro->SalonTejidoId,
                $registro->NoTelarId,
                $registro->Ultimo,
                $registro->CambioHilo,
                $registro->Maquina,
                $registro->Ancho,
                $registro->EficienciaSTD,
                $registro->VelocidadSTD,
                $registro->FibraRizo,
                $registro->CalibrePie2,
                $registro->CalendarioId,
                $registro->TamanoClave,
                $registro->NoExisteBase,
                $registro->NombreProducto,
                $registro->SaldoPedido,
                optional($registro->ProgramarProd)->format('Y-m-d'),
                $registro->NoProduccion,
                optional($registro->Programado)->format('Y-m-d'),
                $registro->NombreProyecto,
                $registro->AplicacionId,
                $registro->Observaciones,
                optional($registro->FechaFinal)->format('Y-m-d'),
                $registro->Prioridad,
                $registro->InventSizeId,
            ], null, 'A' . $rowNumber);

            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Actualiza CatCodificados con los campos de ReqProgramaTejido después de liberar
     */
    private function actualizarCatCodificados(ReqProgramaTejido $registro): void
    {
        try {
            Log::info('=== INICIO actualizarCatCodificados ===', [
                'registro_id' => $registro->Id ?? 'null',
                'no_produccion' => $registro->NoProduccion ?? 'null',
                'no_telar_id' => $registro->NoTelarId ?? 'null',
                'total_rollos' => $registro->TotalRollos ?? 'null',
                'total_pzas' => $registro->TotalPzas ?? 'null',
            ]);

            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            if (empty($noProduccion)) {
                Log::warning('NoProduccion vacío, saliendo de actualizarCatCodificados');
                return;
            }

            $modelo = new CatCodificados();
            $table = $modelo->getTable();
            $columns = Schema::getColumnListing($table);

            Log::info('Columnas disponibles en CatCodificados', [
                'total_columnas' => count($columns),
                'tiene_totalrollos' => in_array('TotalRollos', $columns, true),
                'tiene_totalpzas' => in_array('TotalPzas', $columns, true),
                'tiene_usuariocrea' => in_array('UsuarioCrea', $columns, true),
            ]);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
                Log::info('Buscando por OrdenTejido', ['valor' => $noProduccion]);
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
                Log::info('Buscando por NumOrden', ['valor' => $noProduccion]);
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
                Log::info('Filtrando por TelarId', ['valor' => $noTelarId]);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
                Log::info('Filtrando por NoTelarId', ['valor' => $noTelarId]);
            }

            if (!$hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
                Log::info('Buscando por NoProduccion', ['valor' => $noProduccion]);
            }

            $registroCodificado = $query->first();

            if (!$registroCodificado) {
                Log::warning('NO se encontró registro en CatCodificados', [
                    'no_produccion' => $noProduccion,
                    'no_telar_id' => $noTelarId,
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                ]);
                return;
            }

            Log::info('Registro encontrado en CatCodificados', [
                'id' => $registroCodificado->Id,
                'no_produccion' => $noProduccion,
            ]);

            // Campos a actualizar desde ReqProgramaTejido
            // Convertir valores null a 0 para campos numéricos si es necesario
            $payload = [
                'BomId' => $registro->BomId,
                'BomName' => $registro->BomName,
                'HiloAX' => $registro->HiloAX,
                'MtsRollo' => $registro->MtsRollo,
                'PzasRollo' => $registro->PzasRollo,
                'TotalRollos' => $registro->TotalRollos,
                'TotalPzas' => $registro->TotalPzas,
                'Repeticiones' => $registro->Repeticiones !== null ? (int)$registro->Repeticiones : null,
                'NoMarbete' => $registro->SaldoMarbete !== null ? (float)$registro->SaldoMarbete : null, // SaldoMarbete en ReqProgramaTejido = NoMarbete en CatCodificados
                'CombinaTram' => $registro->CombinaTram,
                'Densidad' => $registro->Densidad !== null ? (float)$registro->Densidad : null,
                'CreaProd' => $registro->CreaProd ?? 1,
                'ActualizaLmat' => $registro->ActualizaLmat ?? 0,
            ];

            $updated = false;

            // Asignar todos los campos del payload, EXCEPTO TotalRollos y TotalPzas que se manejarán después
            foreach ($payload as $column => $value) {
                // Saltar TotalRollos y TotalPzas, se manejarán después
                if ($column === 'TotalRollos' || $column === 'TotalPzas') {
                    continue;
                }

                if (!in_array($column, $columns, true)) {
                    continue;
                }

                // Asignar siempre, sin comparación (para asegurar que se actualicen)
                $registroCodificado->setAttribute($column, $value);
                $updated = true;
            }

            // FORZAR asignación de TotalRollos y TotalPzas SIEMPRE (incluso si son null)
            // Estos campos deben guardarse exactamente como están en ReqProgramaTejido
            $valorTotalRollos = $registro->TotalRollos !== null ? (float)$registro->TotalRollos : null;
            $valorTotalPzas = $registro->TotalPzas !== null ? (float)$registro->TotalPzas : null;

            Log::info('Valores desde ReqProgramaTejido', [
                'total_rollos_original' => $registro->TotalRollos,
                'total_pzas_original' => $registro->TotalPzas,
                'total_rollos_convertido' => $valorTotalRollos,
                'total_pzas_convertido' => $valorTotalPzas,
            ]);

            if (in_array('TotalRollos', $columns, true)) {
                $registroCodificado->TotalRollos = $valorTotalRollos;
                $updated = true;
            }
            if (in_array('TotalPzas', $columns, true)) {
                $registroCodificado->TotalPzas = $valorTotalPzas;
                $updated = true;
            }

            // Aplicar campos de auditoría: primero creación si no existen, luego modificación
            // Usar false para aplicar ambos (creación y modificación)
            AuditoriaHelper::aplicarCamposAuditoria($registroCodificado, false);

            // Forzar UsuarioCrea si no existe y la columna existe
            if (in_array('UsuarioCrea', $columns, true)) {
                $usuarioActual = trim((string)($registroCodificado->UsuarioCrea ?? ''));
                if (empty($usuarioActual)) {
                    $usuario = AuditoriaHelper::obtenerUsuarioActual();
                    $registroCodificado->UsuarioCrea = $usuario;
                    $updated = true;
                    Log::info('UsuarioCrea asignado', ['usuario' => $usuario]);
                }
            }

            // Verificar valores antes de guardar
            $valoresAntesGuardar = [
                'TotalRollos' => $registroCodificado->TotalRollos,
                'TotalPzas' => $registroCodificado->TotalPzas,
                'UsuarioCrea' => $registroCodificado->UsuarioCrea,
                'is_dirty' => $registroCodificado->isDirty(),
                'dirty_attributes' => $registroCodificado->getDirty(),
            ];

            Log::info('Antes de guardar CatCodificados', [
                'no_produccion' => $noProduccion,
                'updated' => $updated,
                'valores' => $valoresAntesGuardar,
            ]);

            if ($updated || $registroCodificado->isDirty()) {
                try {
                    // Guardar usando fill() para asegurar que se guarden todos los cambios
                    $registroCodificado->save();

                    // Recargar desde BD para verificar
                    $registroCodificado->refresh();

                    $valoresDespuesGuardar = [
                        'TotalRollos' => $registroCodificado->TotalRollos,
                        'TotalPzas' => $registroCodificado->TotalPzas,
                        'UsuarioCrea' => $registroCodificado->UsuarioCrea,
                    ];

                    Log::info('CatCodificados actualizado correctamente', [
                        'no_produccion' => $noProduccion,
                        'antes' => $valoresAntesGuardar,
                        'despues' => $valoresDespuesGuardar,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al guardar CatCodificados', [
                        'no_produccion' => $noProduccion,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'dirty_attributes' => $registroCodificado->getDirty(),
                        'valores_antes' => $valoresAntesGuardar,
                    ]);
                }
            } else {
                Log::warning('No se guardó CatCodificados - no hay cambios', [
                    'no_produccion' => $noProduccion,
                    'updated' => $updated,
                    'is_dirty' => $registroCodificado->isDirty(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('EXCEPCIÓN en actualizarCatCodificados', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'registro_id' => $registro->Id ?? 'null',
                'no_produccion' => $registro->NoProduccion ?? 'null',
            ]);
        }

        Log::info('=== FIN actualizarCatCodificados ===');
    }

    /**
     * Obtiene el peso del rollo desde la tabla ReqPesosRolloTejido
     * Busca primero por tamaño FEL si el registro tiene FEL en InventSizeId
     * Si no encuentra, busca por tamaño DEF (default)
     * Si no encuentra nada, retorna null (para usar 41.5 como default)
     *
     * @param ReqProgramaTejido $registro
     * @return float|null
     */
    private function obtenerPesoRollo(ReqProgramaTejido $registro): ?float
    {
        try {
            $itemId = trim((string)($registro->ItemId ?? ''));
            $inventSizeId = trim((string)($registro->InventSizeId ?? ''));

            if (empty($itemId)) {
                return null;
            }

            // Si el registro tiene FEL en InventSizeId, buscar por FEL primero
            $buscarFel = !empty($inventSizeId) && (stripos($inventSizeId, 'FEL') !== false);

            if ($buscarFel) {
                $pesoRollo = ReqPesosRollosTejido::where('ItemId', $itemId)
                    ->where('InventSizeId', 'FEL')
                    ->first();

                if ($pesoRollo && $pesoRollo->PesoRollo !== null) {
                    return (float) $pesoRollo->PesoRollo;
                }
            }

            // Si no se encontró FEL, buscar por DEF (default)
            $pesoRollo = ReqPesosRollosTejido::where('ItemId', $itemId)
                ->where('InventSizeId', 'DEF')
                ->first();

            if ($pesoRollo && $pesoRollo->PesoRollo !== null) {
                return (float) $pesoRollo->PesoRollo;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calcula la fecha programada basada en la fórmula INN
     *
     * @param ReqProgramaTejido $registro
     * @param Carbon $hoy
     * @param Carbon $fechaFormula
     * @return Carbon|null
     */
    private function calcularFechaProgramada(ReqProgramaTejido $registro, Carbon $hoy, Carbon $fechaFormula): ?Carbon
    {
        if (!$registro->FechaInicio) {
            return null;
        }

        $fechaInicio = $registro->FechaInicio instanceof Carbon
            ? $registro->FechaInicio->copy()->startOfDay()
            : Carbon::parse($registro->FechaInicio)->startOfDay();

        return $fechaInicio->lte($fechaFormula) ? $hoy->copy() : null;
    }


}
