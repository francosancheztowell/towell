<?php

namespace App\Http\Controllers;

use App\Models\TelBpmModel;
use App\Models\TelBpmLineModel;
use App\Models\TelActividadesBPM;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelBpmLineController extends Controller
{
    private const EST_CREADO = 'Creado';

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
            $asignados = \App\Models\TelTelaresOperador::query()
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

        // Obtener comentarios existentes
        $comentarios = DB::table('TelBPMLine')
            ->where('Folio', $folio)
            ->where('Orden', 0)
            ->where('NoTelarId', 'COMENT')
            ->value('comentarios') ?? '';

        return view('bpm-tejedores.tel-bpm-line.index', [
            'folio'       => $folio,
            'header'      => $header,
            'actividades' => $actividades,
            'lineas'      => $lineas,
            'telares'     => $telares,
            'salonPorTelar' => $salonPorTelar,
            'comentarios' => $comentarios,
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

    /** Actualizar comentarios del folio */
    public function updateComentarios(Request $request, string $folio)
    {
        $header = TelBpmModel::findOrFail($folio);

        if ($header->Status !== self::EST_CREADO) {
            return response()->json(['ok' => false, 'msg' => 'Edición sólo en estado Creado'], 422);
        }

        $data = $request->validate([
            'Comentarios' => ['nullable','string','max:1000'],
        ]);

        try {
            // Buscar si ya existe un registro de comentarios (usando Orden = 0 como identificador especial)
            DB::table('TelBPMLine')->updateOrInsert(
                [
                    'Folio' => $folio,
                    'Orden' => 0, // Orden especial para comentarios
                    'NoTelarId' => 'COMENT', // Acortado para evitar truncamiento
                    'Actividad' => 'COMENTARIOS', // Acortado
                ],
                [
                    'comentarios' => $data['Comentarios'] ?? null,
                    'TurnoRecibe' => substr((string)$header->TurnoRecibe, 0, 10), // Limitar a 10 caracteres
                    'SalonTejidoId' => null,
                    'Valor' => null,
                ]
            );

            return response()->json(['ok' => true, 'msg' => 'Comentarios guardados']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /* ==================== Helpers ==================== */

    private function nextValor($curr): ?string
    {
        // NULL → 'OK' → 'X' → NULL
        if ($curr === null || $curr === '') return 'OK';
        if ($curr === 'OK') return 'X';
        return null;
    }
}
