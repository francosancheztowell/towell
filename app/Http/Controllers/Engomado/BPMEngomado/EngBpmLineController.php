<?php

namespace App\Http\Controllers\Engomado\BPMEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngBpmModel;
use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Engomado\EngBpmLineModel;
use App\Models\Sistema\SYSUsuario;
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
            $maquina = \App\Models\Urdido\URDCatalogoMaquina::where('MaquinaId', $maquinaId)->first();
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

    private function currentUserIsSupervisor(): bool
    {
        try {
            $this->getSupervisorInfo('validar permisos');

            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    private function getSupervisorInfo(string $accion): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $numeroEmpleado = $user->numero_empleado ?? $user->cve ?? null;
        $sysUsuario = null;

        if ($numeroEmpleado) {
            $sysUsuario = SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }

        if (!$sysUsuario && isset($user->idusuario)) {
            $sysUsuario = SYSUsuario::where('idusuario', $user->idusuario)->first();
        }

        if (!$sysUsuario) {
            throw new \RuntimeException("No se pudo identificar el usuario para validar permisos de {$accion}.");
        }

        $puesto = mb_strtolower(trim((string) ($sysUsuario->puesto ?? '')));
        $area = mb_strtolower(trim((string) ($sysUsuario->area ?? '')));

        $esSupervisor = str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor');

        if (!$esSupervisor) {
            throw new \RuntimeException("No tienes permisos para {$accion}. Solo los supervisores pueden realizar esta acción.");
        }

        $code = $sysUsuario->numero_empleado
            ?? $user->numero_empleado
            ?? $user->cve
            ?? $user->idusuario
            ?? $user->id
            ?? null;

        $name = $sysUsuario->nombre
            ?? $user->nombre
            ?? $user->name
            ?? $user->Nombre
            ?? null;

        return [$code, $name];
    }
}
