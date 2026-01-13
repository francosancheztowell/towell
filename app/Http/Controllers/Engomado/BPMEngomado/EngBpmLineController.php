<?php

namespace App\Http\Controllers\Engomado\BPMEngomado;

use App\Http\Controllers\Controller;
use App\Models\EngBpmModel;
use App\Models\EngActividadesBpmModel;
use App\Models\EngBpmLineModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EngBpmLineController extends Controller
{
    public function index(string $folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();
        $actividades = EngActividadesBpmModel::orderBy('Orden')->get();

        // Verificar si ya existen registros para este folio
        $existingLines = EngBpmLineModel::where('Folio', $folio)->count();

        // Obtener MaquinaId y Departamento de la sesión o de las líneas existentes
        $primeraLinea = EngBpmLineModel::where('Folio', $folio)->first();
        $maquinaId = $primeraLinea->MaquinaId ?? session('bpm_eng_maquina_id');
        $departamento = $primeraLinea->Departamento ?? session('bpm_eng_departamento', 'Engomado');

        // Si no existen registros, crear todos con Valor=0
        if ($existingLines === 0) {
            foreach ($actividades as $actividad) {
                EngBpmLineModel::create([
                    'Folio' => $folio,
                    'TurnoRecibe' => $header->TurnoRecibe,
                    'MaquinaId' => $maquinaId,
                    'Departamento' => $departamento,
                    'Orden' => $actividad->Orden,
                    'Actividad' => $actividad->Actividad,
                    'Valor' => 0,
                ]);
            }
            // Limpiar sesión después de usar
            session()->forget(['bpm_eng_maquina_id', 'bpm_eng_departamento']);
        }

        // Obtener nombre de máquina desde URDCatalogoMaquina
        $nombreMaquina = 'Máquina';
        if ($maquinaId) {
            $maquina = \App\Models\URDCatalogoMaquina::where('MaquinaId', $maquinaId)->first();
            $nombreMaquina = $maquina->Nombre ?? $maquinaId;
        }

        // Obtener las líneas con sus valores actuales
        $lineas = EngBpmLineModel::where('Folio', $folio)
            ->pluck('Valor', 'Actividad');

        return view('modulos.engomado.Engomado-BPM-Line.index', compact('header', 'actividades', 'lineas', 'nombreMaquina'));
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
                'message' => 'No se pueden modificar actividades en estado ' . $header->Status
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

        $header->update(['Status' => 'Terminado']);

        return redirect()->back()->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }

        // Obtener información del usuario actual que autoriza
        $usuario = Auth::user();
        $usuarioDb = \App\Models\SYSUsuario::where('idusuario', $usuario->idusuario)->first();

        $header->update([
            'Status' => 'Autorizado',
            'CveEmplAutoriza' => $usuarioDb->numero_empleado ?? null,
            'NomEmplAutoriza' => $usuarioDb->nombre ?? null
        ]);

        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = EngBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede rechazar un registro Terminado');
        }

        $header->update(['Status' => 'Creado']);

        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
