<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Atadores\AtaMontadoMaquinasModel;
use App\Models\Atadores\AtaMontadoActividadesModel;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Atadores\AtaActividadesModel;
use App\Models\Atadores\AtaComentariosModel;
use App\Models\Tejido\TejHistorialInventarioTelaresModel;
use App\Models\Tejedores\TelTelaresOperador;

class AtadoresController extends Controller
{
    //
    public function index(Request $request){
        $user = Auth::user();
        $area = $user->area ?? '';
        $puesto = $user->puesto ?? '';

        // Obtener filtro personalizado del request, si existe
        $filtroPersonalizado = $request->get('filtro', null);

        // Construir la consulta base
        $query = TejInventarioTelares::select(
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
            'tej_inventario_telares.ConfigId',
            'tej_inventario_telares.InventSizeId',
            'tej_inventario_telares.InventColorId',
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
        ->whereNotNull('tej_inventario_telares.no_julio')
        ->where('tej_inventario_telares.no_julio', '!=', ''); // No_julio debe estar lleno

        // Determinar filtro por defecto según área/puesto (solo para mostrar en el frontend)
        // El filtrado real se hace en el cliente sin recargar
        $filtroAplicado = 'todos'; // Por defecto mostrar todos
        $telaresUsuario = []; // Telares del usuario si es tejedor

        // Verificar si es tejedor (área Smith, Itema o Jacquard)
        $areaUpper = strtoupper(trim($area));
        $esTejedor = in_array($areaUpper, ['SMITH', 'ITEMA', 'JACQUARD']);

        // Si no hay filtro personalizado, determinar el filtro por defecto según área/puesto
        $areaNorm = strtolower(trim((string) $area));
        $puestoNorm = strtolower(trim((string) $puesto));
        $esAreaAtadores = in_array($areaNorm, ['atador', 'atadores'], true);

        if (!$filtroPersonalizado) {
            if ($esTejedor) {
                // Tejedor: obtener sus telares y aplicar filtro terminados
                $filtroAplicado = 'terminados';
                $telaresUsuario = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
                    ->pluck('NoTelarId')
                    ->toArray();
            } elseif ($esAreaAtadores || in_array($puestoNorm, ['atador', 'atadores'], true)) {
                // Área Atadores (o puesto atador/atadores): ver solo Activo por defecto
                $filtroAplicado = 'activo';
            } elseif ($puestoNorm === 'supervisor') {
                $filtroAplicado = 'terminados';
            }
        } else {
            $filtroAplicado = $filtroPersonalizado;
            // Si el usuario es tejedor, obtener sus telares para el filtro
            if ($esTejedor) {
                $telaresUsuario = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
                    ->pluck('NoTelarId')
                    ->toArray();
            }
        }

        // No aplicar filtros en el servidor - se harán en el cliente
        // Siempre devolver todos los registros

        $inventarioTelares = $query->orderBy('tej_inventario_telares.fecha', 'asc')
            ->orderBy('tej_inventario_telares.turno', 'asc')
            ->get();

        return view("modulos.atadores.programaAtadores.index", compact('inventarioTelares', 'filtroAplicado', 'telaresUsuario', 'esTejedor'));
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
            'HrInicio' => Carbon::now()->format('H:i'),
            'ConfigId' => $item->ConfigId,
            'InventSizeId' => $item->InventSizeId,
            'InventColorId' => $item->InventColorId,
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
                // Obtener el registro original de tej_inventario_telares ANTES de la transacción
                // para capturar campos adicionales (Localidad, Cuenta, Calibre, TipoAtado, etc.)
                $registroOriginal = TejInventarioTelares::where('no_julio', $montado->NoJulio)
                    ->where('no_orden', $montado->NoProduccion)
                    ->first();

                // Iniciar transacción en la conexión SQL Server
                DB::connection('sqlsrv')->beginTransaction();

                // 1. Actualizar supervisor en AtaMontadoTelas usando el modelo directamente
                $comentariosSupervisor = $request->input('comments_sup', $request->input('comentarios_supervisor', ''));

                $montado->CveSupervisor = $user->numero_empleado;
                $montado->NomSupervisor = $user->nombre;
                $montado->FechaSupervisor = Carbon::now();
                $montado->Estatus = 'Autorizado';
                $montado->CveTejedor = $montado->CveTejedor ?: $user->numero_empleado;
                $montado->NomTejedor = $montado->NomTejedor ?: $user->nombre;
                $montado->comments_sup = $comentariosSupervisor;
                $montado->save();

                // 2. Guardar en TejHistorialInventarioTelares con todos los campos
                // Usar query builder para tener mejor control sobre el formato de fechas

                // Preparar FechaRequerimiento
                $fechaRequerimiento = null;
                if ($montado->Fecha) {
                    try {
                        if (is_string($montado->Fecha)) {
                            $fechaRequerimiento = Carbon::parse($montado->Fecha);
                        } elseif ($montado->Fecha instanceof \DateTime || $montado->Fecha instanceof \Carbon\Carbon) {
                            $fechaRequerimiento = Carbon::instance($montado->Fecha);
                        }
                    } catch (\Exception $e) {
                    }
                }

                // Preparar HoraParo (remover microsegundos si existen)
                $horaParo = null;
                if ($montado->HoraParo) {
                    $horaParoStr = trim((string)$montado->HoraParo);
                    // Remover microsegundos si existen
                    if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $horaParoStr, $matches)) {
                        $horaParo = $matches[1];
                    } elseif (preg_match('/^(\d{1,2}:\d{2})/', $horaParoStr, $matches)) {
                        $horaParo = $matches[1] . ':00';
                    }
                }

