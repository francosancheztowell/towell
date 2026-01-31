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

    /** Listado con filtros; último primero */
    public function index(Request $request)
    {
        $this->checkPerm('acceso');

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

        // Prefills para modal crear (estructura de 3 filas)
        $turnoActual = TurnoHelper::getTurnoActual();
        $fechaActual = Carbon::now('America/Mexico_City');
        $user = Auth::user();
        $userCode    = $user ? (string) ($user->cve ?? '') : '';
        $userCodeAlt = $user ? (string) ($user->numero_empleado ?? '') : '';
        $userName    = $user ? (string) ($user->name ?? $user->nombre ?? '') : '';

        // Quien RECIBE: usuario actual en TelTelaresOperador (busca por varias llaves)
        $operadorUsuario = null;
        $telaresUsuario = collect();
        try {
            $codes = collect([$userCode, $userCodeAlt])->filter(fn($v)=>$v!=='' )->unique()->values()->all();
            $operadorUsuario = $this->operadorQuery($codes, $userName)->first();
            $telaresUsuario = $this->operadorQuery($codes, $userName)
                ->pluck('NoTelarId')
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            $operadorUsuario = null;
            $telaresUsuario = collect();
        }
        $usuarioEsOperador = (bool) $operadorUsuario;

        // Opciones para ENTREGAR (select): solo operadores con telar en común y sin duplicados
        try {
            if ($telaresUsuario->isEmpty()) {
                $operadoresEntrega = collect();
            } else {
                $operadoresEntrega = TelTelaresOperador::query()
                    ->whereIn('NoTelarId', $telaresUsuario)
                    ->orderBy('numero_empleado')
                    ->get(['numero_empleado','nombreEmpl','Turno']);

                $operadoresEntrega = $operadoresEntrega
                    ->groupBy('numero_empleado')
                    ->map(fn($group) => $group->first())
                    ->values();
            }
        } catch (\Throwable $e) {
            $operadoresEntrega = collect();
        }

        if (!$usuarioEsOperador && $user) {
            Log::info('BPM Tejedores index: usuario no encontrado como operador (no puede crear folio desde la UI)', [
                'user_id' => $user->id ?? null,
                'user_cve' => $user->cve ?? null,
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

    /** Store: Genera folio, crea header y redirige a líneas */
    public function store(Request $request)
    {
        $user = Auth::user();
        Log::info('BPM Tejedores store: intento de crear folio', [
            'user_id' => $user?->id ?? null,
            'user_cve' => $user->cve ?? $user->numero_empleado ?? null,
            'user_nombre' => $user->name ?? $user->nombre ?? null,
        ]);

        $this->checkPerm('crear');

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
                'user_id' => $user?->id ?? null,
            ]);
            throw $e;
        }

        // Valores por defecto cuando no vienen en el request (usuario autenticado)
        $user = Auth::user();
        $data['CveEmplRec']    = $data['CveEmplRec']    ?? ($user ? (string) ($user->cve ?? $user->numero_empleado ?? '') : '');
        $data['NombreEmplRec'] = $data['NombreEmplRec'] ?? ($user ? (string) ($user->name ?? $user->nombre ?? '') : '');
        $data['TurnoRecibe']   = $data['TurnoRecibe']   ?? (string) request()->get('turno_actual', '1');

        // Permitir múltiples folios activos - se eliminó la restricción anterior

        // Validar: Entrega y Recibe no pueden ser el mismo operador
        if (!empty($data['CveEmplRec']) && !empty($data['CveEmplEnt']) && $data['CveEmplRec'] === $data['CveEmplEnt']) {
            return back()->with('error', 'Entrega y Recibe no pueden ser el mismo operador.');
        }

        // Alinear consecutivo con máximo existente para evitar duplicado de PK
        $folio = null;
        try {
            DB::transaction(function () use (&$folio) {
                // Leer prefijo actual para TelBPM
                $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();
                if (!$row) {
                    // Crear la fila si no existe (evita 500 en ambientes sin el registro)
                    $maxFolio = DB::table('TelBPM')->where('Folio', 'like', 'BT%')->orderBy('Folio', 'desc')->value('Folio');
                    $start = $maxFolio ? (int) substr($maxFolio, strlen('BT')) : 0;
                    SSYSFoliosSecuencia::create(['modulo' => self::FOLIO_KEY, 'prefijo' => 'BT', 'consecutivo' => $start]);
                    $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();
                    if (!$row) {
                        Log::error('BPM Tejedores store: no se pudo crear fila en SSYSFoliosSecuencias para modulo=' . self::FOLIO_KEY);
                        throw new \RuntimeException('No existe configuración de folio para BPM Tejedores en SSYSFoliosSecuencias y no se pudo crear. Verifique la tabla dbo.SSYSFoliosSecuencias.');
                    }
                    Log::info('BPM Tejedores store: se creó automáticamente la configuración de folio en SSYSFoliosSecuencias.');
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
        });

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


        // Inicializar líneas del checklist (todas las combinaciones Actividad x Telar)
        try {
            $header = TelBpmModel::findOrFail($folio);
            // Catálogo de actividades
            $actividades = TelActividadesBPM::orderBy('Orden')->get(['Orden','Actividad'])
                ->map(fn($a)=>['Orden'=>$a->Orden, 'Actividad'=>$a->Actividad]);
            // Telares asignados al usuario que recibe
            $telares = collect();
            $salonPorTelar = [];
            try {
                $asignados = TelTelaresOperador::query()
                    ->where('numero_empleado', (string)$header->CveEmplRec)
                    ->get(['NoTelarId','SalonTejidoId']);
                $telares = $asignados->pluck('NoTelarId')->filter()->unique()->values();
                $salonPorTelar = $asignados->mapWithKeys(fn($r)=>[$r->NoTelarId => $r->SalonTejidoId])->all();
            } catch (\Throwable $e) {
                $telares = collect();
            }
            DB::transaction(function () use ($folio, $header, $actividades, $telares, $salonPorTelar) {
                foreach ($actividades as $a) {
                    $orden = (int)$a['Orden'];
                    $actividad = (string)$a['Actividad'];
                    foreach ($telares as $t) {
                        $exists = DB::table('TelBPMLine')
                            ->where('Folio', $folio)
                            ->where('Orden', $orden)
                            ->where('NoTelarId', (string)$t)
                            ->exists();
                        if (!$exists) {
                            DB::table('TelBPMLine')->insert([
                                'Folio'         => $folio,
                                'Orden'         => $orden,
                                'NoTelarId'     => (string)$t,
                                'Actividad'     => $actividad,
                                'SalonTejidoId' => $salonPorTelar[$t] ?? null,
                                'TurnoRecibe'   => (string)$header->TurnoRecibe,
                                'Valor'         => null,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            // Si falla la inicialización, continuar
        }

        Log::info('BPM Tejedores store: folio creado correctamente', ['folio' => $folio, 'user_id' => $user?->id ?? null]);

        return redirect()
            ->route('tel-bpm-line.index', $folio)
            ->with('success', "Folio $folio creado. Completa el checklist.");
        } catch (\Throwable $e) {
            Log::error('BPM Tejedores store: error al crear folio', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $user?->id ?? null,
            ]);
            throw $e;
        }
    }

    /** Editar header (sólo en estado 'Creado') */
    public function update(Request $request, string $folio)
    {
        $this->checkPerm('modificar');

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
        $this->checkPerm('eliminar');

        $item = TelBpmModel::findOrFail($folio);
        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', "No se puede eliminar el folio {$folio} en estado \"{$item->Status}\". Solo se pueden eliminar folios en estado \"Creado\".");
        }

        $item->delete(); // ON DELETE CASCADE eliminará sus líneas
        // Ruta real de navegación
        return redirect()->route('tejedores.bpm')->with('success', "Folio $folio eliminado.");
    }

    /* ===================== Helpers ===================== */

    /** Verifica permisos con userCan (action, module). Si el helper no existe, deja pasar. */
    private function checkPerm(string $accion): void
    {
        try {
            if (function_exists('userCan') && !userCan($accion, 'BPM')) {
                $user = Auth::user();
                Log::warning('BPM Tejedores: permiso denegado', [
                    'accion' => $accion,
                    'modulo' => 'BPM',
                    'user_id' => $user?->id ?? null,
                    'user_cve' => $user->cve ?? $user->numero_empleado ?? null,
                ]);
                abort(403);
            }
        } catch (\Throwable $e) {
            Log::debug('Perm helper no disponible: '.$e->getMessage());
        }
    }

    private function operadorQuery(array $codes, string $userName)
    {
        return TelTelaresOperador::query()
            ->when(!empty($codes), fn($q) => $q->whereIn('numero_empleado', $codes))
            ->when(empty($codes) && $userName !== '', fn($q)=> $q->where('nombreEmpl', 'like', "%{$userName}%"));
    }
}
