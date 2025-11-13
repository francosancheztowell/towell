<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\TejInventarioTelares;
use App\Models\AtaMontadoTelasModel;
use App\Models\AtaMontadoMaquinasModel;
use App\Models\AtaMontadoActividadesModel;
use App\Models\AtaMaquinasModel;
use App\Models\AtaActividadesModel;

class AtadoresController extends Controller
{
    //
    public function index(){
        $inventarioTelares = TejInventarioTelares::select(
            'id',
            'fecha',
            'turno',
            'no_telar',
            'tipo',
            'no_julio',
            'localidad',
            'metros',
            'no_orden',
            'tipo_atado',
            'cuenta',
            'calibre',
            'hilo',
            // Map column names from MySQL (camelCase) to expected aliases
            DB::raw('loteProveedor as LoteProveedor'),
            DB::raw('noProveedor as NoProveedor'),
            'horaParo'
        )
        ->whereNotNull('no_julio')
        ->where('no_julio', '!=', '')
        ->orderBy('fecha', 'desc')
        ->orderBy('turno', 'desc')
        ->get();

        return view("modulos.atadores.programaAtadores.index", compact('inventarioTelares'));
    }

    public function iniciarAtado(Request $request)
    {
        // Validar que se recibió un ID
        if (!$request->has('id')) {
            return redirect()->route('atadores.programa')->with('error', 'Debe seleccionar un registro');
        }

        $id = $request->input('id');

        // Obtener el registro específico del inventario de telares
        $item = TejInventarioTelares::find($id);

        if (!$item) {
            return redirect()->route('atadores.programa')->with('error', 'Registro no encontrado');
        }

        // ELIMINAR todos los registros anteriores en AtaMontadoTelas antes de insertar el nuevo
        AtaMontadoTelasModel::query()->delete();

        // Usuario actual como operador por defecto
        $user = Auth::user();

        // Insertar solo el registro seleccionado en AtaMontadoTelas
        AtaMontadoTelasModel::create([
            'Estatus' => 'En Proceso',
            'Fecha' => $item->fecha,
            'Turno' => $item->turno,
            'NoJulio' => $item->no_julio,
            'NoProduccion' => $item->no_orden,
            'Tipo' => $item->tipo,
            'Metros' => $item->metros,
            'NoTelarId' => $item->no_telar,
            'LoteProveedor' => $item->LoteProveedor,
            'NoProveedor' => $item->NoProveedor,
            'HoraParo' => $item->horaParo,
            // Operador = usuario en sesión al iniciar
            'CveTejedor' => $user?->numero_empleado,
            'NomTejedor' => $user?->nombre,
            'Calidad' => null,
            'Limpieza' => null,
        ]);

        // Redirigir a la página de calificar atadores
        return redirect()->route('atadores.calificar')->with('success', 'Atado iniciado correctamente');
    }

    public function calificarAtadores()
    {
        // Obtener todos los registros de AtaMontadoTelas
        $montadoTelas = AtaMontadoTelasModel::orderBy('Fecha', 'desc')
            ->orderBy('Turno', 'desc')
            ->get();

        // Catálogos base
        $maquinasCatalogo = AtaMaquinasModel::orderBy('MaquinaId')->get();
        $actividadesCatalogo = AtaActividadesModel::orderBy('ActividadId')->get();

        // Estados para el atado actual (si existe)
        $maquinasMontado = collect();
        $actividadesMontado = collect();
        if ($montadoTelas->isNotEmpty()) {
            $actual = $montadoTelas->first();
            $maquinasMontado = AtaMontadoMaquinasModel::where('NoJulio', $actual->NoJulio)
                ->where('NoProduccion', $actual->NoProduccion)
                ->get()
                ->keyBy('MaquinaId');

            $actividadesMontado = AtaMontadoActividadesModel::where('NoJulio', $actual->NoJulio)
                ->where('NoProduccion', $actual->NoProduccion)
                ->get()
                ->keyBy('ActividadId');
        }

        return view(
            'modulos.atadores.calificar-atadores.index',
            compact(
                'montadoTelas',
                'maquinasCatalogo',
                'maquinasMontado',
                'actividadesCatalogo',
                'actividadesMontado'
            )
        );
    }

    public function save(Request $request)
    {
        $action = $request->input('action');

        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        // Obtener el atado actual (último en proceso)
        $montado = AtaMontadoTelasModel::orderBy('Fecha', 'desc')
            ->orderBy('Turno', 'desc')
            ->first();

        if (!$montado) {
            return response()->json(['ok' => false, 'message' => 'No hay atado en proceso'], 404);
        }

        if ($action === 'operador') {
            $montado->CveTejedor = $user->numero_empleado;
            $montado->NomTejedor = $user->nombre;
            $montado->save();
            return response()->json(['ok' => true, 'message' => 'Operador asignado']);
        }

        if ($action === 'supervisor') {
            $montado->CveSupervisor = $user->numero_empleado;
            $montado->NomSupervisor = $user->nombre;
            $montado->FechaSupervisor = Carbon::now();
            $montado->save();
            return response()->json(['ok' => true, 'message' => 'Supervisor asignado']);
        }

        return response()->json(['ok' => false, 'message' => 'Acción no válida'], 422);
    }
}