                // Preparar datos para insertar
                $datosHistorial = [
                    'NoTelarId' => $montado->NoTelarId,
                    'Status' => 'Completado',
                    'Tipo' => $montado->Tipo,
                    'Cuenta' => $registroOriginal?->cuenta,
                    'Calibre' => $registroOriginal?->calibre,
                    'Turno' => $montado->Turno,
                    'Fibra' => $registroOriginal?->hilo,
                    'Metros' => $montado->Metros,
                    'NoJulio' => $montado->NoJulio,
                    'NoProduccion' => $montado->NoProduccion,
                    'TipoAtado' => $registroOriginal?->tipo_atado,
                    'Localidad' => $registroOriginal?->localidad,
                    'LoteProveedor' => $montado->LoteProveedor,
                    'NoProveedor' => $montado->NoProveedor,
                    'FechaAtado' => Carbon::now(),
                ];

                // Agregar FechaRequerimiento solo si existe
                if ($fechaRequerimiento) {
                    $datosHistorial['FechaRequerimiento'] = $fechaRequerimiento;
                }

                // Agregar HoraParo solo si existe
                if ($horaParo) {
                    $datosHistorial['HoraParo'] = $horaParo;
                }

                // Insertar usando query builder para mejor control
                DB::connection('sqlsrv')
                    ->table('TejHistorialInventarioTelares')
                    ->insert($datosHistorial);

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

                // Commit de la transacción SQL Server
                DB::connection('sqlsrv')->commit();

                // 4. Eliminar el registro original de tej_inventario_telares (MySQL)
                // Esto se hace fuera de la transacción SQL Server ya que es otra conexión
                if ($registroOriginal) {
                    $registroOriginal->delete();
                }

                // 5. NO eliminar las tablas de montado - mantener los registros autorizados
                // Los registros en AtaMontadoTelas, AtaMontadoMaquinas y AtaMontadoActividades
                // se conservan como registro histórico del proceso autorizado
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
                DB::connection('sqlsrv')->rollBack();

                return response()->json(['ok' => false, 'message' => 'Error al autorizar: ' . $e->getMessage()]);
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
                'comments_tej' => ['nullable','string','max:500'],
            ]);
            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update([
                    'Calidad' => (int) $data['calidad'],
                    'Limpieza' => (int) $data['limpieza'],
                    'comments_tej' => $data['comments_tej'] ?? null,
                    'CveTejedor' => $user->numero_empleado,
                    'NomTejedor' => $user->nombre,
                    'Estatus' => 'Calificado'
                ]);

            // Actualizar también el status en tej_inventario_telares
            TejInventarioTelares::where('no_julio', $montado->NoJulio)
                ->where('no_orden', $montado->NoProduccion)
                ->update(['status' => 'Calificado']);

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

            // Validar que la merma (MergaKg) esté capturada antes de terminar
            if (is_null($montado->MergaKg) || $montado->MergaKg === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Debe capturar la merma (Kg) antes de terminar el atado.'
                ], 422);
            }

            // Register current time as "hora de arranque" y cambiar estatus a 'Terminado'
            $commentsAta = $request->input('comments_ata', '');

            DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('NoJulio', $montado->NoJulio)
                ->where('NoProduccion', $montado->NoProduccion)
                ->update([
                    'HoraArranque' => Carbon::now()->format('H:i'),
                    'Estatus' => 'Terminado',
                    'comments_ata' => $commentsAta,
                ]);

            // Actualizar también el status en tej_inventario_telares
            TejInventarioTelares::where('no_julio', $montado->NoJulio)
                ->where('no_orden', $montado->NoProduccion)
                ->update(['status' => 'Terminado']);

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
