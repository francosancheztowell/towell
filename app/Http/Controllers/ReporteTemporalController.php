<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReporteTemporal;
use Illuminate\Support\Facades\DB;

class ReporteTemporalController extends Controller
{
    protected function obtenerTurnoActual()
    {
        $ahora = \Carbon\Carbon::now('America/Mexico_City');
        $hora = (int) $ahora->format('H');
        $minuto = (int) $ahora->format('i');
        $totalMinutos = $hora * 60 + $minuto;

        if ($totalMinutos >= 390 && $totalMinutos <= 869) {
            return 1; // 06:30 - 14:29
        } elseif ($totalMinutos >= 870 && $totalMinutos <= 1349) {
            return 2; // 14:30 - 22:29
        } else {
            return 3; // 22:30 - 06:29
        }
        //$turnoActual = $this->obtenerTurnoActual(); ASI LO LLAMARÉ
    }

    private static function normalizarTelefonoMx(?string $raw): ?string
    {
        if (!$raw) return null;
        // 1) Dejar solo dígitos y + inicial (si existe)
        $canon = preg_replace('/[^\d+]/', '', $raw);

        // 2) Quitar '+' para evaluar
        $digits = ltrim($canon, '+');

        // Casos:
        // - 10 dígitos (local MX): agregar +52
        if (preg_match('/^\d{10}$/', $digits)) {
            return '+52' . $digits;
        }

        // - 12 dígitos y empieza con 52: agregar '+'
        if (preg_match('/^52\d{10}$/', $digits)) {
            return '+' . $digits;
        }

        // - Ya viene como +52XXXXXXXXXX
        if (preg_match('/^\+52\d{10}$/', $canon)) {
            return $canon;
        }

        // Acepta también 7–15 dígitos internacionales genéricos con '+'
        if (preg_match('/^\+\d{7,15}$/', $canon)) {
            return $canon;
        }

        // No válido
        return null;
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'telar' => 'required|integer',
            'tipo' => 'required|string',
            'clave_falla' => 'required|string',
            'descripcion' => 'nullable|string',
            'fecha_reporte' => 'required|date',
            'hora_reporte' => 'required',
            'operador' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $reporte = ReporteTemporal::create($data);

        return response()->json([
            'success' => true,
            'id' => $reporte->id
        ]);
    }

    public function index()
    {
        $reportes = \App\Models\ReporteTemporal::orderBy('created_at', 'desc')->get();
        return view('modulos.mantenimiento', compact('reportes'));
    }

    //guardar en BD (reportes_temporales) -> TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM TELEGRAM
    public function guardar(Request $request)
    {
        $data = $request->validate([
            'telar'         => 'required|string|max:50',
            'tipo'          => 'required|string|max:50',
            'clave_falla'   => 'nullable|string|max:50',
            'descripcion'   => 'required|string|max:1000',
            'fecha_reporte' => 'required|date_format:Y-m-d',
            'hora_reporte'  => 'required|date_format:H:i',
            'operador'      => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $turno = $this->obtenerTurnoActual();

        // 1) Traer teléfonos del turno y con enviarMensaje = 1
        $telefonosRaw = DB::table('SYSUsuario')
            ->where('turno', $turno)
            ->where('enviarMensaje', 1)
            ->pluck('telefono')
            ->all();

        // 2) Normalizar a E.164 MX (+52XXXXXXXXXX) y deduplicar
        $telefonos = collect($telefonosRaw)
            ->map(fn($t) => self::normalizarTelefonoMx($t))
            ->filter()        // quita null/invalid
            ->unique()        // dedup
            ->values()
            ->all();

        // 3) Guardar destinos en el reporte (separados por ;)
        $reporte = ReporteTemporal::create($data + [
            'enviado_telegram'   => false,
            'telefonos_destino'  => implode(';', $telefonos),
        ]);

        return redirect()
            ->back()
            ->with('ok', "Reporte #{$reporte->id} capturado. En breve se enviará por Telegram.");
    }
}
