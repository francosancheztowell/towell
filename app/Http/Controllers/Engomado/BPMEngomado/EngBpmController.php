<?php

namespace App\Http\Controllers\Engomado\BPMEngomado;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Support\Http\Concerns\HandlesApiErrors;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EngBpmController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        try {
            $items = EngBpmModel::orderBy('Id', 'desc')->get();
            $usuarios = SYSUsuario::where('area', 'Engomado')
                ->orderBy('nombre', 'asc')
                ->get();
            $maquinas = URDCatalogoMaquina::where('Departamento', 'Engomado')
                ->orderBy('Nombre', 'asc')
                ->get();
            $folioSugerido = FolioHelper::obtenerFolioSugerido('Engomado BPM', 3);
        } catch (\Exception $e) {
            $items = collect([]);
            $usuarios = collect([]);
            $maquinas = collect([]);
            $folioSugerido = '';
            Log::error('Error al cargar BPM Engomado: '.$e->getMessage());
        }

        $esSupervisorBpm = $this->currentUserIsSupervisor();

        return view('modulos.engomado.BPM-Engomado.index', compact('items', 'usuarios', 'maquinas', 'folioSugerido', 'esSupervisorBpm'));
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
            'NomEmplAutoriza' => 'nullable|string|max:255',
            'Status' => 'required|in:Creado,Terminado,Autorizado',
            'MaquinaId' => 'required|string|max:50',
            'Departamento' => 'nullable|string|max:100',
        ], [
            'NombreEmplRec.required' => 'Debe seleccionar quien recibe',
            'MaquinaId.required' => 'Debe seleccionar una máquina',
        ]);

        try {
            // Extraer MaquinaId y Departamento para usar en las líneas (no se guardan en EngBPM)
            $maquinaId = $validated['MaquinaId'];
            $departamento = $validated['Departamento'] ?? 'Engomado';
            unset($validated['MaquinaId'], $validated['Departamento']);

            // Combinar la fecha seleccionada con la hora actual (si viene sin hora)
            // Conserva el día elegido y usa la hora/minuto actual del servidor
            if (! empty($validated['Fecha'])) {
                $fechaInput = $validated['Fecha'];
                $fecha = Carbon::parse($fechaInput);
                // Si el input no contiene hora explícita (ej. formato 'Y-m-d'), asignar hora actual
                // Nota: aunque contenga hora, este setTimeFrom asegura que guardamos la hora actual deseada
                $validated['Fecha'] = $fecha->setTimeFrom(Carbon::now());
            }

            // Generar folio automáticamente con el módulo "Engomado BPM"
            $folio = FolioHelper::obtenerSiguienteFolio('Engomado BPM', 3);
            $validated['Folio'] = $folio;

            $header = EngBpmModel::create($validated);

            // Guardar MaquinaId y Departamento en sesión para usarlos en las líneas
            session(['bpm_eng_maquina_id' => $maquinaId, 'bpm_eng_departamento' => $departamento]);

            // Redirigir a la vista de líneas del folio creado
            return redirect()->route('eng-bpm-line.index', $folio)
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
            'NomEmplAutoriza' => 'nullable|string|max:255',
            'Status' => 'required|in:Creado,Terminado,Autorizado',
        ]);

        try {
            $item = EngBpmModel::findOrFail($id);
            $item->update($validated);

            return redirect()->back()->with('success', 'Registro actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->volverConError($e, 'actualizar');
        }
    }

    public function destroy($id)
    {
        try {
            $item = EngBpmModel::findOrFail($id);
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

        return str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor');
    }
}
