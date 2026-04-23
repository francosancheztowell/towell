<?php

namespace App\Http\Controllers\Tejedores\BPMTejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelTelaresOperador;
use App\Helpers\TurnoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Models\Tejedores\TelActividadesBPM;

class TelBpmController extends Controller
{
    /** Clave para SSYSFoliosSecuencias (debe coincidir con la columna Modulo en la BD) */
    private const FOLIO_KEY   = 'BPMTEjido';
    private const PAD_LENGTH  = 5;        // BT00001 → 5 ceros
    private const EST_CREADO  = 'Creado';
    private const EST_TERM    = 'Terminado';
    private const EST_AUTO    = 'Autorizado';

    /** Listado con filtros; último primero (acceso controlado por rutas/menú) */
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $status  = $request->get('status');
        // El listado no muestra paginación visual; limitamos para evitar páginas demasiado pesadas.
        $perPage = (int) $request->get('per_page', 300);
        $perPage = max(50, min($perPage, 1000));

        $items = TelBpmModel::query()
            ->select([
                'TelBPM.Folio',
                'TelBPM.Status',
                'TelBPM.Fecha',
                'TelBPM.CveEmplRec',
                'TelBPM.NombreEmplRec',
                'TelBPM.TurnoRecibe',
                'TelBPM.CveEmplEnt',
                'TelBPM.NombreEmplEnt',
                'TelBPM.TurnoEntrega',
                'TelBPM.CveEmplAutoriza',
                'TelBPM.NomEmplAutoriza',
                'TelBPM.Comentarios',
            ])
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where('TelBPM.Folio', 'like', "%{$q}%")
                    ->orWhere('TelBPM.CveEmplRec', 'like', "%{$q}%")
                    ->orWhere('TelBPM.NombreEmplRec', 'like', "%{$q}%")
                    ->orWhere('TelBPM.CveEmplEnt', 'like', "%{$q}%")
                    ->orWhere('TelBPM.NombreEmplEnt', 'like', "%{$q}%");
            })
            ->when($status, fn($qry) => $qry->where('TelBPM.Status', $status))
            ->orderByDesc('TelBPM.Fecha') // último primero
            ->orderByDesc('TelBPM.Folio')
            ->simplePaginate($perPage)
            ->withQueryString();

        // Prefills para modal crear
        $turnoActual = TurnoHelper::getTurnoActual();
        $fechaActual = Carbon::now('America/Mexico_City');
        $user = Auth::user();

        // Obtener datos del operador y operadores de entrega
        [$operadorUsuario, $usuarioEsOperador, $operadoresEntrega] = $this->obtenerDatosOperador($user);
        $this->logTurnoDebug('index.modal_recibe', [
            'user_idusuario' => $user->idusuario ?? null,
            'user_numero_empleado' => $user->numero_empleado ?? null,
            'operador_numero_empleado' => $operadorUsuario->numero_empleado ?? null,
            'operador_turno' => $operadorUsuario->Turno ?? null,
            'usuario_es_operador' => $usuarioEsOperador,
        ]);

        return view('modulos.bpm-tejedores.tel-bpm.index', [
            'items'   => $items,
            'q'       => $q,
            'status'  => $status,
            'turnoActual' => $turnoActual,
            'fechaActual' => $fechaActual,
            'operadorUsuario' => $operadorUsuario,
            'usuarioEsOperador' => $usuarioEsOperador,
            'operadoresEntrega' => $operadoresEntrega,
        ]);
    }

    /**
     * Mostrar un folio: redirige al checklist de líneas (ruta resource espera este método).
     */
    public function show(string $folio)
    {
        return redirect()->route('tel-bpm-line.index', $folio);
    }

    /** Endpoint llamado desde el cliente para pasos del flujo crear/modal (sin logging). */
    public function logDebug(Request $request)
    {
        return response('', 204);
    }

    /** Store: Genera folio, crea header y redirige a líneas */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }
            return redirect()->back()->with('error', 'Debes iniciar sesión para crear un folio.');
        }

        try {
            $data = $request->validate([
                // Recibe (automático del usuario logueado si no viene en request)
                'CveEmplRec'    => ['nullable','string','max:30'],
                'NombreEmplRec' => ['nullable','string','max:150'],
                'TurnoRecibe'   => ['nullable','string','max:10'],
                // Entrega (estos sí los captura el usuario)
                'CveEmplEnt'    => ['required','string','max:30'],
                'NombreEmplEnt' => ['required','string','max:150'],
                'TurnoEntrega'  => ['required','string','max:10'],
            ]);
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput($request->all());
        }

        try {
            // Valores por defecto cuando no vienen en el request (usuario autenticado)
            $data['CveEmplRec']    = $data['CveEmplRec']    ?? (string) ($user->cve ?? $user->numero_empleado ?? '');
            $data['NombreEmplRec'] = $data['NombreEmplRec'] ?? (string) ($user->name ?? $user->nombre ?? '');
            $turnoRecibeResuelto = $this->resolverTurnoEmpleado($data['CveEmplRec'], $user, false);
            $data['TurnoRecibe'] = $turnoRecibeResuelto
                ?? $data['TurnoRecibe']
                ?? (string) TurnoHelper::getTurnoActual();
            $turnoEntregaResuelto = $this->resolverTurnoEmpleado($data['CveEmplEnt'], $user);
            if ($turnoEntregaResuelto !== null && $turnoEntregaResuelto !== '') {
                $data['TurnoEntrega'] = $turnoEntregaResuelto;
            }
            $this->logTurnoDebug('store.resuelto', [
                'cve_empl_rec' => $data['CveEmplRec'] ?? null,
                'turno_recibe_final' => $data['TurnoRecibe'] ?? null,
                'cve_empl_ent' => $data['CveEmplEnt'] ?? null,
                'turno_entrega_final' => $data['TurnoEntrega'] ?? null,
            ]);

            // Validar: Entrega y Recibe no pueden ser el mismo operador
            if (!empty($data['CveEmplRec']) && !empty($data['CveEmplEnt']) && $data['CveEmplRec'] === $data['CveEmplEnt']) {
                return redirect()->back()->with('error', 'Entrega y Recibe no pueden ser el mismo operador.');
            }

            $folio = $this->generarFolio();

            TelBpmModel::create([
                'Folio'            => $folio,
                'Fecha'            => Carbon::now(),
                'CveEmplRec'       => $data['CveEmplRec'],
                'NombreEmplRec'    => $data['NombreEmplRec'],
                'TurnoRecibe'      => $data['TurnoRecibe'],
                'CveEmplEnt'       => $data['CveEmplEnt'],
                'NombreEmplEnt'    => $data['NombreEmplEnt'],
                'TurnoEntrega'     => $data['TurnoEntrega'],
                'CveEmplAutoriza'  => null,
                'NomEmplAutoriza'  => null,
                'Status'           => self::EST_CREADO,
            ]);

            $this->inicializarLineasChecklist($folio, $data['CveEmplRec'], $data['TurnoRecibe']);

            return redirect()
                ->route('tel-bpm-line.index', $folio)
                ->with('success', "Folio $folio creado. Completa el checklist.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al crear el folio: ' . $e->getMessage());
        }
    }

    /** Editar header (sólo en estado 'Creado') */
    public function update(Request $request, string $folio)
    {
        $item = TelBpmModel::findOrFail($folio);
        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', 'Sólo se puede editar en estado Creado.');
        }

        $data = $request->validate([
            'CveEmplEnt'    => ['required','string','max:30'],
            'NombreEmplEnt' => ['required','string','max:150'],
            'TurnoEntrega'  => ['required','string','max:10'],
        ]);

        $item->update($data);

        return back()->with('success', 'Encabezado actualizado.');
    }

    /** Eliminar (sólo 'Creado') */
    public function destroy(string $folio)
    {
        $item = TelBpmModel::findOrFail($folio);
        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', "No se puede eliminar el folio {$folio} en estado \"{$item->Status}\". Solo se pueden eliminar folios en estado \"Creado\".");
        }

        $item->delete(); // ON DELETE CASCADE eliminará sus líneas
        // Ruta real de navegación
        return redirect()->route('tejedores.bpm')->with('success', "Folio $folio eliminado.");
    }

    /* ===================== Helpers ===================== */

    /** Genera un nuevo folio único */
    private function generarFolio(): string
    {
        return DB::transaction(function () {
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();

            if (!$row) {
                $maxFolio = DB::table('TelBPM')->where('Folio', 'like', 'BT%')->orderBy('Folio', 'desc')->value('Folio');
                $start = $maxFolio ? (int) substr($maxFolio, strlen('BT')) : 0;
                SSYSFoliosSecuencia::create(['modulo' => self::FOLIO_KEY, 'prefijo' => 'BT', 'consecutivo' => $start]);
                    $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();

                    if (!$row) {
                        throw new \RuntimeException('No existe configuración de folio para BPM Tejedores en SSYSFoliosSecuencias y no se pudo crear.');
                    }
                }

                $prefijo = $row->prefijo ?? ($row->Prefijo ?? 'BT');
                $currConsec = (int)($row->consecutivo ?? ($row->Consecutivo ?? 0));

                // Calcular máximo actual en TelBPM con ese prefijo
                $maxFolio = DB::table('TelBPM')->where('Folio', 'like', $prefijo.'%')->orderBy('Folio','desc')->value('Folio');
                $maxNum = 0;
                if ($maxFolio) {
                    $maxNum = (int) substr($maxFolio, strlen($prefijo));
                }
                if ($maxNum > $currConsec) {
                    DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->update(['consecutivo' => $maxNum]);
                }

                try {
                    $f = SSYSFoliosSecuencia::nextFolio(self::FOLIO_KEY, self::PAD_LENGTH);
                } catch (\Throwable $e) {
                    $f = SSYSFoliosSecuencia::nextFolioByPrefijo($prefijo, self::PAD_LENGTH);
                }
                $folio = $f['folio'];

                // Si por alguna razón sigue duplicado, incrementar hasta encontrar libre
                $guard = 0;
                while (TelBpmModel::find($folio) && $guard < 5) {
                    $f = SSYSFoliosSecuencia::nextFolioByPrefijo($prefijo, self::PAD_LENGTH);
                    $folio = $f['folio'];
                    $guard++;
                }

                return $folio;
            });
    }

    /** Obtiene datos del operador y operadores de entrega */
    private function obtenerDatosOperador($user): array
    {
        $operadorUsuario = null;
        $usuarioEsOperador = false;
        $operadoresEntrega = collect();

        if (!$user) {
            return [$operadorUsuario, $usuarioEsOperador, $operadoresEntrega];
        }

        try {
            $userCode = (string) ($user->cve ?? '');
            $userCodeAlt = (string) ($user->numero_empleado ?? '');
            $userName = (string) ($user->name ?? $user->nombre ?? '');

            $codes = collect([$userCode, $userCodeAlt])->filter(fn($v) => $v !== '')->unique()->values()->all();

            // Primero buscar por códigos
            $operadorUsuario = $this->operadorQuery($codes, '')->first();

            // Si no encuentra por código, intentar por nombre
            if (!$operadorUsuario && $userName !== '') {
                $operadorUsuario = TelTelaresOperador::where('nombreEmpl', 'like', "%{$userName}%")->first();
            }

            $operadorUsuario = $this->completarTurnoOperador($operadorUsuario, $user);

            $usuarioEsOperador = (bool) $operadorUsuario;

            if ($usuarioEsOperador) {
                // Usar el numero_empleado del operador encontrado para buscar telares
                $telaresUsuario = TelTelaresOperador::where('numero_empleado', $operadorUsuario->numero_empleado)
                    ->pluck('NoTelarId')
                    ->filter()
                    ->unique()
                    ->values();

                if ($telaresUsuario->isNotEmpty()) {
                    $operadoresRaw = TelTelaresOperador::query()
                        ->whereIn('NoTelarId', $telaresUsuario)
                        ->where(function($query) {
                            $query->where('Supervisor', '!=', 1)
                                  ->orWhereNull('Supervisor');
                        }) // Excluir supervisores (Supervisor = 1)
                        ->select(['numero_empleado', 'nombreEmpl', 'Turno', 'Id'])
                        ->orderByDesc('Id')
                        ->get();

                    $sysUsuarios = SYSUsuario::query()
                        ->whereIn('numero_empleado', $operadoresRaw->pluck('numero_empleado')->filter()->unique()->values())
                        ->get(['numero_empleado', 'nombre', 'turno'])
                        ->keyBy('numero_empleado');

                    $operadoresEntrega = $operadoresRaw
                        ->groupBy('numero_empleado')
                        ->map(function ($rows, $numeroEmpleado) use ($sysUsuarios) {
                            $base = $rows->first();
                            $sysUsuario = $sysUsuarios->get((string) $numeroEmpleado);
                            $nombre = trim((string) ($sysUsuario->nombre ?? '')) !== ''
                                ? (string) $sysUsuario->nombre
                                : (string) ($base->nombreEmpl ?? '');
                            $turno = trim((string) ($sysUsuario->turno ?? '')) !== ''
                                ? (string) $sysUsuario->turno
                                : (string) ($base->Turno ?? '');

                            return (object) [
                                'numero_empleado' => (string) $numeroEmpleado,
                                'nombreEmpl' => $nombre,
                                'Turno' => $turno,
                            ];
                        })
                        ->sortBy('numero_empleado', SORT_NATURAL)
                        ->values();
                }
            }
        } catch (\Throwable $e) {
        }

        return [$operadorUsuario, $usuarioEsOperador, $operadoresEntrega];
    }

    /** Inicializa las líneas del checklist para un folio */
    private function inicializarLineasChecklist(string $folio, string $cveEmplRec, string $turnoRecibe): void
    {
        try {
            $actividades = TelActividadesBPM::orderBy('Orden')->get(['Orden', 'Actividad']);

            $asignados = TelTelaresOperador::query()
                ->where('numero_empleado', $cveEmplRec)
                ->get(['NoTelarId', 'SalonTejidoId']);

            $telares = $asignados->pluck('NoTelarId')->filter()->unique()->values();
            $salonPorTelar = $asignados->mapWithKeys(fn($r) => [$r->NoTelarId => $r->SalonTejidoId])->all();

            if ($actividades->isEmpty()) {
                return;
            }
            if ($telares->isEmpty()) {
                return;
            }

            $insertados = 0;
            DB::transaction(function () use ($folio, $actividades, $telares, $salonPorTelar, $turnoRecibe, &$insertados) {
                $existentes = DB::table('TelBPMLine')
                    ->where('Folio', $folio)
                    ->select('Orden', 'NoTelarId')
                    ->get()
                    ->map(fn($row) => ((int) $row->Orden) . '|' . (string) $row->NoTelarId)
                    ->flip();

                $pendientes = [];

                foreach ($actividades as $actividad) {
                    $orden = (int) $actividad->Orden;
                    $actividadNombre = (string) $actividad->Actividad;

                    foreach ($telares as $telar) {
                        $clave = $orden . '|' . (string) $telar;
                        if (!$existentes->has($clave)) {
                            $pendientes[] = [
                                'Folio' => $folio,
                                'Orden' => $orden,
                                'NoTelarId' => (string) $telar,
                                'Actividad' => $actividadNombre,
                                'SalonTejidoId' => $salonPorTelar[$telar] ?? null,
                                'TurnoRecibe' => (string) $turnoRecibe,
                                'Valor' => null,
                            ];
                        }
                    }
                }

                foreach (array_chunk($pendientes, 200) as $chunk) {
                    DB::table('TelBPMLine')->insert($chunk);
                    $insertados += count($chunk);
                }
            });
        } catch (\Throwable $e) {
        }
    }

    private function operadorQuery(array $codes, string $userName)
    {
        return TelTelaresOperador::query()
            ->where(function ($q) use ($codes, $userName) {
                // Buscar por códigos de empleado (exacto o LIKE para manejar espacios/formatos)
                if (!empty($codes)) {
                    $q->where(function ($sub) use ($codes) {
                        foreach ($codes as $code) {
                            $sub->orWhere('numero_empleado', $code)
                                ->orWhere('numero_empleado', 'like', trim($code));
                        }
                    });
                }
                // Si no hay códigos, buscar por nombre
                if (empty($codes) && $userName !== '') {
                    $q->orWhere('nombreEmpl', 'like', "%{$userName}%");
                }
            });
    }

    private function completarTurnoOperador($operadorUsuario, $user)
    {
        if (!$operadorUsuario) {
            return $operadorUsuario;
        }

        $numeroEmpleado = (string) ($operadorUsuario->numero_empleado ?? '');
        $turno = $this->resolverTurnoEmpleado($numeroEmpleado, $user, false);
        if ($turno !== null && $turno !== '') {
            $operadorUsuario->Turno = (string) $turno;
        }

        return $operadorUsuario;
    }

    private function resolverTurnoEmpleado(?string $numeroEmpleado, $user, bool $priorizarUsuarioAutenticado = false): ?string
    {
        $numeroEmpleado = trim((string) $numeroEmpleado);
        if ($numeroEmpleado === '' && $user) {
            $numeroEmpleado = trim((string) ($user->numero_empleado ?? $user->cve ?? ''));
        }

        if ($priorizarUsuarioAutenticado && $numeroEmpleado === '' && $user && isset($user->idusuario)) {
            $sysUsuarioAuth = SYSUsuario::query()
                ->where('idusuario', $user->idusuario)
                ->first(['turno']);
            if ($sysUsuarioAuth && trim((string) ($sysUsuarioAuth->turno ?? '')) !== '') {
                $this->logTurnoDebug('resolver_turno.sysusuario_auth', [
                    'idusuario' => $user->idusuario,
                    'numero_empleado_input' => $numeroEmpleado,
                    'turno' => (string) $sysUsuarioAuth->turno,
                ]);
                return (string) $sysUsuarioAuth->turno;
            }
        }

        if ($numeroEmpleado !== '') {
            $numeroEmpleadoNumerico = ctype_digit($numeroEmpleado)
                ? (int) $numeroEmpleado
                : null;

            $sysUsuarioExacto = SYSUsuario::query()
                ->where(function ($q) use ($numeroEmpleado) {
                    $q->where('numero_empleado', $numeroEmpleado)
                        ->orWhereRaw('LTRIM(RTRIM(numero_empleado)) = ?', [$numeroEmpleado]);
                })
                ->orderByDesc('Productivo')
                ->orderByDesc('idusuario')
                ->first(['turno']);
            if ($sysUsuarioExacto && trim((string) ($sysUsuarioExacto->turno ?? '')) !== '') {
                $this->logTurnoDebug('resolver_turno.sysusuario_exacto', [
                    'numero_empleado_input' => $numeroEmpleado,
                    'numero_empleado_numeric' => $numeroEmpleadoNumerico,
                    'turno' => (string) $sysUsuarioExacto->turno,
                ]);
                return (string) $sysUsuarioExacto->turno;
            }

            if ($numeroEmpleadoNumerico !== null) {
                $sysUsuarioNumerico = SYSUsuario::query()
                    ->whereRaw('TRY_CONVERT(INT, LTRIM(RTRIM(numero_empleado))) = ?', [$numeroEmpleadoNumerico])
                    ->orderByDesc('Productivo')
                    ->orderByDesc('idusuario')
                    ->first(['turno', 'numero_empleado']);
                if ($sysUsuarioNumerico && trim((string) ($sysUsuarioNumerico->turno ?? '')) !== '') {
                    $this->logTurnoDebug('resolver_turno.sysusuario_numerico', [
                        'numero_empleado_input' => $numeroEmpleado,
                        'numero_empleado_numeric' => $numeroEmpleadoNumerico,
                        'numero_empleado_match' => $sysUsuarioNumerico->numero_empleado ?? null,
                        'turno' => (string) $sysUsuarioNumerico->turno,
                    ]);
                    return (string) $sysUsuarioNumerico->turno;
                }
            }

            $telarOperadorExacto = TelTelaresOperador::query()
                ->where(function ($q) use ($numeroEmpleado) {
                    $q->where('numero_empleado', $numeroEmpleado)
                        ->orWhereRaw('LTRIM(RTRIM(numero_empleado)) = ?', [$numeroEmpleado]);
                })
                ->orderByDesc('Id')
                ->first(['Turno']);
            if ($telarOperadorExacto && trim((string) ($telarOperadorExacto->Turno ?? '')) !== '') {
                $this->logTurnoDebug('resolver_turno.telar_exacto', [
                    'numero_empleado_input' => $numeroEmpleado,
                    'numero_empleado_numeric' => $numeroEmpleadoNumerico,
                    'turno' => (string) $telarOperadorExacto->Turno,
                ]);
                return (string) $telarOperadorExacto->Turno;
            }

            if ($numeroEmpleadoNumerico !== null) {
                $telarOperadorNumerico = TelTelaresOperador::query()
                    ->whereRaw('TRY_CONVERT(INT, LTRIM(RTRIM(numero_empleado))) = ?', [$numeroEmpleadoNumerico])
                    ->orderByDesc('Id')
                    ->first(['Turno', 'numero_empleado']);
                if ($telarOperadorNumerico && trim((string) ($telarOperadorNumerico->Turno ?? '')) !== '') {
                    $this->logTurnoDebug('resolver_turno.telar_numerico', [
                        'numero_empleado_input' => $numeroEmpleado,
                        'numero_empleado_numeric' => $numeroEmpleadoNumerico,
                        'numero_empleado_match' => $telarOperadorNumerico->numero_empleado ?? null,
                        'turno' => (string) $telarOperadorNumerico->Turno,
                    ]);
                    return (string) $telarOperadorNumerico->Turno;
                }
            }
        }

        $this->logTurnoDebug('resolver_turno.sin_resultado', [
            'numero_empleado_input' => $numeroEmpleado,
            'priorizar_usuario_autenticado' => $priorizarUsuarioAutenticado,
            'user_idusuario' => $user->idusuario ?? null,
            'user_numero_empleado' => $user->numero_empleado ?? null,
        ]);

        return null;
    }

    private function logTurnoDebug(string $evento, array $context = []): void
    {
        try {
            Log::warning('[TEL-BPM TURNO] ' . $evento, $context);
        } catch (\Throwable $e) {
        }
    }
}
