<?php

namespace App\Http\Controllers\Urdido\BPMUrdido;

use App\Http\Controllers\Concerns\ChecklistBpm;
use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdActividadesBpmModel;
use App\Models\Urdido\UrdBpmLineModel;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UrdBpmLineController extends Controller
{
    use ChecklistBpm;

    public function index(string $folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        // Una sola lectura de las líneas del folio (antes: value(MaquinaId) + count() + first()).
        // Si no hay líneas es la primera visita: MaquinaId y Departamento vienen de la sesión (store).
        $primeraLinea = UrdBpmLineModel::where('Folio', $folio)->first();
        $maquinaId = $primeraLinea->MaquinaId ?? session('bpm_maquina_id');
        $departamento = $primeraLinea->Departamento ?? session('bpm_departamento', 'Urdido');

        $actividades = UrdActividadesBpmModel::where('Maquina', $maquinaId === 'KM1' ? 'KM' : 'MC')
            ->orderBy('Orden')
            ->get();

        // Primera visita: todas las actividades con Valor=0, en bloque (antes un INSERT por actividad).
        if ($primeraLinea === null) {
            $this->crearLineas(UrdBpmLineModel::class, $actividades, [
                'Folio' => $folio,
                'TurnoRecibe' => $header->TurnoRecibe,
                'MaquinaId' => $maquinaId,
                'Departamento' => $departamento,
            ]);
            // Limpiar sesión después de usar
            session()->forget(['bpm_maquina_id', 'bpm_departamento']);
        }

        // Obtener nombre de máquina desde URDCatalogoMaquina
        $nombreMaquina = 'Máquina';
        if ($maquinaId) {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->first();
            $nombreMaquina = $maquina->Nombre ?? $maquinaId;
        }

        // Obtener las líneas con sus valores actuales
        $lineas = UrdBpmLineModel::where('Folio', $folio)
            ->pluck('Valor', 'Actividad');

        // Determinar si el usuario actual es Supervisor (para habilitar acciones de autorización en la vista)
        $esSupervisor = $this->currentUserIsSupervisor();

        return view('modulos.urdido.Urdido-BPM-Line.index', compact('header', 'actividades', 'lineas', 'nombreMaquina', 'esSupervisor'));
    }

    public function toggleActividad(Request $request, string $folio)
    {
        $actividad = $request->input('actividad');
        $valor = $request->input('valor'); // 0, 1 o 2

        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        // Solo permitir cambios si está en estado "Creado"
        if ($header->Status !== 'Creado') {
            Log::warning('Intento de modificar actividad con status incorrecto', ['status' => $header->Status]);

            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar actividades en estado '.$header->Status,
            ], 403);
        }

        // Actualizar el valor (0 = vacío, 1 = palomita, 2 = tache)

        $affected = UrdBpmLineModel::where('Folio', $folio)
            ->where('Actividad', $actividad)
            ->update(['Valor' => $valor]);

        return response()->json(['success' => true, 'affected' => $affected]);
    }

    public function terminar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Creado') {
            return redirect()->back()->with('error', 'Solo se puede terminar un registro en estado Creado');
        }

        if ($this->currentUserIsSupervisor()) {
            [$code, $name] = $this->getSupervisorInfo('terminar y autorizar');

            $header->update([
                'Status' => 'Autorizado',
                'CveEmplAutoriza' => $code !== null ? (string) $code : '',
                'NombreEmplAutoriza' => $name !== null ? (string) $name : '',
            ]);

            return redirect()->back()->with('success', 'Registro terminado y autorizado exitosamente');
        }

        $header->update([
            'Status' => 'Terminado',
            'CveEmplAutoriza' => null,
            'NombreEmplAutoriza' => null,
        ]);

        return redirect()->back()->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }
        try {
            [$code, $name] = $this->getSupervisorInfo('autorizar');
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Autorizar y registrar quién autorizó
        $header->update([
            'Status' => 'Autorizado',
            'CveEmplAutoriza' => $code !== null ? (string) $code : '',
            'NombreEmplAutoriza' => $name !== null ? (string) $name : '',
        ]);

        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede rechazar un registro Terminado');
        }

        try {
            $this->getSupervisorInfo('rechazar');
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $header->update([
            'Status' => 'Creado',
            'CveEmplAutoriza' => null,
            'NombreEmplAutoriza' => null,
        ]);

        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
