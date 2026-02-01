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

            $itemsQuery = EngProduccionFormulacionModel::orderBy('Folio', 'desc');
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
            $foliosPrograma = $foliosProgramaQuery->get(['Folio', 'Cuenta', 'Calibre', 'RizoPie', 'BomFormula']);

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

        return view("modulos.engomado.captura-formula.index", compact("items", "usuarios", "maquinas", "foliosPrograma", "folioSugerido", "folioFiltro"));
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
            'Kilos' => 'nullable|numeric',
            'Litros' => 'nullable|numeric',
            'ProdId' => 'nullable|string|max:50',
            'TiempoCocinado' => 'nullable|numeric',
            'Solidos' => 'nullable|numeric',
            'Viscocidad' => 'nullable|numeric',
            'componentes' => 'nullable|string',
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

            $validated['Folio'] = $folio;
            $validated['Status'] = 'Creado';
            $validated['MaquinaId'] = $programa->MaquinaEng;
            $validated['CveEmpl'] = $programa->CveEmpl;

            DB::transaction(function () use ($validated, $folio, $request) {
                EngProduccionFormulacionModel::create($validated);

                $componentesRaw = $request->input('componentes');
                if ($componentesRaw) {
                    $componentes = json_decode($componentesRaw, true);
                    if (is_array($componentes)) {
                        foreach ($componentes as $comp) {
                            EngFormulacionLineModel::create([
                                'Folio' => $folio,
                                'ItemId' => $comp['ItemId'] ?? null,
                                'ItemName' => $comp['ItemName'] ?? null,
                                'ConfigId' => $comp['ConfigId'] ?? null,
                                'ConsumoUnit' => $comp['ConsumoUnitario'] ?? null,
                                'ConsumoTotal' => $comp['ConsumoTotal'] ?? null,
                                'Unidad' => $comp['Unidad'] ?? null,
                                'InventLocation' => $comp['Almacen'] ?? null,
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
     * Obtener componentes de la fórmula desde AX (BOMVersion + Bom + InventTable + InventDim)
     * JOIN: BOMVersion -> Bom (por BomId) -> InventTable (por ItemId) -> InventDim (por InventDimId)
     * Filtros: BOMVersion.ItemId = Formula, IT.DATAAREAID = 'PRO', B.DATAAREAID = 'PRO', ID.DATAAREAID = 'PRO'
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
            'Kilos' => 'nullable|numeric',
            'Litros' => 'nullable|numeric',
            'ProdId' => 'nullable|string|max:50',
            'TiempoCocinado' => 'nullable|numeric',
            'Solidos' => 'nullable|numeric',
            'Viscocidad' => 'nullable|numeric',
            'Status' => 'nullable|in:Creado,En Proceso,Terminado',
            'obs_calidad' => 'nullable|string',
        ]);

        try {
            $item = EngProduccionFormulacionModel::where('Folio', $folio)->firstOrFail();
            $item->update($validated);
            
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

    public function destroy($folio)
    {
        try {
            DB::beginTransaction();

            // Eliminar líneas asociadas
            EngFormulacionLineModel::where('Folio', $folio)->delete();

            // Eliminar encabezado
            $item = EngProduccionFormulacionModel::where('Folio', $folio)->firstOrFail();
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
}
