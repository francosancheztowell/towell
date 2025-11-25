<?php

namespace App\Http\Controllers;

use App\Models\EngProduccionFormulacionModel;
use App\Models\EngFormulacionLineModel;
use App\Models\SYSUsuario;
use App\Models\URDCatalogoMaquina;
use App\Models\EngProgramaEngomado;
use App\Helpers\FolioHelper;
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
            $folioSugerido = 'ENG-FORM-' . date('Y') . '-0001';
            Log::error('Error al cargar Formulación de Engomado: ' . $e->getMessage());
        }
        
        return view("modulos.engomado.captura-formula.index", compact("items", "usuarios", "maquinas", "folioSugerido"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Hora' => 'nullable|string|max:10',
            'MaquinaId' => 'required|string|max:50',
            'Cuenta' => 'nullable|string|max:50',
            'Calibre' => 'nullable|numeric',
            'Tipo' => 'nullable|string|max:50',
            'CveEmpl' => 'nullable|string|max:50',
            'NomEmpl' => 'required|string|max:255',
            'Olla' => 'nullable|string|max:50',
            'Formula' => 'nullable|string|max:100',
            'Kilos' => 'nullable|numeric',
            'Litros' => 'nullable|numeric',
            'ProdId' => 'nullable|string|max:50',
            'TiempoCocinado' => 'nullable|numeric',
            'Solidos' => 'nullable|numeric',
            'Viscocidad' => 'nullable|numeric',
        ], [
            'NomEmpl.required' => 'Debe seleccionar un empleado',
            'MaquinaId.required' => 'Debe seleccionar una máquina',
        ]);

        try {
            // Generar folio automáticamente: ENG-FORM-YYYY-####
            $year = date('Y');
            $prefix = "ENG-FORM-{$year}-";
            
            // Obtener el último folio del año actual
            $lastRecord = EngProduccionFormulacionModel::where('Folio', 'like', $prefix . '%')
                ->orderBy('Folio', 'desc')
                ->first();
            
            if ($lastRecord) {
                // Extraer el número del último folio
                $lastNumber = (int) substr($lastRecord->Folio, strlen($prefix));
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
            
            $folio = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
            
            $validated['Folio'] = $folio;
            $validated['Status'] = 'Creado';
            
            $formulacion = EngProduccionFormulacionModel::create($validated);
            
            return redirect()->back()
                ->with('success', 'Formulación creada exitosamente con folio: ' . $folio);
        } catch (\Exception $e) {
            Log::error('Error al crear formulación: ' . $e->getMessage());
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
     * Obtener componentes de la fórmula desde AX (Bom + InventDim)
     */
    public function getComponentesFormula(Request $request)
    {
        try {
            $formula = $request->query('formula');
            
            if (!$formula) {
                return response()->json(['error' => 'No se proporcionó el código de fórmula'], 400);
            }

            // Consultar TOW_PRO: Bom INNER JOIN InventDim con filtros especificados
            $componentes = DB::connection('sqlsrv_tow_pro')
                ->table('Bom as B')
                ->join('InventDim as I', 'B.InventDimId', '=', 'I.InventDimId')
                ->select(
                    'B.BomId',
                    'B.ItemId',
                    'B.BomQty as ConsumoUnitario',
                    'I.ConfigId',
                    'I.InventLocationId'
                )
                ->where('B.BomId', $formula)
                ->where('B.DATAAREAID', 'PRO')
                ->where('I.DATAAREAID', 'PRO')
                ->orderBy('B.LineNum')
                ->get();

            return response()->json([
                'success' => true,
                'componentes' => $componentes,
                'total' => $componentes->count(),
                'vacio' => $componentes->isEmpty()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener componentes: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function update(Request $request, $folio)
    {
        $validated = $request->validate([
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
