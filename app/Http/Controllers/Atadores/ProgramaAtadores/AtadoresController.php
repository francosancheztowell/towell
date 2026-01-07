<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
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
            // Dynamic status based on AtaMontadoTelas.Estatus
            DB::raw("CASE
                WHEN AtaMontadoTelas.Estatus = 'Autorizado' THEN 'Autorizado'
                WHEN AtaMontadoTelas.Estatus = 'Calificado' THEN 'Calificado'
                WHEN AtaMontadoTelas.Estatus = 'Terminado' THEN 'Terminado'
                WHEN AtaMontadoTelas.Estatus = 'En Proceso' THEN 'En Proceso'
                ELSE 'Activo'
            END as status_proceso")
        )
        ->leftJoin('AtaMontadoTelas', function($join) {
            $join->on('tej_inventario_telares.no_julio', '=', 'AtaMontadoTelas.NoJulio')
                 ->on('tej_inventario_telares.no_orden', '=', 'AtaMontadoTelas.NoProduccion');
        })
        ->whereIn('tej_inventario_telares.status', ['activo', 'En Proceso']) // Mostrar activos y en proceso
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
        $noJulioRequest = $request->input('no_julio');
        $noOrdenRequest = $request->input('no_orden');

        // Obtener el registro específico del inventario de telares
        $item = TejInventarioTelares::find($id);

        if (!$item) {
            return redirect()->route('atadores.programa')->with('error', 'Registro no encontrado');
        }

        // Validar que los datos del registro coincidan con los enviados desde el frontend
        // Esto asegura que se está seleccionando el registro correcto
        if ($noJulioRequest && $item->no_julio != $noJulioRequest) {
            return redirect()->route('atadores.programa')->with('error', 'Los datos del No. Julio no coinciden. Por favor, seleccione el registro nuevamente.');
        }

        if ($noOrdenRequest && $item->no_orden != $noOrdenRequest) {
            return redirect()->route('atadores.programa')->with('error', 'Los datos del No. Orden no coinciden. Por favor, seleccione el registro nuevamente.');
        }

        // Validar que el registro tenga los datos necesarios
        if (empty($item->no_julio) || empty($item->no_orden)) {
            return redirect()->route('atadores.programa')->with('error', 'El registro seleccionado no tiene los datos necesarios (No. Julio o No. Orden)');
        }

        // Verificar si ya existe un atado para este mismo NoJulio y NoOrden (en cualquier estado activo)
        $existente = AtaMontadoTelasModel::where('NoJulio', $item->no_julio)
            ->where('NoProduccion', $item->no_orden)
            ->whereIn('Estatus', ['En Proceso', 'Terminado', 'Calificado'])
            ->first();

        if ($existente) {
            // Si ya existe, redirigir a calificar con los parámetros del registro correcto
            return redirect()->route('atadores.calificar', [
                'no_julio' => $item->no_julio,
                'no_orden' => $item->no_orden
            ])->with('info', 'Continuando con atado en proceso');
        }

        // NO eliminar otros procesos en estado 'En Proceso'
        // Permitir múltiples procesos simultáneos, cada uno con su propia información

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

        // Actualizar el estado en tej_inventario_telares a "En Proceso"
        $item->status = 'En Proceso';
        $item->save();

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

        // Redirigir a la página de calificar atadores con los parámetros del registro seleccionado
        return redirect()->route('atadores.calificar', [
            'no_julio' => $item->no_julio,
            'no_orden' => $item->no_orden
        ])->with('success', 'Atado iniciado correctamente');
    }

    public function calificarAtadores(Request $request)
    {
        // Obtener parámetros opcionales para filtrar el registro correcto
        $noJulio = $request->query('no_julio');
        $noOrden = $request->query('no_orden');

        // Si se proporcionan parámetros, filtrar por ellos para obtener el registro específico
        if ($noJulio && $noOrden) {
            // Buscar el registro específico en cualquier estado activo
            $montadoTelas = AtaMontadoTelasModel::whereIn('Estatus', ['En Proceso', 'Terminado', 'Calificado'])
                ->where('NoJulio', $noJulio)
                ->where('NoProduccion', $noOrden)
                ->orderBy('Fecha', 'desc')
                ->orderBy('Turno', 'desc')
                ->get();
        } else {
            // Si no se proporcionan parámetros, obtener todos los procesos activos
            $montadoTelas = AtaMontadoTelasModel::whereIn('Estatus', ['En Proceso', 'Terminado', 'Calificado'])
                ->orderBy('Fecha', 'desc')
                ->orderBy('Turno', 'desc')
                ->get();
        }

        // Catálogos base
        $maquinasCatalogo = AtaMaquinasModel::orderBy('MaquinaId')->get();
        $actividadesCatalogo = AtaActividadesModel::orderBy('ActividadId')->get();

        // Estados para el atado actual (si existe)
        $maquinasMontado = collect();
        $actividadesMontado = collect();
        if ($montadoTelas->isNotEmpty()) {
            $actual = $montadoTelas->first();

            // Si tenemos parámetros, validar que el registro coincida
            if ($noJulio && $noOrden) {
                if ($actual->NoJulio != $noJulio || $actual->NoProduccion != $noOrden) {
                    // Si no coincide, mostrar mensaje de error
                    return redirect()->route('atadores.programa')
                        ->with('error', 'No se encontró el proceso especificado (No. Julio: ' . $noJulio . ', No. Orden: ' . $noOrden . ')');
                }
            }

            // Cargar máquinas y actividades del proceso actual
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

        // Obtener parámetros para identificar el registro correcto
        $noJulio = $request->input('no_julio');
        $noOrden = $request->input('no_orden');

        // Validar que se proporcionen los parámetros necesarios
        if (!$noJulio || !$noOrden) {
            return response()->json(['ok' => false, 'message' => 'Faltan parámetros necesarios (no_julio o no_orden)'], 422);
        }

        // Construir la consulta para obtener el registro correcto filtrando por NoJulio y NoProduccion
        // Buscar en estados activos (no Autorizado)
        $montado = AtaMontadoTelasModel::whereIn('Estatus', ['En Proceso', 'Terminado', 'Calificado'])
            ->where('NoJulio', $noJulio)
            ->where('NoProduccion', $noOrden)
            ->orderBy('Fecha', 'desc')
            ->orderBy('Turno', 'desc')
            ->first();

        if (!$montado) {
            return response()->json(['ok' => false, 'message' => 'No hay atado activo para el registro especificado (No. Julio: ' . $noJulio . ', No. Orden: ' . $noOrden . ')'], 404);
        }

        // Validar que los parámetros coincidan con el registro encontrado
        if ($montado->NoJulio != $noJulio || $montado->NoProduccion != $noOrden) {
            return response()->json(['ok' => false, 'message' => 'Los datos del registro no coinciden'], 422);
        }

        if ($action === 'operador') {
            $montado->CveTejedor = $user->numero_empleado;
            $montado->NomTejedor = $user->nombre;
            $montado->save();
            return response()->json(['ok' => true, 'message' => 'Operador asignado']);
        }

        if ($action === 'supervisor') {
            // Validar que el atado esté en estado Calificado
            if ($montado->Estatus !== 'Calificado') {
                return response()->json(['ok' => false, 'message' => 'Debe calificar el atado antes de autorizarlo como supervisor'], 422);
            }

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
                    'redirect' => route('atadores.programa'),
                    'supervisor' => [
                        'cve' => $user->numero_empleado,
                        'nombre' => $user->nombre
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        }

        if ($action === 'calificacion') {
            // Validar que esté en estado 'Terminado' para poder calificar
            if ($montado->Estatus !== 'Terminado') {
                return response()->json(['ok' => false, 'message' => 'Debe terminar el atado antes de calificarlo'], 422);
            }

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
                    'CveTejedor' => $user->numero_empleado,
                    'NomTejedor' => $user->nombre,
                    'Estatus' => 'Calificado'
                ]);

            return response()->json([
                'ok' => true,
                'message' => 'Calificación guardada',
                'tejedor' => [
                    'cve' => $user->numero_empleado,
                    'nombre' => $user->nombre
                ]
            ]);
        }

        if ($action === 'observaciones') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se pueden modificar observaciones después de terminar el atado'], 422);
            }

            $observaciones = $request->input('observaciones');

            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update(['Obs' => $observaciones]);

            return response()->json(['ok' => true, 'message' => 'Observaciones guardadas']);
        }

        if ($action === 'merga') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se puede modificar merga después de terminar el atado'], 422);
            }

            $mergaKg = $request->input('mergaKg');

            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update(['MergaKg' => $mergaKg]);

            return response()->json(['ok' => true, 'message' => 'Merga guardada']);
        }

        if ($action === 'maquina_estado') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se pueden modificar máquinas después de terminar el atado'], 422);
            }

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
            // Validar que no esté ya terminado, calificado o autorizado
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'El atado ya fue terminado anteriormente'], 422);
            }

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

            // Register current time as "hora de arranque" y cambiar estatus a 'Terminado'
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update([
                    'HoraArranque' => Carbon::now()->format('H:i'),
                    'Estatus' => 'Terminado'
                ]);

            return response()->json(['ok' => true, 'message' => 'Atado terminado y hora de arranque registrada']);
        }

        if ($action === 'actividad_estado') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se pueden modificar actividades después de terminar el atado'], 422);
            }

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
