<?php

namespace App\Http\Controllers\Urdido\BPMUrdido;

use App\Http\Controllers\Controller;
use App\Models\UrdBpmModel;
use App\Models\SYSUsuario;
use App\Models\URDCatalogoMaquina;
use App\Helpers\FolioHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrdBpmController extends Controller
{
    public function index()
    {
        try {
            $items = UrdBpmModel::orderBy('Id', 'desc')->get();
            $usuarios = SYSUsuario::where('area', 'Urdido')->whereNotNull('numero_empleado')->orderBy('nombre','asc')->get();
            $maquinas = URDCatalogoMaquina::where('Departamento', 'Urdido')
                ->orderBy('Nombre', 'asc')
                ->get();
            $folioSugerido = FolioHelper::obtenerFolioSugerido('Urdido BPM', 3);
        } catch (\Exception $e) {
            $items = collect([]);
            $usuarios = collect([]);
            $maquinas = collect([]);
            $folioSugerido = '';
            Log::error('Error al cargar BPM Urdido: ' . $e->getMessage());
        }

        return view("modulos.urdido.BPM-Urdido.index", compact("items", "usuarios", "maquinas", "folioSugerido"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Fecha' => 'required|date',
            'CveEmplRec' => 'nullable|string|max:50',
            'NombreEmplRec' => 'required|string|max:255',
            'TurnoRecibe' => 'nullable|string|max:50',
            'CveEmplEnt' => 'nullable|string|max:50',
            'NombreEmplEnt' => 'nullable|string|max:255',
            'TurnoEntrega' => 'nullable|string|max:50',
            'CveEmplAutoriza' => 'nullable|string|max:50',
            'NombreEmplAutoriza' => 'nullable|string|max:255',
            'Status' => 'required|in:Creado,Terminado,Autorizado',
            'MaquinaId' => 'required|string|max:50',
            'Departamento' => 'nullable|string|max:100',
        ], [
            'NombreEmplRec.required' => 'Debe seleccionar quien recibe',
            'MaquinaId.required' => 'Debe seleccionar una máquina',
        ]);

        try {
            // Extraer MaquinaId y Departamento para usar en las líneas (no se guardan en UrdBPM)
            $maquinaId = $validated['MaquinaId'];
            $departamento = $validated['Departamento'] ?? 'Urdido';
            unset($validated['MaquinaId'], $validated['Departamento']);

            // Generar folio automáticamente con el módulo "Urdido BPM"
            $folio = FolioHelper::obtenerSiguienteFolio('Urdido BPM', 3);
            $validated['Folio'] = $folio;

            $header = UrdBpmModel::create($validated);

            // Guardar MaquinaId y Departamento en sesión para usarlos en las líneas
            session(['bpm_maquina_id' => $maquinaId, 'bpm_departamento' => $departamento]);

            // Redirigir a la vista de líneas del folio creado
            return redirect()->route('urd-bpm-line.index', $folio)
                ->with('success', 'Registro creado exitosamente con folio: ' . $folio);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al crear el registro: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'Folio' => 'required|string|max:50',
            'Fecha' => 'required|date',
            'CveEmplRec' => 'nullable|string|max:50',
            'NombreEmplRec' => 'nullable|string|max:255',
            'TurnoRecibe' => 'nullable|string|max:50',
            'CveEmplEnt' => 'nullable|string|max:50',
            'NombreEmplEnt' => 'nullable|string|max:255',
            'TurnoEntrega' => 'nullable|string|max:50',
            'CveEmplAutoriza' => 'nullable|string|max:50',
            'NombreEmplAutoriza' => 'nullable|string|max:255',
            'Status' => 'required|in:Creado,Terminado,Autorizado',
        ]);

        try {
            $item = UrdBpmModel::findOrFail($id);
            $item->update($validated);
            return redirect()->back()->with('success', 'Registro actualizado exitosamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar el registro: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $item = UrdBpmModel::findOrFail($id);
            $item->delete();
            return redirect()->back()->with('success', 'Registro eliminado exitosamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar el registro: ' . $e->getMessage());
        }
    }
}
