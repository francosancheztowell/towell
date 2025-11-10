<?php

namespace App\Http\Controllers;

use App\Models\TelBpmModel;
use App\Models\TelBpmLineModel;
use App\Models\TelActividadesBPM;
use App\Models\SSYSFoliosSecuencia;
use App\Models\TelTelaresOperador;
use App\Helpers\TurnoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TelBpmController extends Controller
{
    /** Clave para SSYSFoliosConsecutivos */
    private const FOLIO_KEY   = 'TelBPM'; // ajusta si tu tabla usa otra columna/filtro
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
        $perPage = (int) $request->get('per_page', 15);

        $items = TelBpmModel::query()
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where('Folio', 'like', "%{$q}%")
                    ->orWhere('CveEmplRec', 'like', "%{$q}%")
                    ->orWhere('NombreEmplRec', 'like', "%{$q}%")
                    ->orWhere('CveEmplEnt', 'like', "%{$q}%")
                    ->orWhere('NombreEmplEnt', 'like', "%{$q}%");
            })
            ->when($status, fn($qry) => $qry->where('Status', $status))
            ->orderByDesc('Fecha') // último primero
            ->orderByDesc('Folio')
            ->paginate($perPage)
            ->withQueryString();

        // Prefills para modal crear (estructura de 3 filas)
        $turnoActual = TurnoHelper::getTurnoActual();
        $fechaActual = Carbon::now('America/Mexico_City');
        $user = auth()->user();
        $userCode    = (string) ($user->cve ?? '');
        $userCodeAlt = (string) ($user->numero_empleado ?? '');
        $userName    = (string) ($user->name ?? $user->nombre ?? '');

        // Quien RECIBE: usuario actual en TelTelaresOperador (busca por varias llaves)
        $operadorUsuario = null;
        try {
            $codes = collect([$userCode, $userCodeAlt])->filter(fn($v)=>$v!=='' )->unique()->all();
            $operadorUsuario = TelTelaresOperador::query()
                ->when(!empty($codes), fn($q) => $q->whereIn('numero_empleado', $codes))
                ->when(empty($codes) && $userName !== '', fn($q)=> $q->where('nombreEmpl', 'like', "%{$userName}%"))
                ->first();
        } catch (\Throwable $e) {
            $operadorUsuario = null;
        }
        $usuarioEsOperador = (bool) $operadorUsuario;

        // Opciones para ENTREGAR (select)
        try {
            $operadoresEntrega = TelTelaresOperador::orderBy('numero_empleado')
                ->get(['numero_empleado','nombreEmpl','Turno']);
        } catch (\Throwable $e) {
            $operadoresEntrega = collect();
        }

        return view('bpm-tejedores.tel-bpm.index', [
            'items'   => $items,
            'q'       => $q,
            'status'  => $status,
            'turnoActual' => $turnoActual,
            'fechaActual' => $fechaActual,
            'turnoActual' => $turnoActual,
            'operadorUsuario' => $operadorUsuario,
            'usuarioEsOperador' => $usuarioEsOperador,
            'operadoresEntrega' => $operadoresEntrega,
        ]);
    }

    /** Store: Genera folio, crea header y redirige a líneas */
    public function store(Request $request)
    {
        $this->checkPerm('crear');

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

        // Defaults automáticos si no vienen en request (ajusta con tus helpers de usuario)
        // Valores por defecto seguros cuando no vienen en el request
        $data['CveEmplRec']    = $data['CveEmplRec']    ?? ((string) (auth()->user()->cve ?? null));
        $data['NombreEmplRec'] = $data['NombreEmplRec'] ?? ((string) (auth()->user()->name ?? null));
        $data['TurnoRecibe']   = $data['TurnoRecibe']   ?? ((string) request()->get('turno_actual', '1'));

        // Regla: sólo un folio activo a la vez (Creado o Terminado)
        $activo = TelBpmModel::whereIn('Status', [self::EST_CREADO, self::EST_TERM])->exists();
        if ($activo) {
            return back()->with('error', 'No se puede crear un nuevo folio: ya existe un registro activo.');
        }

        // Validar: Entrega y Recibe no pueden ser el mismo operador
        if (!empty($data['CveEmplRec']) && !empty($data['CveEmplEnt']) && $data['CveEmplRec'] === $data['CveEmplEnt']) {
            return back()->with('error', 'Entrega y Recibe no pueden ser el mismo operador.');
        }

        // Alinear consecutivo con máximo existente para evitar duplicado de PK
        $folio = null;
        DB::transaction(function () use (&$folio) {
            // Leer prefijo actual para TelBPM
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', self::FOLIO_KEY)->lockForUpdate()->first();
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

        // (Opcional) Inicializar catálogo de actividades como “plantilla”
        // No creo líneas aquí, se crearán on-demand al ir marcando (toggle/bulk).
        // Si quieres pre-generarlas, dímelo y lo agregamos.

        return redirect()
            ->route('tel-bpm-line.index', $folio)
            ->with('success', "Folio $folio creado. Completa el checklist.");
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

    /** Eliminar (sólo ‘Creado’) */
    public function destroy(string $folio)
    {
        $this->checkPerm('eliminar');

        $item = TelBpmModel::findOrFail($folio);
        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', 'No se puede eliminar si no está en estado Creado.');
        }

        $item->delete(); // ON DELETE CASCADE eliminará sus líneas
        return redirect()->route('tel-bpm.index')->with('success', "Folio $folio eliminado.");
    }

    /** Terminar (de Creado → Terminado) */
    public function finish(string $folio)
    {
        $this->checkPerm('registrar'); // o el permiso que uses para “terminar”

        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', 'Sólo puedes terminar un folio en estado Creado.');
        }

        $item->update(['Status' => self::EST_TERM]);
        return back()->with('success', 'Folio marcado como Terminado.');
    }

    /** Autorizar (de Terminado → Autorizado) */
    public function authorizeDoc(string $folio)
    {
        $this->checkPerm('registrar'); // o permiso “autorizar”

        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_TERM) {
            return back()->with('error', 'Sólo puedes autorizar un folio Terminado.');
        }

        // Tomar datos del usuario actual de forma robusta
        $u = auth()->user();
        $code = null;
        $name = null;
        if ($u) {
            // Posibles campos usados en diferentes módulos
            $code = $u->cve
                ?? $u->numero_empleado
                ?? $u->idusuario
                ?? $u->id
                ?? null;
            $name = $u->name
                ?? $u->nombre
                ?? $u->Nombre
                ?? null;
        }

        $item->update([
            'Status'          => self::EST_AUTO,
            'CveEmplAutoriza' => $code !== null ? (string)$code : '',
            'NomEmplAutoriza' => $name !== null ? (string)$name : '',
        ]);

        return back()->with('success', 'Folio Autorizado.');
    }

    /** Rechazar (de Terminado → Creado) */
    public function reject(string $folio)
    {
        $this->checkPerm('registrar'); // o permiso “rechazar”

        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_TERM) {
            return back()->with('error', 'Sólo puedes rechazar un folio Terminado.');
        }

        $item->update([
            'Status'          => self::EST_CREADO,
            'CveEmplAutoriza' => null,
            'NomEmplAutoriza' => null,
        ]);

        return back()->with('success', 'Folio regresó a estado Creado.');
    }

    /* ===================== Helpers ===================== */

    /** Verifica permisos con tu helper; si no existe, deja pasar (para desarrollo) */
    private function checkPerm(string $accion): void
    {
        // TODO: reemplaza por tu helper real de permisos
        // Ejemplo: if (!\Permission::can('BPM', $accion)) abort(403);
        try {
            if (function_exists('permission_can')) {
                if (!permission_can('BPM', $accion)) abort(403);
            }
        } catch (\Throwable $e) {
            // en dev, no bloqueamos si el helper no está disponible
            Log::debug('Perm helper no disponible: '.$e->getMessage());
        }
    }
}
