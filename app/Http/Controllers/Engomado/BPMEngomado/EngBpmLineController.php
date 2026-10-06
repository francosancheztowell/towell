<?php

namespace App\Http\Controllers\Engomado\BPMEngomado;

use App\Http\Controllers\Concerns\ChecklistBpm;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Engomado\EngBpmLineModel;
use App\Models\Engomado\EngBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Http\Request;

class EngBpmLineController extends Controller
{
    use ChecklistBpm;

    public function index(string $folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();
        $actividades = EngActividadesBpmModel::orderBy('Orden')->get();

        // Una sola lectura de las líneas del folio (antes: count() + first()).
        // Si no hay líneas es la primera visita: MaquinaId y Departamento vienen de la sesión (store).
        $primeraLinea = EngBpmLineModel::where('Folio', $folio)->first();
        $maquinaId = $primeraLinea->MaquinaId ?? session('bpm_eng_maquina_id');
        $departamento = $primeraLinea->Departamento ?? session('bpm_eng_departamento', 'Engomado');

        // Primera visita: todas las actividades con Valor=0, en bloque (antes un INSERT por actividad).
        if ($primeraLinea === null) {
            $this->crearLineas(EngBpmLineModel::class, $actividades, [
                'Folio' => $folio,
                'TurnoRecibe' => $header->TurnoRecibe,
                'MaquinaId' => $maquinaId,
                'Departamento' => $departamento,
            ]);
            // Limpiar sesión después de usar
            session()->forget(['bpm_eng_maquina_id', 'bpm_eng_departamento']);
        }

        // Obtener nombre de máquina desde URDCatalogoMaquina
        $nombreMaquina = 'Máquina';
        if ($maquinaId) {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->first();
            $nombreMaquina = $maquina->Nombre ?? $maquinaId;
        }

        // Obtener las líneas con sus valores actuales
        $lineas = EngBpmLineModel::where('Folio', $folio)
            ->pluck('Valor', 'Actividad');

        $esSupervisor = $this->currentUserIsSupervisor();

        return view('modulos.engomado.Engomado-BPM-Line.index', compact('header', 'actividades', 'lineas', 'nombreMaquina', 'esSupervisor'));
    }

    public function toggleActividad(Request $request, string $folio)
    {
        $actividad = $request->input('actividad');
        $valor = $request->input('valor'); // 0, 1 o 2

        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

        // Solo permitir cambios si está en estado "Creado"
        if ($header->Status !== 'Creado') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar actividades en estado '.$header->Status,
            ], 403);
        }

        // Actualizar el valor (0 = vacío, 1 = palomita, 2 = tache)
        $affected = EngBpmLineModel::where('Folio', $folio)
            ->where('Actividad', $actividad)
            ->update(['Valor' => $valor]);

        return response()->json(['success' => true, 'affected' => $affected]);
    }

    public function terminar($folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Creado') {
            return redirect()->back()->with('error', 'Solo se puede terminar un registro en estado Creado');
        }

        if ($this->currentUserIsSupervisor()) {
            [$code, $name] = $this->getSupervisorInfo('terminar y autorizar');

            $header->update([
                'Status' => 'Autorizado',
                'CveEmplAutoriza' => $code !== null ? (string) $code : '',
                'NomEmplAutoriza' => $name !== null ? (string) $name : '',
            ]);

            return redirect()->route('eng-bpm.index')->with('success', 'Registro terminado y autorizado exitosamente');
        }

        $header->update([
            'Status' => 'Terminado',
            'CveEmplAutoriza' => null,
            'NomEmplAutoriza' => null,
        ]);

        return redirect()->route('eng-bpm.index')->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }

        try {
            [$code, $name] = $this->getSupervisorInfo('autorizar');
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $header->update([
            'Status' => 'Autorizado',
            'CveEmplAutoriza' => $code !== null ? (string) $code : '',
            'NomEmplAutoriza' => $name !== null ? (string) $name : '',
        ]);

        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

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
            'NomEmplAutoriza' => null,
        ]);

        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
