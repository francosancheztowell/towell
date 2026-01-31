<?php

namespace App\Http\Controllers\Tejedores\BPMTejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Tejedores\TelBpmLineModel;
use App\Models\Tejedores\TelActividadesBPM;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelBpmLineController extends Controller
{
    private const EST_CREADO = 'Creado';
    private const EST_TERM   = 'Terminado';
    private const EST_AUTO   = 'Autorizado';

    /** Vista de edición del checklist por Folio */
    public function index(string $folio)
    {
        $header = TelBpmModel::with('lines')->findOrFail($folio);

        // Catálogo de actividades para mostrar filas (si quieres listar todas)
        $actividades = TelActividadesBPM::orderBy('Orden')->get(['Orden','Actividad'])
                        ->map(fn($a)=>['Orden'=>$a->Orden, 'Actividad'=>$a->Actividad]);

        // Agrupar líneas existentes por (Orden, NoTelarId)
        $lineas = TelBpmLineModel::where('Folio', $folio)
                    ->orderBy('Orden')
                    ->get();

        // Los telares visibles: asociados al usuario que RECIBE (TelTelaresOperador)
        $telares = collect();
        $salonPorTelar = [];
        try {
            $asignados = \App\Models\Tejedores\TelTelaresOperador::query()
                ->where('numero_empleado', (string)$header->CveEmplRec)
                ->get(['NoTelarId','SalonTejidoId']);
            $telares = $asignados->pluck('NoTelarId')->filter()->unique()->values();
            $salonPorTelar = $asignados->mapWithKeys(fn($r)=>[$r->NoTelarId => $r->SalonTejidoId])->all();
        } catch (\Throwable $e) {
            // Fallback a los que existan en líneas
            $telares = $lineas->pluck('NoTelarId')->filter()->unique()->values();
        }

        // Inicializar todas las combinaciones (Actividad x Telar) con Valor NULL si no existen
        try {
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
            // Recargar líneas después de inicializar
            $lineas = TelBpmLineModel::where('Folio', $folio)
                        ->orderBy('Orden')
                        ->get();
        } catch (\Throwable $e) {
            // Si falla la inicialización, continuamos mostrando lo disponible
        }

        // Comentarios ahora en TelBPM.Comentarios (no en TelBPMLine)
        $comentarios = $header->Comentarios ?? '';

        // Determinar si el usuario actual es Supervisor
        $esSupervisor = false;
        try {
            $u = \Illuminate\Support\Facades\Auth::user();
            if ($u) {
                $num = $u->numero_empleado ?? $u->cve ?? null;
                if ($num) {
                    $sysU = SYSUsuario::where('numero_empleado', $num)->first();
                    $puesto = strtolower(trim((string)($sysU->puesto ?? '')));
                    $esSupervisor = ($puesto === 'supervisor');
                }
            }
        } catch (\Throwable $e) {
            $esSupervisor = false;
        }

        return view('modulos.bpm-tejedores.tel-bpm-line.index', [
            'folio'       => $folio,
            'header'      => $header,
            'actividades' => $actividades,
            'lineas'      => $lineas,
            'telares'     => $telares,
            'salonPorTelar' => $salonPorTelar,
            'comentarios' => $comentarios,
            'esSupervisor' => $esSupervisor,
        ]);
    }

    /** Alterna tri-estado de una celda (NULL → OK → X → NULL) */
    public function toggle(Request $request, string $folio)
    {
        $header = TelBpmModel::findOrFail($folio);

        if ($header->Status !== self::EST_CREADO) {
            return response()->json(['ok' => false, 'msg' => 'Edición sólo en estado Creado'], 422);
        }

        $data = $request->validate([
            'Orden'       => ['required','integer','min:1'],
            'NoTelarId'   => ['required','string','max:10'],
            'SalonTejidoId' => ['nullable','string','max:10'],
            'TurnoRecibe' => ['nullable','string','max:10'],
            'Actividad'   => ['nullable','string','max:100'], // opcional (por si el front lo manda)
        ]);

        try {
            // Leer valor actual para esta celda específica
            $curr = DB::table('TelBPMLine')
                ->where('Folio', $folio)
                ->where('Orden', (int)$data['Orden'])
                ->where('NoTelarId', (string)$data['NoTelarId'])
                ->value('Valor');

            $next = $this->nextValor($curr);
            $actividad = $data['Actividad'] ?? TelActividadesBPM::where('Orden', (int)$data['Orden'])->value('Actividad');

            DB::table('TelBPMLine')->updateOrInsert(
                [
                    'Folio'     => $folio,
                    'Orden'     => (int)$data['Orden'],
                    'NoTelarId' => (string)$data['NoTelarId'],
                ],
                [
                    'Actividad'     => $actividad,
                    'SalonTejidoId' => $data['SalonTejidoId'] ?? null,
                    'TurnoRecibe'   => $data['TurnoRecibe'] ?? null,
                    'Valor'         => $next,
                ]
            );

            return response()->json(['ok' => true, 'valor' => $next]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** Guardado en lote (para cuando envíes todo el grid) */
    public function bulkSave(Request $request, string $folio)
    {
        $header = TelBpmModel::findOrFail($folio);

        if ($header->Status !== self::EST_CREADO) {
            return response()->json(['ok' => false, 'msg' => 'Edición sólo en estado Creado'], 422);
        }

        $rows = $request->validate([
            'rows' => ['required','array','min:1'],
            'rows.*.Orden'       => ['required','integer','min:1'],
            'rows.*.NoTelarId'   => ['required','string','max:10'],
            'rows.*.Actividad'   => ['nullable','string','max:100'],
            'rows.*.SalonTejidoId' => ['nullable','string','max:10'],
            'rows.*.TurnoRecibe' => ['nullable','string','max:10'],
            'rows.*.Valor'       => ['nullable','string','max:50'], // 'OK' | 'X' | null
        ])['rows'];

        DB::transaction(function () use ($rows, $folio) {
            foreach ($rows as $r) {
                $orden = (int)($r['Orden'] ?? 0);
                $telar = (string)($r['NoTelarId'] ?? '');
                if ($orden <= 0 || $telar === '') continue;

                $actividad = $r['Actividad'] ?? TelActividadesBPM::where('Orden', $orden)->value('Actividad');
                $valor = $r['Valor'] ?? null;
                if ($valor === '') $valor = null;
                if ($valor !== null && !in_array($valor, ['OK','X'], true)) {
                    if ($valor === '1') $valor = 'OK';
                    elseif ($valor === '-1') $valor = 'X';
                    else $valor = null;
                }

                DB::table('TelBPMLine')->updateOrInsert(
                    [
                        'Folio'     => $folio,
                        'Orden'     => $orden,
                        'NoTelarId' => $telar,
                    ],
                    [
                        'Actividad'     => $actividad,
                        'SalonTejidoId' => $r['SalonTejidoId'] ?? null,
                        'TurnoRecibe'   => $r['TurnoRecibe'] ?? null,
                        'Valor'         => $valor,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Actualizar comentarios del folio (guardados en TelBPM.Comentarios) */
    public function updateComentarios(Request $request, string $folio)
    {
        $header = TelBpmModel::findOrFail($folio);

        if ($header->Status !== self::EST_CREADO) {
            return response()->json(['ok' => false, 'msg' => 'Edición sólo en estado Creado'], 422);
        }

        $data = $request->validate([
            'Comentarios' => ['nullable','string','max:150'],
        ]);

        $valor = $data['Comentarios'] ?? null;
        if ($valor !== null) {
            $valor = trim($valor);
            if ($valor === '') {
                $valor = null;
            }
        }

        try {
            $header->Comentarios = $valor;
            $header->save();

            $msg = $valor === null || $valor === ''
                ? 'Comentarios actualizados.'
                : 'Comentario guardado correctamente.';
            return response()->json(['ok' => true, 'msg' => $msg]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /* ==================== Acciones de Estado ==================== */

    /** Terminar (de Creado → Terminado) */
    public function finish(string $folio)
    {
        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_CREADO) {
            return back()->with('error', 'Sólo puedes terminar un folio en estado Creado.');
        }

        $item->update(['Status' => self::EST_TERM]);
        // Ruta real de navegación
        return redirect()->route('tejedores.bpm')->with('success', 'Folio marcado como Terminado.');
    }

    /** Autorizar (de Terminado → Autorizado) */
    public function authorizeDoc(string $folio)
    {
        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_TERM) {
            return back()->with('error', 'Sólo puedes autorizar un folio Terminado.');
        }

        try {
            [$code, $name] = $this->getSupervisorInfo('autorizar');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $item->update([
            'Status'          => self::EST_AUTO,
            'CveEmplAutoriza' => $code !== null ? (string)$code : '',
            'NomEmplAutoriza' => $name !== null ? (string)$name : '',
        ]);

        // Ruta real de navegación
        return redirect()->route('tejedores.bpm')->with('success', 'Folio Autorizado.');
    }

    /** Rechazar (de Terminado → Creado) */
    public function reject(string $folio)
    {
        $item = TelBpmModel::findOrFail($folio);

        if ($item->Status !== self::EST_TERM) {
            return back()->with('error', 'Sólo puedes rechazar un folio Terminado.');
        }

        try {
            $this->getSupervisorInfo('rechazar');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $item->update([
            'Status'          => self::EST_CREADO,
            'CveEmplAutoriza' => null,
            'NomEmplAutoriza' => null,
        ]);

        // Ruta real de navegación
        return redirect()->route('tejedores.bpm')->with('success', 'Folio regresó a estado Creado.');
    }

    /* ==================== Helpers ==================== */

    private function nextValor($curr): ?string
    {
        // NULL → 'OK' → 'X' → NULL
        if ($curr === null || $curr === '') return 'OK';
        if ($curr === 'OK') return 'X';
        return null;
    }

    private function getSupervisorInfo(string $accion): array
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        if (!$u) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $numeroEmpleado = $u->numero_empleado ?? $u->cve ?? null;
        if (!$numeroEmpleado) {
            throw new \RuntimeException("No se pudo identificar el usuario para validar permisos de {$accion}.");
        }

        $sysUsuario = SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        if (!$sysUsuario || strtolower(trim($sysUsuario->puesto ?? '')) !== 'supervisor') {
            throw new \RuntimeException("No tienes permisos para {$accion}. Solo los supervisores pueden realizar esta acción.");
        }

        $code = $u->cve
            ?? $u->numero_empleado
            ?? $u->idusuario
            ?? $u->id
            ?? null;
        $name = $u->name
            ?? $u->nombre
            ?? $u->Nombre
            ?? null;

        return [$code, $name];
    }
}
