<?php

namespace App\Http\Controllers;

use App\Models\EngProduccionFormulacionModel;
use App\Models\EngFormulacionLineModel;
use App\Models\SYSUsuario;
use App\Models\URDCatalogoMaquina;
use App\Helpers\FolioHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            
            // Folio sugerido para producción de formulación
            $folioSugerido = FolioHelper::obtenerFolioSugerido('Eng Formulacion', 3);
        } catch (\Exception $e) {
            $items = collect([]);
            $usuarios = collect([]);
            $maquinas = collect([]);
            $folioSugerido = '';
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
            // Generar folio automáticamente
            $folio = FolioHelper::obtenerSiguienteFolio('Eng Formulacion', 3);
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
