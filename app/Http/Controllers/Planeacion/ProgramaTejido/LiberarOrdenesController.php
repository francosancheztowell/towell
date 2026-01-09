<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            // Obtener registros que NO tienen orden de producción (NoProduccion es null o vacío)
            // Solo los campos que se muestran en la tabla
            $registros = ReqProgramaTejido::select([
                'Id',
                'CuentaRizo',
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
                'PesoCrudo',
                'NoTiras',
                'LargoCrudo',
                'ItemId',
                'CalendarioId',
                'TamanoClave',
                'InventSizeId',
                'NoExisteBase',
                'NombreProducto',
                'TotalPedido',
                'SaldoPedido',
                'ProgramarProd',
                'NoProduccion',
                'Programado',
                'NombreProyecto',
                'AplicacionId',
                'Observaciones',
                'FechaInicio',
                'FechaFinal',
                'Prioridad',
                'PesoGRM2',
                'TipoPedido',
                'EntregaProduc',
                'EntregaPT',
                'EntregaCte',
                'PTvsCte',
                'MtsRollo',
                'PzasRollo',
                'TotalRollos',
                'TotalPzas',
                'Repeticiones',
                'SaldoMarbete',
                'CategoriaCalidad',
                'CombinaTram',
                'BomId',
                'BomName',
                'HiloAX'
            ])
            ->where(function($query) {
                $query->whereNull('NoProduccion')
                      ->orWhere('NoProduccion', '');
            })
            ->ordenado()
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
                    $repeticiones = floor(((41.5 / (float)$pCrudo) / (float)$tiras) * 1000);
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
                $mtsRollo = null;
                if (isset($registro->MtsRollo) && is_numeric($registro->MtsRollo)) {
                    $mtsRollo = (float) $registro->MtsRollo;
                } else {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo) ? (float)$largo : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            $mtsRollo = round($largoNum * $repeticiones / 100, 2);
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
                $registro->Densidad = $registro->PesoGRM2 ?? null;
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

                // Generar folio único
                $folio = FolioHelper::obtenerSiguienteFolio('Planeacion', 5);

                $programado = null;
                if ($registro->FechaInicio) {
                    $fechaInicio = $registro->FechaInicio instanceof Carbon
                        ? $registro->FechaInicio->copy()->startOfDay()
                        : Carbon::parse($registro->FechaInicio)->startOfDay();

                    if ($fechaInicio->lte($fechaFormula)) {
                        $programado = $hoy->copy();
                    }
                }

                $registro->Prioridad = $prioridad !== '' ? $prioridad : null;
                if ($programado) {
                    $registro->Programado = $programado;
                }
                $registro->NoProduccion = $folio;

                // Calcular Repeticiones: TRUNCAR((41.5/PesoCrudo)/NoTiras*1000)
                $pCrudo = $registro->PesoCrudo ?? null;
                $tiras = $registro->NoTiras ?? null;
                $repeticiones = null;
                if ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    $repeticiones = floor(((41.5 / (float)$pCrudo) / (float)$tiras) * 1000);
                }

                // Calcular NoMarbetes: TRUNCAR(SaldoPedido/NoTiras/Repeticiones)
                $cantidadProducir = $registro->SaldoPedido ?? null;
                $saldoMarbete = 0;
                if ($cantidadProducir !== null && $tiras && $repeticiones !== null &&
                    is_numeric($cantidadProducir) && is_numeric($tiras) && is_numeric($repeticiones) &&
                    $tiras > 0 && $repeticiones > 0) {
                    $saldoMarbete = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;
                }
                $saldoMarbeteInt = 0;
                if (is_numeric($saldoMarbete)) {
                    $saldoFloat = (float) $saldoMarbete;
                    $entero = (int) floor($saldoFloat);
                    $decimal = abs($saldoFloat - $entero);
                    $saldoMarbeteInt = $decimal >= 0.5 ? (int) ceil($saldoFloat) : $entero;
                }

                // Calcular MtsRollo: (LargoCrudo * Repeticiones) / 100
                $largo = $registro->LargoCrudo ?? null;
                $mtsRollo = null;
                if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                    $largoNum = is_numeric($largo) ? (float)$largo : (float)str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string)$largo);
                    if ($largoNum > 0 && $repeticiones > 0) {
                        $mtsRollo = round($largoNum * $repeticiones / 100, 2);
                    }
                }

                // Calcular PzasRollo: Repeticiones * NoTiras
                $pzasRollo = null;
                if ($repeticiones !== null && $tiras && is_numeric($repeticiones) && is_numeric($tiras) && $repeticiones > 0 && $tiras > 0) {
                    $pzasRollo = round($repeticiones * $tiras, 0);
                }

                // Calcular TotalRollos y TotalPzas
                $totalRollos = $saldoMarbeteInt;
                $totalPzas = null;
                if ($totalRollos !== null && $pzasRollo !== null && is_numeric($totalRollos) && is_numeric($pzasRollo)) {
                    $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
                }

                // Actualizar campos calculados
                $registro->Repeticiones = $repeticiones;
                $registro->SaldoMarbete = $saldoMarbeteInt;
                $registro->MtsRollo = $mtsRollo;
                $registro->PzasRollo = $pzasRollo;
                $registro->TotalRollos = $totalRollos;
                $registro->TotalPzas = $totalPzas;
                $registro->CombinaTram = $registro->CombinaTram ?? null;
                $registro->BomId = $registro->BomId ?? null;
                $registro->BomName = $registro->BomName ?? null;
                $registro->CreaProd = $registro->CreaProd ?? 1;
                $registro->EficienciaSTD = $registro->EficienciaSTD ?? null;
                $registro->Densidad = $registro->PesoGRM2 ?? null;
                $registro->HiloAX = $registro->HiloAX ?? null;
                $registro->ActualizaLmat = $registro->ActualizaLmat ?? 0;

                // Campos de auditoría
                $usuario = Auth::check() && Auth::user()
                    ? (Auth::user()->nombre ?? Auth::user()->numero_empleado ?? 'Sistema')
                    : 'Sistema';
                $fechaActual = now();
                $registro->setAttribute('FechaCreacion', $fechaActual);
                $registro->HoraCreacion = $fechaActual->format('H:i:s');
                $registro->UsuarioCrea = $usuario;
                $registro->setAttribute('FechaModificacion', $fechaActual);
                $registro->HoraModificacion = $fechaActual->format('H:i:s');
                $registro->UsuarioModifica = $usuario;

                $registro->save();

                // Recargar el modelo sin relaciones para evitar errores
                $registroActualizado = ReqProgramaTejido::select([
                    'Id',
                    'CuentaRizo',
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
                    'InventSizeId',
                    'NoExisteBase',
                    'NombreProducto',
                    'SaldoPedido',
                    'ProgramarProd',
                    'NoProduccion',
                    'Programado',
                    'NombreProyecto',
                    'AplicacionId',
                    'Observaciones',
                    'FechaFinal',
                    'Prioridad',
                    // Campos de color y combinaciones necesarios para CatCodificados
                    'CodColorTrama',
                    'ColorTrama',
                    'FibraTrama',
                    'CalibreTrama',
                    'PesoCrudo',
                    'Luchaje',
                    'Peine',
                    'NoTiras',
                    'ItemId',

                    'FlogsId',
                    'LargoCrudo',
                    'Rasurado',
                    'MedidaPlano',

                    'CalibreRizo',
                    'CalibreRizo2',
                    'CalibrePie',
                    'CuentaPie',
                    'FibraPie',
                    'CalibreComb1',
                    'CalibreComb12',
                    'FibraComb1',
                    'CodColorComb1',
                    'NombreCC1',
                    'PasadasComb1',
                    'CalibreComb2',
                    'CalibreComb22',
                    'FibraComb2',
                    'CodColorComb2',
                    'NombreCC2',
                    'PasadasComb2',
                    'CalibreComb3',
                    'CalibreComb32',
                    'FibraComb3',
                    'CodColorComb3',
                    'NombreCC3',
                    'PasadasComb3',
                    'CalibreComb4',
                    'CalibreComb42',
                    'FibraComb4',
                    'CodColorComb4',
                    'NombreCC4',
                    'PasadasComb4',
                    'CalibreComb5',
                    'CalibreComb52',
                    'FibraComb5',
                    'CodColorComb5',
                    'NombreCC5',
                    'PasadasComb5',
                    'PasadasTrama',
                    'TotalPedido',
                    'Produccion',
                    'SaldoMarbete',
                    'CategoriaCalidad',
                    'OrdCompartida',
                    'OrdCompartidaLider',
                    'CombinaTram',
                    'BomId',
                    'BomName',
                    'CreaProd',
                    'HiloAX',
                    'ActualizaLmat',
                    'PesoGRM2',
                ])->find($id);

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



    public function buscarBom(Request $request)
    {
        // Si viene el parámetro 'combinations', es para múltiples combinaciones (más rápido)
        $combinationsParam = trim((string) $request->query('combinations', ''));

        if ($combinationsParam !== '') {
            return $this->buscarBomMultiple($combinationsParam);
        }

        // Lógica original para una sola combinación (autocompletado)
        $itemId = trim((string) $request->query('itemId', ''));
        $inventSizeId = trim((string) $request->query('inventSizeId', ''));
        $term = trim((string) $request->query('term', ''));

        if ($itemId === '' || $inventSizeId === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $itemIdWithSuffix = $itemId . '-1';

            $query = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where('BV.ITEMID', $itemIdWithSuffix)
                ->where('BT.INVENTSIZEID', $inventSizeId);

            if ($term !== '') {
                $query->where(function ($q) use ($term) {
                    $q->where('BT.BOMID', 'like', '%' . $term . '%')
                      ->orWhere('BT.NAME', 'like', '%' . $term . '%');
                });
            }

            $results = $query->orderBy('BT.BOMID')->limit(20)->get();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al buscar BOMId', [
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

    private function buscarBomMultiple($combinationsParam)
    {
        try {
            // Formato: "itemId1:inventSizeId1,itemId2:inventSizeId2,..."
            $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

            if (empty($combinations)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $pairs = [];
            foreach ($combinations as $combo) {
                $parts = explode(':', $combo);
                if (count($parts) === 2) {
                    $itemId = trim($parts[0]);
                    $inventSizeId = trim($parts[1]);
                    $pairs[] = [
                        'itemId' => $itemId,
                        'itemIdWithSuffix' => $itemId . '-1',
                        'inventSizeId' => $inventSizeId,
                        'key' => $itemId . '|' . $inventSizeId
                    ];
                }
            }

            if (empty($pairs)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            // Consulta única optimizada con múltiples condiciones OR
            $results = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->select('BV.ITEMID', 'BT.INVENTSIZEID', 'BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where(function($query) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $query->orWhere(function($q) use ($pair) {
                            $q->where('BV.ITEMID', $pair['itemIdWithSuffix'])
                              ->where('BT.INVENTSIZEID', $pair['inventSizeId']);
                        });
                    }
                })
                ->orderBy('BT.BOMID')
                ->get();

            // Agrupar resultados por combinación (solo el primero si hay múltiples)
            $map = [];
            foreach ($results as $result) {
                $itemIdOriginal = str_replace('-1', '', $result->ITEMID);
                $key = $itemIdOriginal . '|' . $result->INVENTSIZEID;

                // Solo guardar el primer resultado por combinación
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
        } catch (\Exception $e) {
            Log::error('Error al buscar BOM múltiple', [
                'combinations' => $combinationsParam,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar L.Mat.',
            ], 500);
        }
    }

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
            $itemIdsWithSuffix = array_map(function($id) {
                return $id . '-1';
            }, $itemIds);

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
}
