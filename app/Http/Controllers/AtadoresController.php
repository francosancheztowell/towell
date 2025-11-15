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
use App\Models\AtaComentariosModel;
use App\Models\TejHistorialInventarioTelaresModel;

class AtadoresController extends Controller
{
    //
    public function index(){
        $inventarioTelares = TejInventarioTelares::select(
            'tej_inventario_telares.id',
            'tej_inventario_telares.fecha',
            'tej_inventario_telares.turno',
            'tej_inventario_telares.no_telar',
            'tej_inventario_telares.tipo',
            'tej_inventario_telares.no_julio',
            'tej_inventario_telares.localidad',
            'tej_inventario_telares.metros',
            'tej_inventario_telares.no_orden',
            'tej_inventario_telares.tipo_atado',
            'tej_inventario_telares.cuenta',
            'tej_inventario_telares.calibre',
            'tej_inventario_telares.hilo',
            // Map column names from MySQL (camelCase) to expected aliases
            DB::raw('tej_inventario_telares.loteProveedor as LoteProveedor'),
            DB::raw('tej_inventario_telares.noProveedor as NoProveedor'),
            'tej_inventario_telares.horaParo',
            // Dynamic status based on AtaMontadoTelas
            DB::raw("CASE 
                WHEN AtaMontadoTelas.Estatus = 'Autorizado' THEN 'Autorizado'
                WHEN AtaMontadoTelas.Calidad IS NOT NULL AND AtaMontadoTelas.Limpieza IS NOT NULL THEN 'Calificado'
                WHEN AtaMontadoTelas.HoraArranque IS NOT NULL THEN 'Terminado'
                WHEN AtaMontadoTelas.NoJulio IS NOT NULL THEN 'En Proceso'
                ELSE 'Activo'
            END as status_proceso")
        )
        ->leftJoin('AtaMontadoTelas', function($join) {
            $join->on('tej_inventario_telares.no_julio', '=', 'AtaMontadoTelas.NoJulio')
                 ->on('tej_inventario_telares.no_orden', '=', 'AtaMontadoTelas.NoProduccion');
        })
        ->where('tej_inventario_telares.status', 'activo') // Solo registros activos
        ->whereNotNull('tej_inventario_telares.no_julio')
        ->where('tej_inventario_telares.no_julio', '!=', '') // No_julio debe estar lleno
        ->orderBy('tej_inventario_telares.fecha', 'desc')
        ->orderBy('tej_inventario_telares.turno', 'desc')
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

        // Verificar si ya existe un atado en proceso para este mismo NoJulio
        $existente = AtaMontadoTelasModel::where('NoJulio', $item->no_julio)
            ->where('NoProduccion', $item->no_orden)
            ->where('Estatus', 'En Proceso')
            ->first();

        if ($existente) {
            // Si ya existe, simplemente redirigir a calificar sin eliminar datos
            return redirect()->route('atadores.calificar')->with('info', 'Continuando con atado en proceso');
        }

        // ELIMINAR solo los registros EN PROCESO que NO sean del mismo NoJulio/NoProduccion
        // para permitir nuevo proceso sin perder datos del actual
        AtaMontadoTelasModel::where('Estatus', 'En Proceso')
            ->where(function($query) use ($item) {
                $query->where('NoJulio', '!=', $item->no_julio)
                      ->orWhere('NoProduccion', '!=', $item->no_orden);
            })
            ->delete();
            
        AtaMontadoMaquinasModel::whereNotIn('NoJulio', function($query) use ($item) {
            $query->select('NoJulio')->from('AtaMontadoTelas')
                  ->where('Estatus', 'En Proceso')
                  ->where('NoJulio', $item->no_julio)
                  ->where('NoProduccion', $item->no_orden);
        })->delete();
        
        AtaMontadoActividadesModel::whereNotIn('NoJulio', function($query) use ($item) {
            $query->select('NoJulio')->from('AtaMontadoTelas')
                  ->where('Estatus', 'En Proceso')
                  ->where('NoJulio', $item->no_julio)
                  ->where('NoProduccion', $item->no_orden);
        })->delete();

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

        // Crear registros base para máquinas del catálogo
        $maquinas = AtaMaquinasModel::all();
        foreach ($maquinas as $maquina) {
            AtaMontadoMaquinasModel::create([
                'NoJulio' => $item->no_julio,
                'NoProduccion' => $item->no_orden,
                'MaquinaId' => $maquina->MaquinaId,
                'Estado' => 0 // Por defecto inactivo
            ]);
        }

        // Crear registros base para actividades del catálogo
        $actividades = AtaActividadesModel::all();
        foreach ($actividades as $actividad) {
            AtaMontadoActividadesModel::create([
                'NoJulio' => $item->no_julio,
                'NoProduccion' => $item->no_orden,
                'ActividadId' => $actividad->ActividadId,
                'Porcentaje' => $actividad->Porcentaje,
                'Estado' => 0, // Por defecto inactivo
                'CveEmpl' => null,
                'NomEmpl' => null,
                'Turno' => $item->turno
            ]);
        }

        // Redirigir a la página de calificar atadores
        return redirect()->route('atadores.calificar')->with('success', 'Atado iniciado correctamente');
    }

    public function calificarAtadores()
    {
        // Obtener solo los registros EN PROCESO (no los autorizados)
        $montadoTelas = AtaMontadoTelasModel::where('Estatus', 'En Proceso')
            ->orderBy('Fecha', 'desc')
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

        // Catálogo de notas/comentarios (para mostrar al final)
        $comentarios = AtaComentariosModel::orderBy('Nota1')->get();

        return view(
            'modulos.atadores.calificar-atadores.index',
            compact(
                'montadoTelas',
                'maquinasCatalogo',
                'maquinasMontado',
                'actividadesCatalogo',
                'actividadesMontado',
                'comentarios'
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

        // Obtener el atado actual (último EN PROCESO)
        $montado = AtaMontadoTelasModel::where('Estatus', 'En Proceso')
            ->orderBy('Fecha', 'desc')
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
            try {
                DB::beginTransaction();

                // 1. Actualizar supervisor en AtaMontadoTelas
                DB::connection('sqlsrv')
                    ->table('AtaMontadoTelas')
                    ->where('NoJulio', $montado->NoJulio)
                    ->where('NoProduccion', $montado->NoProduccion)
                    ->update([
                        'CveSupervisor' => $user->numero_empleado,
                        'NomSupervisor' => $user->nombre,
                        'FechaSupervisor' => Carbon::now(),
                        'Estatus' => 'Autorizado',
                        'CveTejedor' => $montado->CveTejedor ?: $user->numero_empleado,
                        'NomTejedor' => $montado->NomTejedor ?: $user->nombre,
                    ]);

                // 2. Guardar en TejHistorialInventarioTelares
                TejHistorialInventarioTelaresModel::create([
                    'NoTelarId' => $montado->NoTelarId,
                    'Status' => 'Completado',
                    'Tipo' => $montado->Tipo,
                    'FechaRequerimiento' => $montado->Fecha,
                    'Turno' => $montado->Turno,
                    'Metros' => $montado->Metros,
                    'NoJulio' => $montado->NoJulio,
                    'NoProduccion' => $montado->NoProduccion,
                    'LoteProveedor' => $montado->LoteProveedor,
                    'NoProveedor' => $montado->NoProveedor,
                    'HoraParo' => $montado->HoraParo
                ]);

                // 3. Guardar datos de máquinas y actividades del proceso actual
                // Obtener datos actuales de máquinas
                $maquinasActuales = AtaMontadoMaquinasModel::where('NoJulio', $montado->NoJulio)
                    ->where('NoProduccion', $montado->NoProduccion)
                    ->get();

                // Obtener datos actuales de actividades  
                $actividadesActuales = AtaMontadoActividadesModel::where('NoJulio', $montado->NoJulio)
                    ->where('NoProduccion', $montado->NoProduccion)
                    ->get();

                // También asegurar que se guarden las máquinas y actividades con estado activo
                // (En caso de que no se hayan marcado manualmente en la interfaz)
                $maquinasCatalogo = AtaMaquinasModel::all();
                foreach ($maquinasCatalogo as $maq) {
                    $existe = $maquinasActuales->where('MaquinaId', $maq->MaquinaId)->first();
                    if (!$existe) {
                        // Crear registro por defecto
                        AtaMontadoMaquinasModel::create([
                            'NoJulio' => $montado->NoJulio,
                            'NoProduccion' => $montado->NoProduccion,
                            'MaquinaId' => $maq->MaquinaId,
                            'Estado' => 0 // Por defecto inactivo
                        ]);
                    }
                }

                $actividadesCatalogo = AtaActividadesModel::all();
                foreach ($actividadesCatalogo as $act) {
                    $existe = $actividadesActuales->where('ActividadId', $act->ActividadId)->first();
                    if (!$existe) {
                        // Crear registro por defecto
                        AtaMontadoActividadesModel::create([
                            'NoJulio' => $montado->NoJulio,
                            'NoProduccion' => $montado->NoProduccion,
                            'ActividadId' => $act->ActividadId,
                            'Porcentaje' => $act->Porcentaje,
                            'Estado' => 0, // Por defecto inactivo
                            'CveEmpl' => null,
                            'NomEmpl' => null,
                            'Turno' => $montado->Turno
                        ]);
                    }
                }

                // 4. Buscar y eliminar SOLO el registro original de tej_inventario_telares
                $registroOriginal = TejInventarioTelares::where('no_julio', $montado->NoJulio)
                    ->where('no_orden', $montado->NoProduccion)
                    ->first();
                
                if ($registroOriginal) {
                    $registroOriginal->delete();
                }

                // 5. NO eliminar las tablas de montado - mantener los registros autorizados
                // Los registros en AtaMontadoTelas, AtaMontadoMaquinas y AtaMontadoActividades 
                // se conservan como registro histórico del proceso autorizado

                DB::commit();
                return response()->json([
                    'ok' => true, 
                    'message' => 'Proceso autorizado completamente',
                    'redirect' => route('atadores.programa')
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        }

        if ($action === 'calificacion') {
            $data = $request->validate([
                'calidad' => ['required','integer','min:1','max:10'],
                'limpieza' => ['required','integer','min:5','max:10'],
            ]);
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update([
                    'Calidad' => (int) $data['calidad'],
                    'Limpieza' => (int) $data['limpieza'],
                    'CveTejedor' => $montado->CveTejedor ?: $user->numero_empleado,
                    'NomTejedor' => $montado->NomTejedor ?: $user->nombre,
                    'CveSupervisor' => $user->numero_empleado,
                    'NomSupervisor' => $user->nombre,
                ]);

            return response()->json([
                'ok' => true, 
                'message' => 'Calificación guardada',
                'supervisor' => [
                    'cve' => $user->numero_empleado,
                    'nombre' => $user->nombre
                ]
            ]);
        }

        if ($action === 'observaciones') {
            $observaciones = $request->input('observaciones');
            
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update(['Obs' => $observaciones]);

            return response()->json(['ok' => true, 'message' => 'Observaciones guardadas']);
        }

        if ($action === 'merga') {
            $mergaKg = $request->input('mergaKg');
            
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update(['MergaKg' => $mergaKg]);

            return response()->json(['ok' => true, 'message' => 'Merga guardada']);
        }

        if ($action === 'maquina_estado') {
            $data = $request->validate([
                'maquinaId' => ['required','string','max:50'],
                'estado' => ['required','boolean']
            ]);

            // Evitar errores por PK inexistente usando query builder
            DB::connection('sqlsrv')
                ->table('AtaMontadoMaquinas')
                ->updateOrInsert(
                    [
                        'NoJulio' => $montado->NoJulio,
                        'NoProduccion' => $montado->NoProduccion,
                        'MaquinaId' => $data['maquinaId'],
                    ],
                    [
                        'Estado' => $data['estado'] ? 1 : 0,
                    ]
                );

            return response()->json(['ok' => true, 'message' => 'Estado de máquina actualizado']);
        }

        if ($action === 'terminar') {
            // Validar que TODAS las actividades estén marcadas (Estado = 1)
            $totalActividades = DB::connection('sqlsrv')
                ->table('AtaMontadoActividades')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->count();

            $actividadesCompletadas = DB::connection('sqlsrv')
                ->table('AtaMontadoActividades')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->where('Estado', 1)
                ->count();

            if ($actividadesCompletadas < $totalActividades) {
                return response()->json([
                    'ok' => false, 
                    'message' => 'Debe marcar todas las actividades antes de terminar el atado. (' . $actividadesCompletadas . '/' . $totalActividades . ' completadas)'
                ], 422);
            }

            // Register current time as "hora de arranque"
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update([
                    'HoraArranque' => Carbon::now()->format('H:i')
                ]);

            return response()->json(['ok' => true, 'message' => 'Atado terminado y hora de arranque registrada']);
        }

        if ($action === 'actividad_estado') {
            $actividadId = $request->input('actividadId');
            $estado = $request->input('estado') ? 1 : 0;

            $updateData = ['Estado' => $estado];
            
            // Si se activa (marca el checkbox), SIEMPRE asignar el usuario actual como operador
            if ($estado) {
                $updateData['CveEmpl'] = $user->numero_empleado;
                $updateData['NomEmpl'] = $user->nombre;
            } else {
                // Si se desmarca, limpiar el operador
                $updateData['CveEmpl'] = null;
                $updateData['NomEmpl'] = null;
            }

            DB::connection('sqlsrv')
                ->table('AtaMontadoActividades')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->where('ActividadId', $actividadId)
                ->update($updateData);

            // Devolver el operador actualizado para reflejar en la UI
            $operador = $estado ? trim(($user->numero_empleado ?? '') . ' - ' . ($user->nombre ?? '')) : '-';
            
            return response()->json([
                'ok' => true, 
                'message' => 'Estado de actividad actualizado',
                'operador' => $operador,
                'cveEmpl' => $estado ? $user->numero_empleado : null,
                'nomEmpl' => $estado ? $user->nombre : null
            ]);
        }

        return response()->json(['ok' => false, 'message' => 'Acción no válida'], 422);
    }
}
