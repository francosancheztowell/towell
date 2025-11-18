<?php

namespace App\Http\Controllers;

use App\Models\UrdBpmModel;
use App\Models\UrdActividadesBpmModel;
use App\Models\UrdBpmLineModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UrdBpmLineController extends Controller
{
    public function index(string $folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        $actividades = UrdActividadesBpmModel::orderBy('Orden')->get();
        
        // Verificar si ya existen registros para este folio
        $existingLines = UrdBpmLineModel::where('Folio', $folio)->count();
        
        // Obtener MaquinaId y Departamento de la sesión o de las líneas existentes
        $primeraLinea = UrdBpmLineModel::where('Folio', $folio)->first();
        $maquinaId = $primeraLinea->MaquinaId ?? session('bpm_maquina_id');
        $departamento = $primeraLinea->Departamento ?? session('bpm_departamento', 'Urdido');
        
        // Si no existen registros, crear todos con Valor=0
        if ($existingLines === 0) {
            foreach ($actividades as $actividad) {
                UrdBpmLineModel::create([
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
            session()->forget(['bpm_maquina_id', 'bpm_departamento']);
        }
        
        // Obtener nombre de máquina desde URDCatalogoMaquina
        $nombreMaquina = 'Máquina';
        if ($maquinaId) {
            $maquina = \App\Models\URDCatalogoMaquina::where('MaquinaId', $maquinaId)->first();
            $nombreMaquina = $maquina->Nombre ?? $maquinaId;
        }
        
        // Obtener las líneas con sus valores actuales
        $lineas = UrdBpmLineModel::where('Folio', $folio)
            ->pluck('Valor', 'Actividad');

        return view('modulos.urdido.Urdido-BPM-Line.index', compact('header', 'actividades', 'lineas', 'nombreMaquina'));
    }

    public function toggleActividad(Request $request, string $folio)
    {
        $actividad = $request->input('actividad');
        $valor = $request->input('valor'); // 0, 1 o 2

        Log::info('toggleActividad llamado', [
            'folio' => $folio,
            'actividad' => $actividad,
            'valor' => $valor,
            'tipo_valor' => gettype($valor)
        ]);

        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        // Solo permitir cambios si está en estado "Creado"
        if ($header->Status !== 'Creado') {
            Log::warning('Intento de modificar actividad con status incorrecto', ['status' => $header->Status]);
            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar actividades en estado ' . $header->Status
            ], 403);
        }

        // Actualizar el valor (0 = vacío, 1 = palomita, 2 = tache)
        Log::info('Intentando actualizar', [
            'folio' => $folio,
            'actividad' => $actividad,
            'valorNuevo' => $valor
        ]);

        $affected = UrdBpmLineModel::where('Folio', $folio)
            ->where('Actividad', $actividad)
            ->update(['Valor' => $valor]);

        Log::info('Update ejecutado', ['rows_affected' => $affected]);

        return response()->json(['success' => true, 'affected' => $affected]);
    }

    public function terminar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Creado') {
            return redirect()->back()->with('error', 'Solo se puede terminar un registro en estado Creado');
        }

        $header->update(['Status' => 'Terminado']);
        
        return redirect()->back()->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }

        // Obtener información del usuario actual que autoriza
        $usuario = Auth::user();
        $usuarioDb = \App\Models\SYSUsuario::where('idusuario', $usuario->idusuario)->first();

        $header->update([
            'Status' => 'Autorizado',
            'CveEmplAutoriza' => $usuarioDb->numero_empleado ?? null,
            'NombreEmplAutoriza' => $usuarioDb->nombre ?? null
        ]);
        
        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede rechazar un registro Terminado');
        }

        $header->update(['Status' => 'Creado']);
        
        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
