<?php

namespace App\Http\Controllers\mecanicos;

use App\Exports\ReporteEstadoMaquinaExport;
use App\Exports\ReporteOtDiariasExport;
use App\Http\Controllers\Controller;
use App\Services\Mecanicos\ReporteEstadoMaquinaService;
use App\Services\Mecanicos\ReporteEstadoMaquinaTelegramNotifier;
use App\Services\Mecanicos\ReporteOtDiariasService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class MecReportesController extends Controller
{
    private const MODULO_ESTADO_MAQUINA = 'Reporte Estado de Maquina';

    private const MODULO_OT_DIARIAS = 'OT Diarias';

    public function __construct(
        private readonly ReporteEstadoMaquinaService $reporteEstadoMaquina,
        private readonly ReporteEstadoMaquinaTelegramNotifier $telegram,
        private readonly ReporteOtDiariasService $reporteOtDiarias,
    ) {}

    public function otDiarias(Request $request): View
    {
        $this->autorizarOtDiarias();

        $fecha = trim((string) $request->query('fecha', ''));
        $reporte = null;
        $error = null;

        if ($fecha !== '') {
            try {
                $reporte = $this->reporteOtDiarias->build($fecha);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        } else {
            $fecha = $this->reporteOtDiarias->lunesActual();
        }

        return view('modulos.mecanicos.reportes.ot-diarias', [
            'fecha' => $fecha,
            'reporte' => $reporte,
            'error' => $error,
        ]);
    }

    public function estadoMaquina(Request $request): View
    {
        $this->autorizarEstadoMaquina();

        $mes = trim((string) $request->query('mes', ''));
        $semana = trim((string) $request->query('semana', ''));
        $semanas = $mes !== '' ? $this->semanasSeguras($mes) : [];
        $reporte = null;
        $error = null;

        if ($mes !== '' && $semana !== '') {
            try {
                $reporte = $this->reporteEstadoMaquina->build($mes, $semana);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('modulos.mecanicos.reportes.estado-maquina', [
            'mes' => $mes,
            'semana' => $semana,
            'semanas' => $semanas,
            'reporte' => $reporte,
            'error' => $error,
        ]);
    }

    public function semanasEstadoMaquina(Request $request): JsonResponse
    {
        $this->autorizarEstadoMaquina();

        $mes = trim((string) $request->query('mes', ''));
        if ($mes === '') {
            return response()->json(['ok' => false, 'message' => 'Selecciona un mes.'], 422);
        }

        try {
            return response()->json([
                'ok' => true,
                'semanas' => $this->reporteEstadoMaquina->semanasQueTocanMes($mes),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function exportarExcelEstadoMaquina(Request $request): Response
    {
        $this->autorizarEstadoMaquina();
        $reporte = $this->reporteDesdeRequest($request);
        $filename = $this->nombreArchivo($reporte, 'xlsx');
        $contents = Excel::raw(new ReporteEstadoMaquinaExport($reporte), ExcelFormat::XLSX);

        $this->telegram->sendDocument($contents, $filename, $this->caption($reporte, 'Excel'));

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportarPdfEstadoMaquina(Request $request): Response
    {
        $this->autorizarEstadoMaquina();
        $reporte = $this->reporteDesdeRequest($request);
        $filename = $this->nombreArchivo($reporte, 'pdf');
        $contents = $this->pdfBinario($reporte);

        $this->telegram->sendDocument($contents, $filename, $this->caption($reporte, 'PDF'));

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportarExcelOtDiarias(Request $request): Response
    {
        $this->autorizarOtDiarias();
        $reporte = $this->reporteOtDiariasDesdeRequest($request);
        $filename = $this->nombreArchivoOtDiarias($reporte, 'xlsx');
        $contents = Excel::raw(new ReporteOtDiariasExport($reporte), ExcelFormat::XLSX);

        $this->telegram->sendDocument($contents, $filename, $this->captionOtDiarias($reporte, 'Excel'));

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportarPdfOtDiarias(Request $request): Response
    {
        $this->autorizarOtDiarias();
        $reporte = $this->reporteOtDiariasDesdeRequest($request);
        $filename = $this->nombreArchivoOtDiarias($reporte, 'pdf');
        $contents = $this->pdfBinarioOtDiarias($reporte);

        $this->telegram->sendDocument($contents, $filename, $this->captionOtDiarias($reporte, 'PDF'));

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function telegramImagenOtDiarias(Request $request): JsonResponse
    {
        $this->autorizarOtDiarias();

        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        try {
            $reporte = $this->reporteOtDiarias->build($validated['fecha'], $this->inputsOtDiarias($request));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        $imagen = $request->file('imagen');
        $contents = $imagen !== null && $imagen->isValid()
            ? (string) file_get_contents($imagen->getRealPath())
            : '';
        $filename = $this->nombreArchivoOtDiarias($reporte, $imagen?->getClientOriginalExtension() ?: 'png');

        $this->telegram->sendPhoto($contents, $filename, $this->captionOtDiarias($reporte, 'Imagen'));

        return response()->json(['ok' => true]);
    }

    public function telegramImagenEstadoMaquina(Request $request): JsonResponse
    {
        $this->autorizarEstadoMaquina();

        $validated = $request->validate([
            'mes' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'semana' => ['required', 'date_format:Y-m-d'],
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        try {
            $reporte = $this->reporteEstadoMaquina->build($validated['mes'], $validated['semana']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        $imagen = $request->file('imagen');
        $contents = $imagen !== null && $imagen->isValid()
            ? (string) file_get_contents($imagen->getRealPath())
            : '';
        $filename = $this->nombreArchivo($reporte, $imagen?->getClientOriginalExtension() ?: 'png');

        $this->telegram->sendPhoto($contents, $filename, $this->caption($reporte, 'Imagen'));

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reporteDesdeRequest(Request $request): array
    {
        $validated = $request->validate([
            'mes' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'semana' => ['required', 'date_format:Y-m-d'],
            'prioridades' => ['nullable', 'array'],
            'prioridades.*' => ['nullable', 'in:1,2,3'],
        ]);

        try {
            return $this->reporteEstadoMaquina->build(
                $validated['mes'],
                $validated['semana'],
                $validated['prioridades'] ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function pdfBinario(array $reporte): string
    {
        $logoPath = public_path('images/fondosTowell/logo.png');
        $logoBase64 = null;
        if (is_file($logoPath) && is_readable($logoPath)) {
            $logoData = file_get_contents($logoPath);
            if ($logoData !== false && $logoData !== '') {
                $logoBase64 = 'data:image/png;base64,'.base64_encode($logoData);
            }
        }

        $html = view('pdf.mecanicos.estado-maquina', [
            'reporte' => $reporte,
            'logoBase64' => $logoBase64,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a3', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @return list<array{lunes: string, domingo: string, desde: string, hasta: string, etiqueta: string}>
     */
    private function semanasSeguras(string $mes): array
    {
        try {
            return $this->reporteEstadoMaquina->semanasQueTocanMes($mes);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function nombreArchivo(array $reporte, string $extension): string
    {
        return 'hoja-verificacion-estado-maquina_'.$reporte['desde'].'_'.$reporte['hasta'].'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function caption(array $reporte, string $formato): string
    {
        $usuario = Auth::user();
        $caption = "Hoja de verificación estado máquina ({$formato})\n";
        $caption .= 'Periodo: '.$reporte['desde'].' al '.$reporte['hasta'];

        $nombre = $usuario->nombre ?? null;
        if (is_string($nombre) && $nombre !== '') {
            $caption .= "\nGenerado por: {$nombre}";
            $numero = $usuario->numero_empleado ?? null;
            if ($numero !== null && $numero !== '') {
                $caption .= " ({$numero})";
            }
        }

        return $caption;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function pdfBinarioOtDiarias(array $reporte): string
    {
        $logoPath = public_path('images/fondosTowell/logo.png');
        $logoBase64 = null;
        if (is_file($logoPath) && is_readable($logoPath)) {
            $logoData = file_get_contents($logoPath);
            if ($logoData !== false && $logoData !== '') {
                $logoBase64 = 'data:image/png;base64,'.base64_encode($logoData);
            }
        }

        $html = view('pdf.mecanicos.ot-diarias', [
            'reporte' => $reporte,
            'logoBase64' => $logoBase64,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a3', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function reporteOtDiariasDesdeRequest(Request $request): array
    {
        $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'inputs' => ['nullable', 'array'],
            'inputs.*' => ['nullable', 'array'],
            'inputs.*.ot_trama' => ['nullable', 'numeric'],
            'inputs.*.cumplidas_trama' => ['nullable', 'numeric'],
            'inputs.*.ocupacion_pct' => ['nullable', 'numeric'],
        ]);

        try {
            return $this->reporteOtDiarias->build(
                (string) $request->input('fecha'),
                $this->inputsOtDiarias($request),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{ot_trama: float|int|string, cumplidas_trama: float|int|string, ocupacion_pct: float|int|string}>
     */
    private function inputsOtDiarias(Request $request): array
    {
        $raw = $request->input('inputs', []);
        if (! is_array($raw)) {
            return [];
        }

        $limpios = [];
        foreach ($raw as $cve => $campos) {
            if (! is_array($campos)) {
                continue;
            }

            $limpios[(string) $cve] = [
                'ot_trama' => $campos['ot_trama'] ?? 0,
                'cumplidas_trama' => $campos['cumplidas_trama'] ?? 0,
                'ocupacion_pct' => $campos['ocupacion_pct'] ?? 0,
            ];
        }

        return $limpios;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function nombreArchivoOtDiarias(array $reporte, string $extension): string
    {
        return 'ot-diarias_'.$reporte['desde'].'_'.$reporte['hasta'].'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function captionOtDiarias(array $reporte, string $formato): string
    {
        $usuario = Auth::user();
        $caption = "Órdenes de trabajo diarias ({$formato})\n";
        $caption .= 'Periodo: '.$reporte['desde'].' al '.$reporte['hasta'];

        $nombre = $usuario->nombre ?? null;
        if (is_string($nombre) && $nombre !== '') {
            $caption .= "\nGenerado por: {$nombre}";
            $numero = $usuario->numero_empleado ?? null;
            if ($numero !== null && $numero !== '') {
                $caption .= " ({$numero})";
            }
        }

        return $caption;
    }

    private function autorizarOtDiarias(): void
    {
        if (! userCan('acceso', $this->moduloOtDiarias())) {
            abort(403, 'No tienes acceso a este reporte.');
        }
    }

    private function moduloOtDiarias(): string
    {
        $nombre = moduleNameForRoute('mecanicos/reportes/ot-diarias');

        return is_string($nombre) && $nombre !== '' ? $nombre : self::MODULO_OT_DIARIAS;
    }

    private function autorizarEstadoMaquina(): void
    {
        if (function_exists('userCan') && ! userCan('acceso', self::MODULO_ESTADO_MAQUINA)) {
            abort(403, 'No tienes acceso a este reporte.');
        }
    }
}
