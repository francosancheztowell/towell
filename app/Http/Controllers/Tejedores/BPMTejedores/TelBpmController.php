<?php

namespace App\Http\Controllers\Tejedores\BPMTejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
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
        $perPage = (int) $request->get('per_page', 1000); // Cargar más datos para filtrar en cliente

        $items = TelBpmModel::query()
            ->select('TelBPM.*')
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
            ->paginate($perPage)
            ->withQueryString();

        // Prefills para modal crear
        $turnoActual = TurnoHelper::getTurnoActual();
        $fechaActual = Carbon::now('America/Mexico_City');
        $user = Auth::user();
        
        // Obtener datos del operador y operadores de entrega
        [$operadorUsuario, $usuarioEsOperador, $operadoresEntrega] = $this->obtenerDatosOperador($user);

        Log::info('BPM Tejedores index: listado cargado', [
            'user_id' => $user?->getAuthIdentifier(),
            'user_cve' => $user->numero_empleado ?? $user->cve ?? null,
            'usuarioEsOperador' => $usuarioEsOperador,
            'items_count' => $items->total(),
        ]);
        
        if (!$usuarioEsOperador && $user) {
            Log::info('BPM Tejedores index: usuario no encontrado como operador', [
                'user_id' => $user->getAuthIdentifier(),
                'user_cve' => $user->numero_empleado ?? $user->cve ?? null,
                'user_numero_empleado' => $user->numero_empleado ?? null,
                'user_nombre' => $user->name ?? $user->nombre ?? null,
            ]);
        }

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

    /** Log de depuración desde el cliente (pasos del flujo crear/modal). Revisar storage/logs/laravel.log */
    public function logDebug(Request $request)
    {
        $step = $request->input('step', '');
        $msg = $request->input('msg', '');
        Log::info('BPM Tejedores [cliente]', [
            'step' => $step,
            'msg' => $msg,
            'user_id' => Auth::id(),
        ]);
        return response('', 204);
    }

    /** Store: Genera folio, crea header y redirige a líneas */
    public function store(Request $request)
    {
        Log::info('BPM Tejedores store: petición recibida', [
            'user_id' => Auth::id(),
            'has_input' => $request->hasAny(['CveEmplRec', 'CveEmplEnt']),
        ]);
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
            Log::warning('BPM Tejedores store: validación fallida', [
                'errors' => $e->errors(),
                'user_id' => $user->getAuthIdentifier(),
            ]);
            throw $e;
        }

        try {
            // Valores por defecto cuando no vienen en el request (usuario autenticado)
            $data['CveEmplRec']    = $data['CveEmplRec']    ?? (string) ($user->cve ?? $user->numero_empleado ?? '');
            $data['NombreEmplRec'] = $data['NombreEmplRec'] ?? (string) ($user->name ?? $user->nombre ?? '');
            $data['TurnoRecibe']   = $data['TurnoRecibe']   ?? (string) request()->get('turno_actual', '1');

            // Validar: Entrega y Recibe no pueden ser el mismo operador
            if (!empty($data['CveEmplRec']) && !empty($data['CveEmplEnt']) && $data['CveEmplRec'] === $data['CveEmplEnt']) {
                return back()->with('error', 'Entrega y Recibe no pueden ser el mismo operador.');
            }

            // Generar folio
            $folio = $this->generarFolio();

            // Crear header
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

            // Inicializar líneas del checklist
            $this->inicializarLineasChecklist($folio, $data['CveEmplRec'], $data['TurnoRecibe']);

            Log::info('BPM Tejedores store: folio creado correctamente', ['folio' => $folio, 'user_id' => $user->getAuthIdentifier()]);

            return redirect()
                ->route('tel-bpm-line.index', $folio)
                ->with('success', "Folio $folio creado. Completa el checklist.");
        } catch (\Throwable $e) {
            Log::error('BPM Tejedores store: error al crear folio', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $user->getAuthIdentifier(),
            ]);
            throw $e;
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
            // Leer prefijo actual para TelBPM
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();
            
            if (!$row) {
                // Crear la fila si no existe
                $maxFolio = DB::table('TelBPM')->where('Folio', 'like', 'BT%')->orderBy('Folio', 'desc')->value('Folio');
                $start = $maxFolio ? (int) substr($maxFolio, strlen('BT')) : 0;
                SSYSFoliosSecuencia::create(['modulo' => self::FOLIO_KEY, 'prefijo' => 'BT', 'consecutivo' => $start]);
                $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();
                
                if (!$row) {
                    Log::error('BPM Tejedores: no se pudo crear fila en SSYSFoliosSecuencias para modulo=' . self::FOLIO_KEY);
                    throw new \RuntimeException('No existe configuración de folio para BPM Tejedores en SSYSFoliosSecuencias y no se pudo crear.');
                }
                Log::info('BPM Tejedores: se creó automáticamente la configuración de folio en SSYSFoliosSecuencias.');
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

            // Generar siguiente folio usando helper
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
            $operadorUsuario = $this->operadorQuery($codes, $userName)->first();
            $usuarioEsOperador = (bool) $operadorUsuario;
            
            if ($usuarioEsOperador) {
                $telaresUsuario = $this->operadorQuery($codes, $userName)
                    ->pluck('NoTelarId')
                    ->filter()
                    ->unique()
                    ->values();
                
                if ($telaresUsuario->isNotEmpty()) {
                    $operadoresEntrega = TelTelaresOperador::query()
                        ->whereIn('NoTelarId', $telaresUsuario)
                        ->orderBy('numero_empleado')
                        ->get(['numero_empleado', 'nombreEmpl', 'Turno'])
                        ->groupBy('numero_empleado')
                        ->map(fn($group) => $group->first())
                        ->values();
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Error al obtener datos del operador: ' . $e->getMessage());
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
            
            if ($actividades->isEmpty() || $telares->isEmpty()) {
                return;
            }
            
            DB::transaction(function () use ($folio, $actividades, $telares, $salonPorTelar, $turnoRecibe) {
                foreach ($actividades as $actividad) {
                    foreach ($telares as $telar) {
                        $exists = DB::table('TelBPMLine')
                            ->where('Folio', $folio)
                            ->where('Orden', $actividad->Orden)
                            ->where('NoTelarId', (string)$telar)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('TelBPMLine')->insert([
                                'Folio' => $folio,
                                'Orden' => (int)$actividad->Orden,
                                'NoTelarId' => (string)$telar,
                                'Actividad' => (string)$actividad->Actividad,
                                'SalonTejidoId' => $salonPorTelar[$telar] ?? null,
                                'TurnoRecibe' => (string)$turnoRecibe,
                                'Valor' => null,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('BPM Tejedores: error al inicializar líneas del checklist', [
                'folio' => $folio,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function operadorQuery(array $codes, string $userName)
    {
        return TelTelaresOperador::query()
            ->when(!empty($codes), fn($q) => $q->whereIn('numero_empleado', $codes))
            ->when(empty($codes) && $userName !== '', fn($q) => $q->where('nombreEmpl', 'like', "%{$userName}%"));
    }
}
