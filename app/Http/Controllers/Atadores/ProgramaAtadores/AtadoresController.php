<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\EnviarMensajeTelegram;
use App\Models\Atadores\AtaActividadesModel;
use App\Models\Atadores\AtaComentariosModel;
use App\Models\Atadores\AtaDevolucionesModel;
use App\Models\Atadores\AtaKmEnhebradoModel;
use App\Models\Atadores\AtaKmMontadoModel;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Atadores\AtaMontadoActividadesModel;
use App\Models\Atadores\AtaMontadoMaquinasModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqTelares;
use App\Models\Sistema\SYSMensaje;
use App\Models\Tejido\TejInventarioTelares;
use App\Services\Atadores\ProgramaAtadoresListado;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AtadoresController extends Controller
{
    /**
     * Estatus que representan un atado ya existente para un NoJulio + NoProduccion.
     * Si alguno de estos existe, NO debe crearse otro registro en AtaMontadoTelas.
     */
    private const ESTATUS_ATADO_EXISTENTE = ['En Proceso', 'Terminado', 'Calificado', 'Autorizado'];

    /**
     * Estatus sobre los que aún se puede editar/avanzar un atado desde la pantalla de calificar.
     */
    private const ESTATUS_ATADO_ACTIVO = ['En Proceso', 'Terminado', 'Calificado'];

    //
    public function index(Request $request, ProgramaAtadoresListado $listado)
    {
        $user = Auth::user();
        $filtro = $request->get('filtro');
        $inventarioTelares = $listado->filas($user, $filtro);
        $contexto = $listado->contexto($user, $filtro);
        $vista = $request->get('vista');

        return view('modulos.atadores.programaAtadores.index', [
            'inventarioTelares' => $inventarioTelares,
            'filtroAplicado' => $contexto['filtroAplicado'],
            'telaresUsuario' => $contexto['telaresUsuario'],
            'esTejedor' => $contexto['esTejedor'],
            'esSupervisor' => $contexto['esSupervisor'],
            'vista' => $vista,
            'filtroGlobalActivo' => $contexto['filtroGlobalActivo'],
        ]);
    }

    /**
     * Estatus actual de las mismas filas que ve el tablero, para refrescar badges
     * sin volver a bajar el HTML.
     */
    public function estatus(Request $request, ProgramaAtadoresListado $listado)
    {
        $filas = $listado->filas(Auth::user(), $request->get('filtro'));

        return response()->json($filas->map(fn ($item) => [
            'id' => $item->id,
            'status' => $item->status_proceso ?? 'Activo',
        ])->values());
    }

    public function iniciarAtado(Request $request)
    {
        $noJulioRequest = $request->input('no_julio');
        $noOrdenRequest = $request->input('no_orden');
        $id = $request->input('id');

        // Si vienen no_julio y no_orden (ej. fila de Autorizados que puede ser de AtaMontadoTelas sin inventario), intentar ir directo a calificar
        if ($noJulioRequest && $noOrdenRequest) {
            $existente = AtaMontadoTelasModel::where('NoJulio', $noJulioRequest)
                ->where('NoProduccion', $noOrdenRequest)
                ->whereIn('Estatus', self::ESTATUS_ATADO_EXISTENTE)
                ->orderByDesc('Id')
                ->first();

            if ($existente) {
                return redirect()->route('atadores.calificar', [
                    'no_julio' => $noJulioRequest,
                    'no_orden' => $noOrdenRequest,
                ])->with('info', $existente->Estatus === 'Autorizado' ? 'Visualizando registro autorizado (solo lectura)' : 'Continuando con atado en proceso');
            }
        }

        // Validar que se recibió un ID cuando no hay no_julio/no_orden
        if (! $request->has('id')) {
            return redirect()->route('atadores.programa')->with('error', 'Debe seleccionar un registro');
        }

        // Obtener el registro específico del inventario de telares
        $item = TejInventarioTelares::find($id);

        if (! $item) {
            return redirect()->route('atadores.programa')->with('error', 'Registro no encontrado');
        }

        // Validar que los datos del registro coincidan con los enviados desde el frontend
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

        // NO eliminar otros procesos en estado 'En Proceso'
        // Permitir múltiples procesos simultáneos, cada uno con su propia información

        // Usuario actual como operador por defecto
        $user = Auth::user();

        // La verificación "ya existe" y el INSERT deben ser atómicos: esta acción se dispara por
        // navegación del cliente y puede llegar duplicada (doble clic, F5, reintento por red lenta).
        // Sin el lock, dos peticiones simultáneas veían "no existe" y ambas insertaban, dejando dos
        // filas gemelas del mismo NoJulio + NoProduccion que luego avanzaban a estatus distintos.
        try {
            $estatusExistente = DB::connection('sqlsrv')->transaction(function () use ($item, $user) {
                $existente = DB::connection('sqlsrv')
                    ->table('AtaMontadoTelas')
                    ->select('Id', 'Estatus')
                    ->where('NoJulio', $item->no_julio)
                    ->where('NoProduccion', $item->no_orden)
                    ->whereIn('Estatus', self::ESTATUS_ATADO_EXISTENTE)
                    ->lockForUpdate()
                    ->orderByDesc('Id')
                    ->first();

                if ($existente) {
                    return (string) $existente->Estatus;
                }

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

                return null;
            });
        } catch (\Throwable $e) {
            Log::error('Atadores: no se pudo iniciar el atado', [
                'no_julio' => $item->no_julio,
                'no_orden' => $item->no_orden,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('atadores.programa')
                ->with('error', 'No se pudo iniciar el atado. Intente nuevamente.');
        }

        // Otra petición (o un intento anterior) ya creó el atado: continuar sobre él, no duplicar.
        if ($estatusExistente !== null) {
            return redirect()->route('atadores.calificar', [
                'no_julio' => $item->no_julio,
                'no_orden' => $item->no_orden,
            ])->with('info', $estatusExistente === 'Autorizado' ? 'Visualizando registro autorizado (solo lectura)' : 'Continuando con atado en proceso');
        }

        // Actualizar el estado en tej_inventario_telares a "En Proceso"
        $item->status = 'En Proceso';
        $item->save();

        // Karl Mayer no usa el checklist de atadoras. No se copian máquinas ni actividades.
        if (! $this->esAtadoKarlMayer($item->tipo, $item->no_telar)) {
            $this->sembrarMaquinasYActividades($item->no_julio, $item->no_orden, $item->turno);
        }

        // Redirigir a la página de calificar atadores con los parámetros del registro seleccionado
        return redirect()->route('atadores.calificar', [
            'no_julio' => $item->no_julio,
            'no_orden' => $item->no_orden,
        ])->with('success', 'Atado iniciado correctamente');
    }

    /**
     * Montado o enhebrado de una barra. Una fila por julio y orden, en su propia tabla.
     */
    private function guardarProcesoKm(Request $request, AtaMontadoTelasModel $montado, string $modelo)
    {
        if (! $this->esAtadoKarlMayer($montado->Tipo, $montado->NoTelarId)) {
            return response()->json(['ok' => false, 'message' => 'Este atado no es de Karl Mayer'], 422);
        }

        if ($montado->Estatus !== 'En Proceso') {
            return response()->json(['ok' => false, 'message' => 'Solo se puede capturar mientras el atado está en proceso'], 422);
        }

        $datos = [];
        foreach ([1, 2, 3] as $n) {
            $datos['CveEmpl'.$n] = $this->textoKm($request->input('cve'.$n), 30);
            $datos['NomEmpl'.$n] = $this->textoKm($request->input('nombre'.$n), 150);
        }

        try {
            $datos['FechaInicio'] = $this->fechaKm($request->input('fecha_inicio'));
            $datos['FechaFin'] = $this->fechaKm($request->input('fecha_fin'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $modelo::updateOrCreate(
            ['NoJulio' => $montado->NoJulio, 'NoProduccion' => $montado->NoProduccion],
            $datos
        );

        return response()->json(['ok' => true, 'message' => 'Guardado']);
    }

    private function textoKm(mixed $valor, int $max): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, $max);
    }

    private function fechaKm(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }

        throw new \InvalidArgumentException('La fecha debe ir como aaaa-mm-dd hh:mm');
    }

    /**
     * Una barra de Karl Mayer es tipo 1-4. Si el tipo no viene, el telar 401-402 también lo es.
     */
    private function esAtadoKarlMayer($tipo, $noTelar): bool
    {
        if (preg_match('/^[1-4]$/', trim((string) $tipo))) {
            return true;
        }

        return TelarSalonResolver::esKarlMayer(null, (string) $noTelar);
    }

    /**
     * Crea las filas base de máquinas y actividades del catálogo para un folio.
     * Es idempotente: si la fila ya existe no se inserta otra.
     */
    private function sembrarMaquinasYActividades($noJulio, $noProduccion, $turno): void
    {
        foreach (AtaMaquinasModel::all() as $maquina) {
            $existe = AtaMontadoMaquinasModel::where('NoJulio', $noJulio)
                ->where('NoProduccion', $noProduccion)
                ->where('MaquinaId', $maquina->MaquinaId)
                ->exists();

            if ($existe) {
                continue;
            }

            AtaMontadoMaquinasModel::create([
                'NoJulio' => $noJulio,
                'NoProduccion' => $noProduccion,
                'MaquinaId' => $maquina->MaquinaId,
                'Estado' => 0, // Por defecto inactivo
                'NomEmpleado' => null,
                'NomEmpl' => null,
            ]);
        }

        foreach (AtaActividadesModel::all() as $actividad) {
            $existe = AtaMontadoActividadesModel::where('NoJulio', $noJulio)
                ->where('NoProduccion', $noProduccion)
                ->where('ActividadId', $actividad->ActividadId)
                ->exists();

            if ($existe) {
                continue;
            }

            AtaMontadoActividadesModel::create([
                'NoJulio' => $noJulio,
                'NoProduccion' => $noProduccion,
                'ActividadId' => $actividad->ActividadId,
                'Porcentaje' => $actividad->Porcentaje,
                'Estado' => 0, // Por defecto inactivo
                'CveEmpl' => null,
                'NomEmpl' => null,
                'Turno' => $turno,
            ]);
        }
    }

    public function calificarAtadores(Request $request)
    {
        // Obtener parámetros opcionales para filtrar el registro correcto
        $noJulio = $request->query('no_julio');
        $noOrden = $request->query('no_orden');

        // Si se proporcionan parámetros, filtrar por ellos para obtener el registro específico
        if ($noJulio && $noOrden) {
            // Buscar el registro específico en cualquier estado activo o Autorizado
            $montadoTelas = AtaMontadoTelasModel::whereIn('Estatus', self::ESTATUS_ATADO_EXISTENTE)
                ->where('NoJulio', $noJulio)
                ->where('NoProduccion', $noOrden)
                ->orderBy('Fecha', 'desc')
                ->orderBy('Turno', 'desc')
                ->orderByDesc('Id')
                ->get();
        } else {
            // Si no se proporcionan parámetros, obtener todos los procesos activos (incluyendo Autorizado)
            $montadoTelas = AtaMontadoTelasModel::whereIn('Estatus', self::ESTATUS_ATADO_EXISTENTE)
                ->orderBy('Fecha', 'desc')
                ->orderBy('Turno', 'desc')
                ->orderByDesc('Id')
                ->get();
        }

        $actual = $montadoTelas->first();
        $esKm = $actual && $this->esAtadoKarlMayer($actual->Tipo, $actual->NoTelarId);

        // El checklist de atadoras es de Jacquard y SMIT. En una barra no se consulta.
        $maquinasCatalogo = $esKm ? collect() : AtaMaquinasModel::orderBy('MaquinaId')->get();
        $actividadesCatalogo = $esKm ? collect() : AtaActividadesModel::orderBy('ActividadId')->get();

        $maquinasMontado = collect();
        $actividadesMontado = collect();
        $devolucionActual = null;
        $devolucionesKm = collect();
        $juliosKm = [];
        $anterioresKm = collect();
        $anteriorKm = null;
        if ($actual) {

            // Si tenemos parámetros, validar que el registro coincida
            if ($noJulio && $noOrden) {
                if ($actual->NoJulio != $noJulio || $actual->NoProduccion != $noOrden) {
                    // Si no coincide, mostrar mensaje de error
                    return redirect()->route('atadores.programa')
                        ->with('error', 'No se encontró el proceso especificado (No. Julio: '.$noJulio.', No. Orden: '.$noOrden.')');
                }
            }

            if (! $esKm) {
                $maquinasMontado = AtaMontadoMaquinasModel::where('NoJulio', $actual->NoJulio)
                    ->where('NoProduccion', $actual->NoProduccion)
                    ->get()
                    ->keyBy('MaquinaId');

                $actividadesMontado = AtaMontadoActividadesModel::where('NoJulio', $actual->NoJulio)
                    ->where('NoProduccion', $actual->NoProduccion)
                    ->get()
                    ->keyBy('ActividadId');
            }

            if ($esKm) {
                $devolucionesKm = AtaDevolucionesModel::where('RefId', $actual->Id)
                    ->orderBy('Id')
                    ->get();
                $devolucionActual = $devolucionesKm->first();
                // Si la barra tuvo varios atados (órdenes) antes, el select elige de cuál se devuelve.
                $devoluciones = app(AtaDevolucionesController::class);
                $anterioresKm = $devoluciones->atadosAnterioresKm($actual);
                $anteriorKm = $devoluciones->atadoAnteriorKm(
                    $actual,
                    $request->integer('anterior') ?: null,
                    $devolucionesKm,
                    $anterioresKm,
                );
                $juliosKm = $devoluciones->filasParaCalificar($anteriorKm, $devolucionesKm);
            } else {
                $devolucionActual = AtaDevolucionesModel::where('RefId', $actual->Id)
                    ->orderByDesc('Id')
                    ->first();
            }

            // En Jacquard/SMIT, si el inicio no sembró actividades, se crean al abrir.
            $faltantes = $actividadesCatalogo->filter(function ($act) use ($actividadesMontado) {
                return ! $actividadesMontado->has((string) $act->ActividadId);
            });

            if ($faltantes->isNotEmpty()) {
                foreach ($faltantes as $act) {
                    AtaMontadoActividadesModel::create([
                        'NoJulio' => $actual->NoJulio,
                        'NoProduccion' => $actual->NoProduccion,
                        'ActividadId' => $act->ActividadId,
                        'Porcentaje' => $act->Porcentaje,
                        'Estado' => 0,
                        'CveEmpl' => null,
                        'NomEmpl' => null,
                        'Turno' => $actual->Turno,
                    ]);
                }

                // Recargar actividades del folio ya con faltantes creadas
                $actividadesMontado = AtaMontadoActividadesModel::where('NoJulio', $actual->NoJulio)
                    ->where('NoProduccion', $actual->NoProduccion)
                    ->get()
                    ->keyBy('ActividadId');
            }
        }

        $kmMontado = null;
        $kmEnhebrado = null;
        if ($esKm && $actual) {
            $kmMontado = AtaKmMontadoModel::where('NoJulio', $actual->NoJulio)
                ->where('NoProduccion', $actual->NoProduccion)
                ->first();
            $kmEnhebrado = AtaKmEnhebradoModel::where('NoJulio', $actual->NoJulio)
                ->where('NoProduccion', $actual->NoProduccion)
                ->first();
        }

        $comentarios = $esKm ? collect() : AtaComentariosModel::orderBy('Nota1')->get();

        // Catálogo de telares de la planta, para el select "Telar" del panel de Devolución.
        $telaresCatalogo = ReqTelares::orderBy('NoTelarId')
            ->pluck('NoTelarId')
            ->filter()
            ->unique()
            ->values();

        return view(
            'modulos.atadores.calificar-atadores.index',
            compact(
                'montadoTelas',
                'maquinasCatalogo',
                'maquinasMontado',
                'actividadesCatalogo',
                'actividadesMontado',
                'comentarios',
                'telaresCatalogo',
                'devolucionActual',
                'devolucionesKm',
                'juliosKm',
                'anterioresKm',
                'anteriorKm',
                'esKm',
                'kmMontado',
                'kmEnhebrado'
            )
        );
    }

    /**
     * Pantalla propia de montado o de enhebrado. Cada una lee solo su tabla.
     */
    public function procesoKm(Request $request, string $proceso)
    {
        if (! in_array($proceso, ['montado', 'enhebrado'], true)) {
            abort(404);
        }

        $noJulio = trim((string) $request->query('no_julio'));
        $noOrden = trim((string) $request->query('no_orden'));
        if ($noJulio === '' || $noOrden === '') {
            return redirect()->route('atadores.programa')
                ->with('error', 'Faltan el julio y la orden');
        }

        $item = AtaMontadoTelasModel::whereIn('Estatus', self::ESTATUS_ATADO_EXISTENTE)
            ->where('NoJulio', $noJulio)
            ->where('NoProduccion', $noOrden)
            ->orderByDesc('Id')
            ->first();

        if (! $item || ! $this->esAtadoKarlMayer($item->Tipo, $item->NoTelarId)) {
            return redirect()->route('atadores.programa')
                ->with('error', 'Ese atado no es de una barra Karl Mayer');
        }

        $modelo = $proceso === 'montado' ? AtaKmMontadoModel::class : AtaKmEnhebradoModel::class;
        $registro = $modelo::query()
            ->where('NoJulio', $item->NoJulio)
            ->where('NoProduccion', $item->NoProduccion)
            ->first();

        $titulo = $proceso === 'montado' ? 'Montado' : 'Enhebrado';

        return view(
            'modulos.atadores.calificar-atadores.proceso-km',
            compact('item', 'registro', 'proceso', 'titulo')
        );
    }

    public function save(Request $request)
    {
        $action = $request->input('action');

        $user = Auth::user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        // Obtener parámetros para identificar el registro correcto
        $noJulio = $request->input('no_julio');
        $noOrden = $request->input('no_orden');

        // Validar que se proporcionen los parámetros necesarios
        if (! $noJulio || ! $noOrden) {
            return response()->json(['ok' => false, 'message' => 'Faltan parámetros necesarios (no_julio o no_orden)'], 422);
        }

        // Construir la consulta para obtener el registro correcto filtrando por NoJulio y NoProduccion
        // Buscar en estados activos (no Autorizado)
        // El desempate por Id es obligatorio: Fecha + Turno no son únicos y, si existieran dos filas
        // para el mismo folio, SQL Server podría devolver una distinta en cada petición y las acciones
        // (terminar / calificar / autorizar) acabarían aplicándose a registros diferentes.
        $montado = AtaMontadoTelasModel::whereIn('Estatus', self::ESTATUS_ATADO_ACTIVO)
            ->where('NoJulio', $noJulio)
            ->where('NoProduccion', $noOrden)
            ->orderBy('Fecha', 'desc')
            ->orderBy('Turno', 'desc')
            ->orderByDesc('Id')
            ->first();

        if (! $montado) {
            return response()->json(['ok' => false, 'message' => 'No hay atado activo para el registro especificado (No. Julio: '.$noJulio.', No. Orden: '.$noOrden.')'], 404);
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
                // Estatus → AtaDevoluciones vía AtaMontadoTelasObserver
                $montado->save();

                // 2. Guardar en TejHistorialInventarioTelares con todos los campos
                // Usar query builder para tener mejor control sobre el formato de fechas

                // Preparar FechaRequerimiento
                $fechaRequerimiento = null;
                if ($montado->Fecha) {
                    try {
                        if (is_string($montado->Fecha)) {
                            $fechaRequerimiento = Carbon::parse($montado->Fecha);
                        } elseif ($montado->Fecha instanceof \DateTime || $montado->Fecha instanceof Carbon) {
                            $fechaRequerimiento = Carbon::instance($montado->Fecha);
                        }
                    } catch (\Exception $e) {
                    }
                }

                // Preparar HoraParo (remover microsegundos si existen)
                $horaParo = null;
                if ($montado->HoraParo) {
                    $horaParoStr = trim((string) $montado->HoraParo);
                    // Remover microsegundos si existen
                    if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $horaParoStr, $matches)) {
                        $horaParo = $matches[1];
                    } elseif (preg_match('/^(\d{1,2}:\d{2})/', $horaParoStr, $matches)) {
                        $horaParo = $matches[1].':00';
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

                // Karl Mayer: una barra lleva hasta 4 julios (no_julio..no_julio4) y la fila de
                // inventario se borra abajo. Sin una fila de historial por julio, la devolución
                // del siguiente atado de la barra solo encontraba el primero.
                $filasHistorial = [$datosHistorial];
                if ($registroOriginal && $this->esAtadoKarlMayer($montado->Tipo, $montado->NoTelarId)) {
                    foreach ([['no_julio2', 'no_orden2'], ['no_julio3', 'no_orden3'], ['no_julio4', 'no_orden4']] as [$colJulio, $colOrden]) {
                        $julioExtra = trim((string) ($registroOriginal->{$colJulio} ?? ''));
                        if ($julioExtra === '') {
                            continue;
                        }
                        $filasHistorial[] = [
                            ...$datosHistorial,
                            'NoJulio' => $julioExtra,
                            'NoProduccion' => trim((string) ($registroOriginal->{$colOrden} ?? '')) ?: $montado->NoProduccion,
                        ];
                    }
                }

                // Insertar usando query builder para mejor control
                DB::connection('sqlsrv')
                    ->table('TejHistorialInventarioTelares')
                    ->insert($filasHistorial);

                // 3. Guardar datos de máquinas y actividades del proceso actual.
                // Karl Mayer no usa el checklist (tiene Montado/Enhebrado): no se siembra.
                if (! $this->esAtadoKarlMayer($montado->Tipo, $montado->NoTelarId)) {
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
                        if (! $existe) {
                            // Crear registro por defecto
                            AtaMontadoMaquinasModel::create([
                                'NoJulio' => $montado->NoJulio,
                                'NoProduccion' => $montado->NoProduccion,
                                'MaquinaId' => $maq->MaquinaId,
                                'Estado' => 0, // Por defecto inactivo
                                'NomEmpleado' => null,
                                'NomEmpl' => null,
                            ]);
                        }
                    }

                    $actividadesCatalogo = AtaActividadesModel::all();
                    foreach ($actividadesCatalogo as $act) {
                        $existe = $actividadesActuales->where('ActividadId', $act->ActividadId)->first();
                        if (! $existe) {
                            // Crear registro por defecto
                            AtaMontadoActividadesModel::create([
                                'NoJulio' => $montado->NoJulio,
                                'NoProduccion' => $montado->NoProduccion,
                                'ActividadId' => $act->ActividadId,
                                'Porcentaje' => $act->Porcentaje,
                                'Estado' => 0, // Por defecto inactivo
                                'CveEmpl' => null,
                                'NomEmpl' => null,
                                'Turno' => $montado->Turno,
                            ]);
                        }
                    }
                }

                // Commit de la transacción SQL Server
                DB::connection('sqlsrv')->commit();

            } catch (\Exception $e) {
                DB::connection('sqlsrv')->rollBack();

                return response()->json(['ok' => false, 'message' => 'Error al autorizar: '.$e->getMessage()]);
            }

            // 4. Eliminar el registro original de tej_inventario_telares (MySQL)
            // IMPORTANTE: se hace FUERA del try-catch de SQL Server. El commit de SQL Server ya
            // ocurrió, así que un fallo aquí NO debe disparar un rollBack inútil ni devolver ok=false
            // dejando el registro huérfano (Autorizado en SQL Server pero presente en MySQL).
            // Se reintenta por id y si aun así falla, se registra en log para limpieza posterior.
            if ($registroOriginal) {
                try {
                    $eliminado = TejInventarioTelares::where('no_julio', $montado->NoJulio)
                        ->where('no_orden', $montado->NoProduccion)
                        ->delete();

                    if (! $eliminado) {
                        // Fallback por id directo
                        TejInventarioTelares::whereKey($registroOriginal->getKey())->delete();
                    }
                } catch (\Exception $e) {
                    Log::warning('Atadores: registro autorizado pero no se pudo eliminar de tej_inventario_telares', [
                        'id' => $registroOriginal->getKey(),
                        'no_julio' => $montado->NoJulio,
                        'no_orden' => $montado->NoProduccion,
                        'error' => $e->getMessage(),
                    ]);
                }
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
                    'nombre' => $user->nombre,
                ],
            ]);
        }

        if ($action === 'calificacion') {
            // Validar que esté en estado 'Terminado' para poder calificar
            if ($montado->Estatus !== 'Terminado') {
                return response()->json(['ok' => false, 'message' => 'Debe terminar el atado antes de calificarlo'], 422);
            }

            $data = $request->validate([
                'calidad' => ['required', 'integer', 'min:1', 'max:10'],
                'limpieza' => ['required', 'integer', 'min:5', 'max:10'],
                'comments_tej' => ['nullable', 'string', 'max:500'],
            ]);

            // Eloquent save para disparar AtaMontadoTelasObserver (sync Estatus → AtaDevoluciones)
            $montado->Calidad = (int) $data['calidad'];
            $montado->Limpieza = (int) $data['limpieza'];
            $montado->comments_tej = $data['comments_tej'] ?? null;
            $montado->CveTejedor = $user->numero_empleado;
            $montado->NomTejedor = $user->nombre;
            $montado->Estatus = 'Calificado';
            $montado->save();

            // Actualizar también el status en tej_inventario_telares
            TejInventarioTelares::where('no_julio', $montado->NoJulio)
                ->where('no_orden', $montado->NoProduccion)
                ->update(['status' => 'Calificado']);

            return response()->json([
                'ok' => true,
                'message' => 'Calificación guardada',
                'tejedor' => [
                    'cve' => $user->numero_empleado,
                    'nombre' => $user->nombre,
                ],
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
                ->where('Id', $montado->Id)
                ->update(['Obs' => $observaciones]);

            return response()->json(['ok' => true, 'message' => 'Observaciones guardadas']);
        }

        if ($action === 'merga') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se puede modificar merga después de terminar el atado'], 422);
            }

            // Obtener el valor de mergaKg y validar que sea numérico
            $mergaKg = $request->input('mergaKg');

            // Validar que el valor sea numérico válido
            if ($mergaKg !== null && $mergaKg !== '') {
                $mergaKg = is_numeric($mergaKg) ? (float) $mergaKg : null;
            } else {
                $mergaKg = null;
            }

            // Realizar el update
            $affected = DB::connection('sqlsrv')
                ->table('AtaMontadoTelas')
                ->where('Id', $montado->Id)
                ->update(['MergaKg' => $mergaKg]);

            // Verificar si se actualizó el registro
            if ($affected > 0) {

                return response()->json([
                    'ok' => true,
                    'message' => 'Merma guardada correctamente',
                    'mergaKg' => $mergaKg,
                    'affected' => $affected,
                ]);
            } else {

                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo actualizar la merma. Verifica que el registro exista.',
                ], 500);
            }
        }

        if ($action === 'folio_paro') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se puede modificar Folio Paro después de terminar el atado'], 422);
            }

            $folioParo = trim((string) $request->input('folio_paro', ''));

            try {
                $schema = Schema::connection('sqlsrv');
                if (! $schema->hasColumn('AtaMontadoTelas', 'FolioParo')) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'La columna FolioParo no existe en AtaMontadoTelas (SQL Server).',
                    ], 422);
                }

                // Evitar error de truncamiento: leer longitud real de la columna en SQL Server.
                $col = DB::connection('sqlsrv')->selectOne(
                    "SELECT CHARACTER_MAXIMUM_LENGTH AS maxlen
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = 'AtaMontadoTelas' AND COLUMN_NAME = 'FolioParo'"
                );
                $maxLen = isset($col->maxlen) ? (int) $col->maxlen : null;
                if ($maxLen && mb_strlen($folioParo) > $maxLen) {
                    return response()->json([
                        'ok' => false,
                        'message' => "Folio Paro excede la longitud permitida ({$maxLen}). Ajusta el tamaño de la columna FolioParo o captura menos caracteres.",
                    ], 422);
                }

                DB::connection('sqlsrv')
                    ->table('AtaMontadoTelas')
                    ->where('Id', $montado->Id)
                    ->update(['FolioParo' => $folioParo !== '' ? $folioParo : null]);
            } catch (\Throwable $e) {
                Log::error('Error al guardar FolioParo', [
                    'error' => $e->getMessage(),
                    'no_julio' => $montado->NoJulio ?? null,
                    'no_orden' => $montado->NoProduccion ?? null,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo guardar FolioParo: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Folio Paro guardado correctamente',
                'folio_paro' => $folioParo,
            ]);
        }

        if ($action === 'maquina_estado') {
            // Validar que solo se pueda modificar en estado 'En Proceso'
            if (in_array($montado->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) {
                return response()->json(['ok' => false, 'message' => 'No se pueden modificar máquinas después de terminar el atado'], 422);
            }

            $data = $request->validate([
                'maquinaId' => ['required', 'string', 'max:50'],
                'estado' => ['required', 'boolean'],
            ]);

            $estado = $data['estado'] ? 1 : 0;

            // Solo guardar el estado (1 o 0)
            DB::connection('sqlsrv')
                ->table('AtaMontadoMaquinas')
                ->updateOrInsert(
                    [
                        'NoJulio' => $montado->NoJulio,
                        'NoProduccion' => $montado->NoProduccion,
                        'MaquinaId' => $data['maquinaId'],
                    ],
                    ['Estado' => $estado]
                );

            return response()->json([
                'ok' => true,
                'message' => 'Estado de máquina actualizado',
            ]);
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
                    'message' => 'Debe capturar la merma (Kg) antes de terminar el atado.',
                ], 422);
            }

            // Si existe devolución asociada, el Julio es obligatorio (no puede ir NULL).
            $devolucionSinJulio = AtaDevolucionesModel::where('RefId', $montado->Id)
                ->get()
                ->contains(fn ($fila) => trim((string) ($fila->NoJulio ?? '')) === '');
            if ($devolucionSinJulio) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La devolución debe tener un Julio seleccionado antes de terminar el atado.',
                ], 422);
            }

            // Register current time as "hora de arranque" y cambiar estatus a 'Terminado'
            $commentsAta = $request->input('comments_ata', '');
            $ahora = Carbon::now();
            $horaArranque = $ahora->format('H:i');
            $fechaArranque = $this->resolverFechaArranque($ahora);

            // Calcular TiempoParo como diferencia entre HrInicio y HoraArranque (formato time HH:MM:SS)
            $tiempoParo = null;
            if (! empty($montado->HrInicio)) {
                try {
                    $hrInicio = Carbon::parse($montado->HrInicio);
                    $hrArranque = Carbon::parse($horaArranque);
                    // Si HoraArranque es menor que HrInicio, asumimos que cruzó medianoche
                    if ($hrArranque->lt($hrInicio)) {
                        $hrArranque->addDay();
                    }
                    $diffMinutos = $hrInicio->diffInMinutes($hrArranque);
                    $horas = intdiv($diffMinutos, 60);
                    $minutos = $diffMinutos % 60;
                    // Formatear como HH:MM:SS para tipo time de SQL Server
                    $tiempoParo = sprintf('%02d:%02d:00', $horas, $minutos);
                } catch (\Throwable $e) {
                    Log::warning('Error calculando TiempoParo', [
                        'HrInicio' => $montado->HrInicio,
                        'HoraArranque' => $horaArranque,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Eloquent save para disparar AtaMontadoTelasObserver (sync Estatus → AtaDevoluciones)
            $montado->HoraArranque = $horaArranque;
            $montado->FechaArranque = $fechaArranque;
            $montado->TiempoParo = $tiempoParo;
            $montado->Estatus = 'Terminado';
            $montado->comments_ata = $commentsAta;
            $montado->save();

            // Actualizar también el status en tej_inventario_telares
            TejInventarioTelares::where('no_julio', $montado->NoJulio)
                ->where('no_orden', $montado->NoProduccion)
                ->update(['status' => 'Terminado']);

            try {
                $this->enviarNotificacionTelegramAtadoTerminado($montado, $user, $horaArranque);
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar notificacion de atado terminado a Telegram', [
                    'error' => $e->getMessage(),
                    'no_julio' => $montado->NoJulio ?? null,
                    'no_orden' => $montado->NoProduccion ?? null,
                ]);
            }

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

            // Si no existe fila previa para este folio+actividad, crearla.
            $actividadCatalogo = AtaActividadesModel::where('ActividadId', $actividadId)->first();
            $updateData['Porcentaje'] = $actividadCatalogo?->Porcentaje ?? 0;
            $updateData['Turno'] = $montado->Turno;

            DB::connection('sqlsrv')
                ->table('AtaMontadoActividades')
                ->updateOrInsert(
                    [
                        'NoJulio' => $montado->NoJulio,
                        'NoProduccion' => $montado->NoProduccion,
                        'ActividadId' => $actividadId,
                    ],
                    $updateData
                );

            // Devolver el operador actualizado para reflejar en la UI
            $operador = $estado ? trim(($user->numero_empleado ?? '').' - '.($user->nombre ?? '')) : '-';

            return response()->json([
                'ok' => true,
                'message' => 'Estado de actividad actualizado',
                'operador' => $operador,
                'cveEmpl' => $estado ? $user->numero_empleado : null,
                'nomEmpl' => $estado ? $user->nombre : null,
            ]);
        }

        if ($action === 'km_montado' || $action === 'km_enhebrado') {
            $modelo = $action === 'km_montado' ? AtaKmMontadoModel::class : AtaKmEnhebradoModel::class;

            return $this->guardarProcesoKm($request, $montado, $modelo);
        }

        return response()->json(['ok' => false, 'message' => 'Acción no válida'], 422);
    }

    /**
     * Regla operativa de fecha para turno 3:
     * Entre 00:00:00 y 06:30:00 se considera fecha del día anterior.
     */
    private function resolverFechaArranque(Carbon $momento): string
    {
        $corteTurno3 = $momento->copy()->startOfDay()->addHours(6)->addMinutes(30);
        if ($momento->lessThanOrEqualTo($corteTurno3)) {
            return $momento->copy()->subDay()->toDateString();
        }

        return $momento->toDateString();
    }

    /**
     * Enviar notificación a Telegram al terminar el atado.
     * Destinatarios: SYSMensajes con Atadores=1 y Activo=1.
     */
    private function enviarNotificacionTelegramAtadoTerminado(AtaMontadoTelasModel $montado, $usuario, ?string $horaArranque = null): void
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::warning('No se pudo enviar notificacion a Telegram: TELEGRAM_BOT_TOKEN no configurado');

            return;
        }

        $chatIds = SYSMensaje::getChatIdsPorModulo('Atadores');
        if (empty($chatIds)) {
            $chatIdGlobal = trim((string) config('services.telegram.chat_id', ''));
            if ($chatIdGlobal !== '') {
                $chatIds = [$chatIdGlobal];
                Log::info('Usando TELEGRAM_CHAT_ID global como destinatario para atadores');
            } else {
                Log::warning('No hay destinatarios con Atadores activo en SYSMensajes ni TELEGRAM_CHAT_ID configurado');

                return;
            }
        }

        $chatIds = collect($chatIds)
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($chatIds)) {
            Log::warning('No hay chat IDs validos para notificacion de atadores');

            return;
        }

        $mensaje = "ATADO TERMINADO\n\n";
        $mensaje .= 'Telar: '.($montado->NoTelarId ?? 'N/A')."\n";
        $mensaje .= $this->esAtadoKarlMayer($montado->Tipo, $montado->NoTelarId)
            ? 'Barra: '.($montado->Tipo ?? 'N/A')."\n"
            : 'Tipo: '.($montado->Tipo ?? 'N/A')."\n";
        $mensaje .= 'No. Julio: '.($montado->NoJulio ?? 'N/A')."\n";
        $mensaje .= 'No. Orden: '.($montado->NoProduccion ?? 'N/A')."\n";
        if (! empty($montado->Metros)) {
            $mensaje .= "Metros: {$montado->Metros}\n";
        }
        if (! empty($montado->MergaKg)) {
            $mensaje .= "Merma Kg: {$montado->MergaKg}\n";
        }
        if (! empty($montado->HoraParo)) {
            $mensaje .= "Hora Paro: {$montado->HoraParo}\n";
        }
        if (! empty($horaArranque)) {
            $mensaje .= "Hora Arranque: {$horaArranque}\n";
        }
        $mensaje .= 'Fecha: '.Carbon::now()->format('d/m/Y')."\n";

        $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
        $numeroEmpleado = $usuario->numero_empleado ?? null;
        if (! empty($nombreUsuario)) {
            $mensaje .= "Operador: {$nombreUsuario}";
            if (! empty($numeroEmpleado)) {
                $mensaje .= " ({$numeroEmpleado})";
            }
            $mensaje .= "\n";
        }

        // En la cola: el atador no espera a Telegram (PERF-13).
        EnviarMensajeTelegram::encolar(new EnviarMensajeTelegram(
            chatIds: $chatIds,
            texto: $mensaje,
            extra: [],
            mensajeLog: 'Error al enviar notificacion de atado terminado a Telegram',
            contextoLog: [
                'no_julio' => $montado->NoJulio ?? null,
                'no_orden' => $montado->NoProduccion ?? null,
            ],
        ));
    }
}
