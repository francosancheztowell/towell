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

        // Los telar(es) visibles en columnas los define tu Blade (puede leer de las líneas existentes).
        $telares = $lineas->pluck('NoTelarId')->filter()->unique()->values();

        return view('bpm-tejedores.tel-bpm-line.index', [
            'folio'       => $folio,
            'header'      => $header,
            'actividades' => $actividades,
            'lineas'      => $lineas,
            'telares'     => $telares,
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

        $line = TelBpmLineModel::firstOrNew([
            'Folio'     => $folio,
            'Orden'     => $data['Orden'],
            'NoTelarId' => $data['NoTelarId'],
        ]);

        // set defaults/overrides
        if (!empty($data['Actividad']))     $line->Actividad     = $data['Actividad'];
        if (!empty($data['SalonTejidoId'])) $line->SalonTejidoId = $data['SalonTejidoId'];
        if (!empty($data['TurnoRecibe']))   $line->TurnoRecibe   = $data['TurnoRecibe'];

        // Tri-estado
        $line->Valor = $this->nextValor($line->Valor);
        $line->save();

        return response()->json(['ok' => true, 'valor' => $line->Valor]);
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
                $line = TelBpmLineModel::firstOrNew([
                    'Folio'     => $folio,
                    'Orden'     => (int)$r['Orden'],
                    'NoTelarId' => $r['NoTelarId'],
                ]);

                foreach (['Actividad','SalonTejidoId','TurnoRecibe','Valor'] as $f) {
                    if (array_key_exists($f, $r)) $line->{$f} = $r[$f];
                }

                // Normaliza valores
                if ($line->Valor === '') $line->Valor = null;
                if ($line->Valor !== null && !in_array($line->Valor, ['OK','X'], true)) {
                    // opcional: mapea 1/0/-1 a OK/X/null
                    if ($line->Valor === '1') $line->Valor = 'OK';
                    elseif ($line->Valor === '-1') $line->Valor = 'X';
                    else $line->Valor = null;
                }

                $line->save();
            }
        });

        return response()->json(['ok' => true]);
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
