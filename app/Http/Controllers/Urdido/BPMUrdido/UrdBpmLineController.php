<?php

namespace App\Http\Controllers\Urdido\BPMUrdido;

use App\Http\Controllers\Controller;
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

        // Determinar si el usuario actual es Supervisor (para habilitar acciones de autorización en la vista)
        $esSupervisor = false;
        try {
            $u = Auth::user();
            if ($u) {
                $num = $u->numero_empleado ?? $u->cve ?? null;
                if ($num) {
                    $sysU = \App\Models\SYSUsuario::where('numero_empleado', $num)->first();
                    $puesto = strtolower(trim((string)($sysU->puesto ?? '')));
                    $esSupervisor = ($puesto === 'supervisor');
                }
            }
        } catch (\Throwable $e) {
            $esSupervisor = false;
        }

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
                'message' => 'No se pueden modificar actividades en estado ' . $header->Status
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

        $header->update(['Status' => 'Terminado']);

        return redirect()->back()->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }
        // Validar que el usuario actual tenga puesto de Supervisor
        $u = Auth::user();
        if (!$u) {
            return redirect()->back()->with('error', 'Usuario no autenticado.');
        }

        // Intentar identificar por número de empleado o clave
        $numeroEmpleado = $u->numero_empleado ?? $u->cve ?? null;
        $sysUsuario = null;
        if ($numeroEmpleado) {
            $sysUsuario = \App\Models\SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }
        // Fallback por idusuario si no se encontró por número de empleado
        if (!$sysUsuario && isset($u->idusuario)) {
            $sysUsuario = \App\Models\SYSUsuario::where('idusuario', $u->idusuario)->first();
        }

        if (!$sysUsuario || strtolower(trim((string)($sysUsuario->puesto ?? ''))) !== 'supervisor') {
            return redirect()->back()->with('error', 'No tienes permisos para autorizar. Solo los supervisores pueden realizar esta acción.');
        }

        // Autorizar y registrar quién autorizó
        $header->update([
            'Status' => 'Autorizado',
            'CveEmplAutoriza' => (string)($sysUsuario->numero_empleado ?? ''),
            'NombreEmplAutoriza' => (string)($sysUsuario->nombre ?? ''),
        ]);

        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();

        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede rechazar un registro Terminado');
        }

        // Validar que el usuario actual tenga puesto de Supervisor
        $u = Auth::user();
        if (!$u) {
            return redirect()->back()->with('error', 'Usuario no autenticado.');
        }

        $numeroEmpleado = $u->numero_empleado ?? $u->cve ?? null;
        $sysUsuario = null;
        if ($numeroEmpleado) {
            $sysUsuario = \App\Models\SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }
        if (!$sysUsuario && isset($u->idusuario)) {
            $sysUsuario = \App\Models\SYSUsuario::where('idusuario', $u->idusuario)->first();
        }

        if (!$sysUsuario || strtolower(trim((string)($sysUsuario->puesto ?? ''))) !== 'supervisor') {
            return redirect()->back()->with('error', 'No tienes permisos para rechazar. Solo los supervisores pueden realizar esta acción.');
        }

        $header->update(['Status' => 'Creado']);

        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
