<?php

namespace App\Http\Controllers\Engomado\CapturaFormulas;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngFormulacionLineModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EngProduccionFormulacionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $folioFiltro = $request->query('folio');

            // Ordenar por ID descendente para mostrar los más recientes primero
            // Si hay múltiples registros con el mismo Folio, se distinguen por ID
            $itemsQuery = EngProduccionFormulacionModel::orderBy('Id', 'desc');
            if (!empty($folioFiltro)) {
                $itemsQuery->where('Folio', $folioFiltro);
            }
            $items = $itemsQuery->get();
            $usuarios = SYSUsuario::orderBy('nombre', 'asc')->get();
            $maquinas = URDCatalogoMaquina::where('Departamento', 'Engomado')
                ->orderBy('Nombre', 'asc')
                ->get();

            // Obtener folios de EngProgramaEngomado con Status diferente de 'Finalizado'
            $foliosProgramaQuery = EngProgramaEngomado::where('Status', '!=', 'Finalizado')
                ->orderBy('Folio', 'desc');
            if (!empty($folioFiltro)) {
                $foliosProgramaQuery->where('Folio', $folioFiltro);
            }
            $foliosPrograma = $foliosProgramaQuery->get(['Folio', 'Cuenta', 'Calibre', 'RizoPie', 'BomFormula', 'Status']);

            // Generar folio sugerido
            $year = date('Y');
            $prefix = "ENG-FORM-{$year}-";
            $lastRecord = EngProduccionFormulacionModel::where('Folio', 'like', $prefix . '%')
                ->orderBy('Folio', 'desc')
                ->first();

            if ($lastRecord) {
                $lastNumber = (int) substr($lastRecord->Folio, strlen($prefix));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            $folioSugerido = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            $items = collect([]);
            $usuarios = collect([]);
            $maquinas = collect([]);
            $foliosPrograma = collect([]);
            $folioSugerido = 'ENG-FORM-' . date('Y') . '-0001';
            $folioFiltro = $request->query('folio');
        }

        // Si hay folio, obtener orden_id para el enlace "Volver a producción"
        $ordenIdProduccion = null;
        if (!empty($folioFiltro)) {
            $ordenEngomado = EngProgramaEngomado::where('Folio', $folioFiltro)->first();
            if ($ordenEngomado) {
                $ordenIdProduccion = $ordenEngomado->Id;
            }
        }

        return view("modulos.engomado.captura-formula.index", compact("items", "usuarios", "maquinas", "foliosPrograma", "folioSugerido", "folioFiltro", "ordenIdProduccion"));
    }

    public function store(Request $request)
    {
        // Validar que se haya seleccionado un folio de programa
        $request->validate([
            'FolioProg' => 'required|string|max:50',
        ], [
            'FolioProg.required' => 'Debe seleccionar un folio de programa',
        ]);

        $validated = $request->validate([
            'fecha' => 'nullable|date',
            'Hora' => 'nullable|string|max:10',
            'MaquinaId' => 'nullable|string|max:50',
            'Cuenta' => 'nullable|string|max:50',
            'Calibre' => 'nullable|numeric',
            'Tipo' => 'nullable|string|max:50',
            'CveEmpl' => 'nullable|string|max:50',
            'NomEmpl' => 'nullable|string|max:255',
            'Olla' => 'nullable|string|max:50',
            'Formula' => 'nullable|string|max:100',
            'Kilos' => 'required|numeric|min:0',
            'Litros' => 'required|numeric|min:0.01',
            'ProdId' => 'nullable|string|max:50',
            'TiempoCocinado' => 'required|numeric|min:0.01',
            'Solidos' => 'required|numeric|min:0.01',
            'Viscocidad' => 'required|numeric|min:0.01',
            'componentes' => 'nullable|string',
        ], [
            'Kilos.min' => 'Los Kilos no pueden ser negativos.',
            'Litros.required' => 'Los litros son obligatorios.',
            'Litros.min' => 'Los litros deben ser mayor a cero.',
            'TiempoCocinado.min' => 'El tiempo cocinado debe ser mayor a cero.',
            'Solidos.min' => 'El % sólidos debe ser mayor a cero.',
            'Viscocidad.min' => 'La viscosidad debe ser mayor a cero.',
        ]);

        try {
            if (empty($validated['fecha'])) {
                $validated['fecha'] = date('Y-m-d');
            }

            // Usar el FolioProg seleccionado como Folio principal
            $folio = $request->input('FolioProg');

            // Validar que el folio exista en EngProgramaEngomado
            $programa = EngProgramaEngomado::where('Folio', $folio)->first();
            if (!$programa) {
                return redirect()->back()
                    ->with('error', 'El folio no existe en el programa de engomado: ' . $folio);
            }
            if ($this->isFinalStatus($programa->Status ?? null)) {
                return redirect()->back()
                    ->with('error', 'No se puede registrar una formulación para un folio finalizado: ' . $folio);
            }

            $validated['Folio'] = $folio;
            $validated['Status'] = 'Creado';
            $validated['MaquinaId'] = $programa->MaquinaEng;
            $validated['Solidos'] = round((float) ($validated['Solidos'] ?? 0), 2);
            $validated['CveEmpl'] = $programa->CveEmpl;

            DB::transaction(function () use ($validated, $folio, $request) {
                // Crear el encabezado y obtener el Id generado
                $formulacion = EngProduccionFormulacionModel::create($validated);
                $formulacionId = $formulacion->Id;

                $componentesRaw = $request->input('componentes');
                if ($componentesRaw) {
                    $componentes = json_decode($componentesRaw, true);
                    if (is_array($componentes)) {
                        foreach ($componentes as $comp) {
                            // Truncar campos de texto para evitar errores de truncamiento en BD
                            EngFormulacionLineModel::create([
                                'Folio' => $folio,
                                'EngProduccionFormulacionId' => $formulacionId, // Nueva FK
                                'ItemId' => $this->truncateString($comp['ItemId'] ?? null, 20),
                                'ItemName' => $this->truncateString($comp['ItemName'] ?? null, 100),
                                'ConfigId' => $this->truncateString($comp['ConfigId'] ?? null, 20),
                                'ConsumoUnit' => $comp['ConsumoUnitario'] ?? null,
                                'ConsumoTotal' => $comp['ConsumoTotal'] ?? null,
                                'Unidad' => $this->truncateString($comp['Unidad'] ?? null, 10),
                                'InventLocation' => $this->truncateString($comp['Almacen'] ?? null, 20),
                            ]);
                        }
                    }
                }
            });

            return redirect()->back()
                ->with('success', 'Formulación creada exitosamente con folio: ' . $folio);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al crear la formulación: ' . $e->getMessage());
        }
    }

    /**
     * Validar folio y obtener datos de EngProgramaEngomado
     */
    public function validarFolio(Request $request)
    {
        try {
            $folio = $request->query('folio');

            if (!$folio) {
                return response()->json(['error' => 'No se proporcionó el folio'], 400);
            }

            $programa = EngProgramaEngomado::where('Folio', $folio)->first();

            if (!$programa) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró el folio en EngProgramaEngomado'
                ], 404);
            }

            // Obtener operador actual del sistema
            $usuario = Auth::user();
            $operador = $usuario ? $usuario->nombre : '';

            return response()->json([
                'success' => true,
                'data' => [
                    'Cuenta' => $programa->Cuenta,
                    'Calibre' => $programa->Calibre,
                    'Tipo' => $programa->RizoPie,
                    'NomEmpl' => $operador,
                    'CveEmpl' => $usuario ? $usuario->numero : '',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al validar folio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos completos de la formulación por ID
     * Usado para editar formulaciones existentes
     * IMPORTANTE: Trae componentes SOLO por EngProduccionFormulacionId (no por Folio)
     */
    public function getFormulacionById(Request $request)
    {
        try {
            $id = $request->query('id');

            if (!$id) {
                return response()->json(['error' => 'No se proporcionó el ID'], 400);
            }

            // Obtener formulación por ID
            $formulacion = EngProduccionFormulacionModel::find($id);

            if (!$formulacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la formulación con ID: ' . $id
                ], 404);
            }

            // IMPORTANTE: SELECT * FROM EngFormulacionLine WHERE EngProduccionFormulacionId = {id}
            // Solo traer componentes vinculados específicamente a este ID, NO por Folio
            // Validar que el ID sea numérico
            $idFormulacion = (int) $id;

            // Query directo: SELECT * FROM EngFormulacionLine WHERE EngProduccionFormulacionId = {id}
            $componentes = EngFormulacionLineModel::where('EngProduccionFormulacionId', $idFormulacion)
                ->orderBy('Id')
                ->get();

            // Verificar que todos los componentes pertenezcan al ID correcto
            $componentesInvalidos = $componentes->filter(function($line) use ($idFormulacion) {
                return (int)$line->EngProduccionFormulacionId !== $idFormulacion;
            });

            if ($componentesInvalidos->count() > 0) {
                Log::warning('Se encontraron componentes con EngProduccionFormulacionId incorrecto', [
                    'formulacion_id_esperado' => $idFormulacion,
                    'componentes_invalidos' => $componentesInvalidos->pluck('Id')->toArray()
                ]);
            }

            $componentes = $componentes->map(function ($line) {
                return [
                    'Id' => $line->Id,
                    'ItemId' => $line->ItemId,
                    'ItemName' => $line->ItemName,
                    'ConfigId' => $line->ConfigId,
                    'ConsumoUnitario' => $line->ConsumoUnit,
                    'ConsumoTotal' => $line->ConsumoTotal,
                    'Unidad' => $line->Unidad,
                    'Almacen' => $line->InventLocation,
                ];
            });

            // Log para verificar que solo trae los componentes del ID específico
            Log::info('Obteniendo formulación por ID para editar', [
                'formulacion_id' => $idFormulacion,
                'folio' => $formulacion->Folio,
                'componentes_encontrados' => $componentes->count(),
                'componentes_ids' => $componentes->pluck('Id')->toArray(),
                'sql_query' => 'SELECT * FROM EngFormulacionLine WHERE EngProduccionFormulacionId = ' . $idFormulacion
            ]);

            return response()->json([
                'success' => true,
                'formulacion' => [
                    'Id' => $formulacion->Id,
                    'Folio' => $formulacion->Folio,
                    'fecha' => $formulacion->fecha ? $formulacion->fecha->format('Y-m-d') : null,
                    'Hora' => $formulacion->Hora,
                    'MaquinaId' => $formulacion->MaquinaId,
                    'Cuenta' => $formulacion->Cuenta,
                    'Calibre' => $formulacion->Calibre,
                    'Tipo' => $formulacion->Tipo,
                    'CveEmpl' => $formulacion->CveEmpl,
                    'NomEmpl' => $formulacion->NomEmpl,
                    'Olla' => $formulacion->Olla,
                    'Formula' => $formulacion->Formula,
                    'Kilos' => $formulacion->Kilos,
                    'Litros' => $formulacion->Litros,
                    'ProdId' => $formulacion->ProdId,
                    'TiempoCocinado' => $formulacion->TiempoCocinado,
                    'Solidos' => $formulacion->Solidos !== null ? round((float) $formulacion->Solidos, 2) : null,
                    'Viscocidad' => $formulacion->Viscocidad,
                    'Status' => $formulacion->Status,
                    'obs_calidad' => $formulacion->obs_calidad,
                ],
                'componentes' => $componentes,
                'total' => $componentes->count(),
                'vacio' => $componentes->isEmpty()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener formulación por ID: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Error al obtener formulación: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtener componentes guardados desde EngFormulacionLine por ID de formulación
     * Usado para editar formulaciones existentes
     */
    public function getComponentesFormulacion(Request $request)
    {
        try {
            $id = $request->query('id');
            $folio = $request->query('folio'); // Mantener compatibilidad con Folio

            if (!$id && !$folio) {
                return response()->json(['error' => 'No se proporcionó el ID ni el Folio'], 400);
            }

            // Priorizar búsqueda por ID (EngProduccionFormulacionId)
            // IMPORTANTE: Usar EngProduccionFormulacionId para traer solo los componentes vinculados a este ID específico
            if ($id) {
                $componentes = EngFormulacionLineModel::byFormulacionId($id)
                    ->orderBy('Id')
                    ->get();
            } else {
                // Fallback a Folio para compatibilidad (solo si no se proporciona ID)
                $componentes = EngFormulacionLineModel::where('Folio', $folio)
                    ->orderBy('Id')
                    ->get();
            }

            $componentes = $componentes->map(function ($line) {
                return [
                    'Id' => $line->Id,
                    'ItemId' => $line->ItemId,
                    'ItemName' => $line->ItemName,
                    'ConfigId' => $line->ConfigId,
                    'ConsumoUnitario' => $line->ConsumoUnit,
                    'ConsumoTotal' => $line->ConsumoTotal,
                    'Unidad' => $line->Unidad,
                    'Almacen' => $line->InventLocation,
                ];
            });

            return response()->json([
                'success' => true,
                'componentes' => $componentes,
                'total' => $componentes->count(),
                'vacio' => $componentes->isEmpty()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener componentes de formulación: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Error al obtener componentes: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtener componentes de la fórmula desde AX (BOMVersion + Bom + InventTable + InventDim)
     * JOIN: BOMVersion -> Bom (por BomId) -> InventTable (por ItemId) -> InventDim (por InventDimId)
     * Filtros: BOMVersion.ItemId = Formula, IT.DATAAREAID = 'PRO', B.DATAAREAID = 'PRO', ID.DATAAREAID = 'PRO'
     * Usado para crear nuevas formulaciones
     */
    public function getComponentesFormula(Request $request)
    {
        try {
            $formula = $request->query('formula');

            if (!$formula) {
                return response()->json(['error' => 'No se proporcionó el código de fórmula'], 400);
            }

            // Consultar: BOMVersion + Bom + InventTable + InventDim con filtros especificados
            $componentes = DB::connection('sqlsrv_ti')
                ->table('BOMVersion as BV')
                ->join('Bom as B', 'B.BomId', '=', 'BV.BomId')
                ->join('InventTable as IT', 'IT.ItemId', '=', 'B.ItemId')
                ->join('InventDim as ID', 'B.InventDimId', '=', 'ID.InventDimId')
                ->select(
                    'B.BomId',
                    'B.ItemId',
                    'IT.ItemName',
                    'ID.ConfigId',
                    'B.BomQty as ConsumoUnitario',
                    'B.UnitId as Unidad',
                    'ID.InventLocationId as Almacen',
                    'B.InventDimId'
                )
                ->where('BV.ItemId', $formula)
                ->where('IT.DATAAREAID', 'PRO')
                ->where('B.DATAAREAID', 'PRO')
                ->where('ID.DATAAREAID', 'PRO')
                ->orderBy('B.LineNum')
                ->get();

            return response()->json([
                'success' => true,
                'componentes' => $componentes,
                'total' => $componentes->count(),
                'vacio' => $componentes->isEmpty()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener componentes: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Error al obtener componentes: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function update(Request $request, $folio)
    {
        $validated = $request->validate([
            'fecha' => 'nullable|date',
            'Hora' => 'nullable|string|max:10',
            'MaquinaId' => 'nullable|string|max:50',
            'Cuenta' => 'nullable|string|max:50',
            'Calibre' => 'nullable|numeric',
            'Tipo' => 'nullable|string|max:50',
            'CveEmpl' => 'nullable|string|max:50',
            'NomEmpl' => 'nullable|string|max:255',
            'Olla' => 'nullable|string|max:50',
            'Formula' => 'nullable|string|max:100',
            'Kilos' => 'nullable|numeric|min:0',
            'Litros' => 'nullable|numeric|min:0.01',
            'ProdId' => 'nullable|string|max:50',
            'TiempoCocinado' => 'nullable|numeric|min:0.01',
            'Solidos' => 'nullable|numeric|min:0.01',
            'Viscocidad' => 'nullable|numeric|min:0.01',
            'Status' => 'nullable|in:Creado,En Proceso,Terminado',
            'obs_calidad' => 'nullable|string',
            'ok_tiempo' => 'nullable|in:0,1',
            'ok_viscocidad' => 'nullable|in:0,1',
            'ok_solidos' => 'nullable|in:0,1',
            'componentes' => 'nullable|string',
        ], [
            'Kilos.min' => 'Los Kilos no pueden ser negativos.',
            'Litros.min' => 'Los litros deben ser mayor a cero.',
            'TiempoCocinado.min' => 'El tiempo cocinado debe ser mayor a cero.',
            'Solidos.min' => 'El % sólidos debe ser mayor a cero.',
            'Viscocidad.min' => 'La viscosidad debe ser mayor a cero.',
        ]);

        try {
            DB::transaction(function () use ($validated, $folio, $request) {
                // Priorizar Id cuando hay múltiples registros con el mismo Folio
                $idFromRequest = $request->input('formulacion_id');
                if (!empty($idFromRequest)) {
                    $item = EngProduccionFormulacionModel::findOrFail((int) $idFromRequest);
                } else {
                    $item = EngProduccionFormulacionModel::where('Folio', $folio)->firstOrFail();
                }
                // Solo actualizar campos que vienen en el request (evitar sobrescribir con null campos no enviados por el form)
                $toUpdate = collect($validated)->except('componentes')->filter(function ($v, $k) use ($request) {
                    return $request->has($k);
                })->toArray();
                // Mapear ok_tiempo, ok_viscocidad, ok_solidos: null = vacío, 0 = tache, 1 = palomita
                if (array_key_exists('ok_tiempo', $toUpdate)) {
                    $v = $toUpdate['ok_tiempo'];
                    $toUpdate['OkTiempo'] = ($v === null || $v === '') ? null : (int) $v;
                    unset($toUpdate['ok_tiempo']);
                }
                if (array_key_exists('ok_viscocidad', $toUpdate)) {
                    $v = $toUpdate['ok_viscocidad'];
                    $toUpdate['OkViscosidad'] = ($v === null || $v === '') ? null : (int) $v;
                    unset($toUpdate['ok_viscocidad']);
                }
                if (array_key_exists('ok_solidos', $toUpdate)) {
                    $v = $toUpdate['ok_solidos'];
                    $toUpdate['OkSolidos'] = ($v === null || $v === '') ? null : (int) $v;
                    unset($toUpdate['ok_solidos']);
                }
                if (array_key_exists('Solidos', $toUpdate)) {
                    $toUpdate['Solidos'] = round((float) $toUpdate['Solidos'], 2);
                }
                $item->update($toUpdate);
                $formulacionId = $item->Id; // Obtener el ID real de la tabla

                // Actualizar componentes si se proporcionan
                $componentesRaw = $request->input('componentes');
                if ($componentesRaw !== null) {
                    // Eliminar componentes existentes por EngProduccionFormulacionId
                    // IMPORTANTE: Usar EngProduccionFormulacionId para eliminar solo los componentes vinculados a este ID específico
                    EngFormulacionLineModel::byFormulacionId($formulacionId)->delete();

                    // Insertar nuevos componentes
                    $componentes = json_decode($componentesRaw, true);
                    if (is_array($componentes) && count($componentes) > 0) {
                        foreach ($componentes as $comp) {
                            // Truncar campos de texto para evitar errores de truncamiento en BD
                            EngFormulacionLineModel::create([
                                'Folio' => $folio, // Mantener Folio para compatibilidad
                                'EngProduccionFormulacionId' => $formulacionId, // FK principal
                                'ItemId' => $this->truncateString($comp['ItemId'] ?? null, 20),
                                'ItemName' => $this->truncateString($comp['ItemName'] ?? null, 100),
                                'ConfigId' => $this->truncateString($comp['ConfigId'] ?? null, 20),
                                'ConsumoUnit' => $comp['ConsumoUnitario'] ?? null,
                                'ConsumoTotal' => $comp['ConsumoTotal'] ?? null,
                                'Unidad' => $this->truncateString($comp['Unidad'] ?? null, 10),
                                'InventLocation' => $this->truncateString($comp['Almacen'] ?? null, 20),
                            ]);
                        }
                    }
                }
            });

            // Si es una petición JSON (desde AJAX), devolver JSON
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Formulación actualizada exitosamente']);
            }

            return redirect()->back()->with('success', 'Formulación actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar formulación: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Error al actualizar la formulación: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Error al actualizar la formulación: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, $folio)
    {
        try {
            DB::beginTransaction();

            // Priorizar Id cuando hay múltiples registros con el mismo Folio
            $idFromRequest = $request->input('formulacion_id');
            if (!empty($idFromRequest)) {
                $item = EngProduccionFormulacionModel::findOrFail((int) $idFromRequest);
            } else {
                $item = EngProduccionFormulacionModel::where('Folio', $folio)->firstOrFail();
            }
            $formulacionId = $item->Id;

            // Eliminar líneas asociadas por EngProduccionFormulacionId
            // IMPORTANTE: Usar EngProduccionFormulacionId para eliminar solo los componentes vinculados a este ID específico
            EngFormulacionLineModel::byFormulacionId($formulacionId)->delete();

            // Eliminar encabezado
            $item->delete();

            DB::commit();
            return redirect()->back()->with('success', 'Formulación eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar formulación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar la formulación: ' . $e->getMessage());
        }
    }

    /**
     * Obtener calibres para selects de fórmula (ItemGroupId = 'MAT P ENG')
     */
    public function getCalibresFormula()
    {
        try {
            $items = DB::connection('sqlsrv_ti')
                ->table('InventTable')
                ->select('ItemId', 'ItemName')
                ->where('ItemGroupId', 'MAT P ENG')
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ItemId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo calibres de fórmula', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener fibras para selects de fórmula (basado en ItemGroupId = 'MAT P ENG')
     */
    public function getFibrasFormula(Request $request)
    {
        $itemId = $request->query('itemId');
        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $fibras = DB::connection('sqlsrv_ti')
                ->table('ConfigTable')
                ->select('ConfigId')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ConfigId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $fibras]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo fibras de fórmula', ['exception' => $e, 'itemId' => $itemId]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener colores para selects de fórmula (basado en ItemGroupId = 'MAT P ENG')
     */
    public function getColoresFormula(Request $request)
    {
        $itemId = $request->query('itemId');
        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $colores = DB::connection('sqlsrv_ti')
                ->table('InventColor')
                ->select('InventColorId', 'Name')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('InventColorId')
                ->get();

            return response()->json(['success' => true, 'data' => $colores]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo colores de fórmula', ['exception' => $e, 'itemId' => $itemId]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trunca un string a la longitud máxima especificada
     *
     * @param string|null $value
     * @param int $maxLength
     * @return string|null
     */
    private function truncateString($value, $maxLength)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        if (mb_strlen($value) > $maxLength) {
            Log::warning('Valor truncado en EngFormulacionLine', [
                'valor_original' => $value,
                'longitud_original' => mb_strlen($value),
                'longitud_maxima' => $maxLength,
                'valor_truncado' => mb_substr($value, 0, $maxLength)
            ]);
            return mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    private function isFinalStatus(?string $status): bool
    {
        $status = mb_strtoupper(trim((string) $status));
        return in_array($status, ['FINALIZADO', 'TERMINADO'], true);
    }
}
