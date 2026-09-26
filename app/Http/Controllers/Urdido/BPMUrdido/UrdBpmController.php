<?php

namespace App\Http\Controllers\Urdido\BPMUrdido;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UrdBpmController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        try {
            $items = UrdBpmModel::orderBy('Id', 'desc')->get();
            $usuarios = SYSUsuario::where('area', 'Urdido')->whereNotNull('numero_empleado')->orderBy('nombre', 'asc')->get();
            $maquinas = URDCatalogoMaquina::where(function ($q) {
                $q->where('Departamento', 'Urdido')
                    ->orWhereIn('MaquinaId', ['401', '402']);
            })
                ->where('Nombre', 'not like', '%Karl Mayer%')
                ->orderBy('Nombre', 'asc')
                ->get();
            $folioSugerido = FolioHelper::obtenerFolioSugerido('Urdido BPM', 3);
        } catch (\Exception $e) {
            $items = collect([]);
            $usuarios = collect([]);
            $maquinas = collect([]);
            $folioSugerido = '';
            Log::error('Error al cargar BPM Urdido: '.$e->getMessage());
        }

        $esSupervisorBpm = $this->currentUserIsSupervisor();

        return view('modulos.urdido.BPM-Urdido.index', compact('items', 'usuarios', 'maquinas', 'folioSugerido', 'esSupervisorBpm'));
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
                ->with('success', 'Registro creado exitosamente con folio: '.$folio);
        } catch (\Exception $e) {
            return $this->volverConError($e, 'crear');
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
            return $this->volverConError($e, 'actualizar');
        }
    }

    public function destroy($id)
    {
        try {
            $item = UrdBpmModel::findOrFail($id);
            $item->delete();

            return redirect()->back()->with('success', 'Registro eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->volverConError($e, 'eliminar');
        }
    }

    /** SEC-07: el usuario ve un mensaje genérico con la referencia del error, nunca el texto de la excepción. */
    private function volverConError(\Throwable $e, string $accion): RedirectResponse
    {
        report($e);

        return redirect()->back()->with('error', "Error al {$accion} el registro (ref: {$this->traceIdDeError($e)})");
    }

    private function currentUserIsSupervisor(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $numeroEmpleado = $user->numero_empleado ?? $user->cve ?? null;
        $sysUsuario = null;

        if ($numeroEmpleado) {
            $sysUsuario = SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }

        if (! $sysUsuario && isset($user->idusuario)) {
            $sysUsuario = SYSUsuario::where('idusuario', $user->idusuario)->first();
        }

        if (! $sysUsuario) {
            return false;
        }

        $puesto = mb_strtolower(trim((string) ($sysUsuario->puesto ?? '')));
        $area = mb_strtolower(trim((string) ($sysUsuario->area ?? '')));

        if (str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor')) {
            return true;
        }

        return false;
    }
}
