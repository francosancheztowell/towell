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
    public function index()
    {
        try {
            $items = EngProduccionFormulacionModel::orderBy('Folio', 'desc')->get();
            $usuarios = SYSUsuario::orderBy('nombre', 'asc')->get();
            $maquinas = URDCatalogoMaquina::where('Departamento', 'Engomado')
                ->orderBy('Nombre', 'asc')
                ->get();

            // Obtener folios de EngProgramaEngomado con Status diferente de 'Finalizado'
            $foliosPrograma = EngProgramaEngomado::where('Status', '!=', 'Finalizado')
                ->orderBy('Folio', 'desc')
                ->get(['Folio', 'Cuenta', 'Calibre', 'RizoPie', 'BomFormula']);

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
        }

        return view("modulos.engomado.captura-formula.index", compact("items", "usuarios", "maquinas", "foliosPrograma", "folioSugerido"));
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
        ]);

        try {
            if (empty($validated['fecha'])) {
                $validated['fecha'] = date('Y-m-d');
            }

            // Usar el FolioProg seleccionado como Folio principal
            $folio = $request->input('FolioProg');

            // Verificar si ya existe un registro con ese folio
            $existingRecord = EngProduccionFormulacionModel::where('Folio', $folio)->first();

            if ($existingRecord) {
                return redirect()->back()
                    ->with('error', 'Ya existe una formulación con el folio: ' . $folio);
            }

            $validated['Folio'] = $folio;
            $validated['Status'] = 'Creado';

            $formulacion = EngProduccionFormulacionModel::create($validated);

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
        ]);

        try {
            $item = EngProduccionFormulacionModel::where('Folio', $folio)->firstOrFail();
            $item->update($validated);
            return redirect()->back()->with('success', 'Formulación actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar formulación: ' . $e->getMessage());
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
}
