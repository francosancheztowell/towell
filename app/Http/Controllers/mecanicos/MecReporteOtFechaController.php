<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos;

use App\Exports\ReporteOtFechaExport;
use App\Http\Controllers\Controller;
use App\Services\Mecanicos\ReporteEstadoMaquinaTelegramNotifier;
use App\Services\Mecanicos\ReporteOtFechaService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte "OT x Fecha" (SYSRoles idrol 209). El permiso de acceso lo aplica la ruta
 * (module.permission:acceso,209). Excel, PDF e imagen también se mandan por Telegram,
 * como OT Diarias y Estado de Máquina.
 */
class MecReporteOtFechaController extends Controller
{
    public function __construct(
        private readonly ReporteOtFechaService $reporte,
        private readonly ReporteEstadoMaquinaTelegramNotifier $telegram,
    ) {}

    public function index(Request $request): View
    {
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));
        $mecanico = trim((string) $request->query('mecanico', ''));
        [$reporte, $error] = [null, null];

        if ($desde !== '' || $hasta !== '') {
            [$reporte, $error] = $this->reporte->intentar($desde, $hasta, $mecanico);
        } else {
            $desde = $hasta = $this->reporte->hoy();
        }

        return view('modulos.mecanicos.reportes.ot-fecha', [
            'desde' => $desde,
            'hasta' => $hasta,
            'mecanico' => $mecanico,
            'mecanicos' => $this->reporte->catalogoMecanicos(),
            'reporte' => $reporte,
            'error' => $error,
        ]);
    }

    public function exportarExcel(Request $request): Response
    {
        $reporte = $this->reporteDesdeRequest($request);
        $filename = $this->nombreArchivo($reporte, 'xlsx');
        $contents = Excel::raw(new ReporteOtFechaExport($reporte), ExcelFormat::XLSX);

        return $this->telegram->sendDocumentYDescargar($contents, $filename, $this->caption($reporte, 'Excel'));
    }

    public function exportarPdf(Request $request): Response
    {
        $reporte = $this->reporteDesdeRequest($request);
        $filename = $this->nombreArchivo($reporte, 'pdf');
        $contents = $this->pdf($reporte);

        return $this->telegram->sendDocumentYDescargar($contents, $filename, $this->caption($reporte, 'PDF'));
    }

    public function telegramImagen(Request $request): JsonResponse
    {
        $request->validate([
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);
        $reporte = $this->reporteDesdeRequest($request);

        return $this->telegram->sendUploadedPhoto($request->file('imagen'), fn (string $ext): string => $this->nombreArchivo($reporte, $ext), $this->caption($reporte, 'Imagen'));
    }

    /**
     * @return array<string, mixed>
     */
    private function reporteDesdeRequest(Request $request): array
    {
        $validated = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d'],
            'mecanico' => ['nullable', 'string', 'max:30'],
        ]);

        [$reporte, $error] = $this->reporte->intentar($validated['desde'], $validated['hasta'], (string) ($validated['mecanico'] ?? ''));
        abort_if($reporte === null, 422, (string) $error);

        return $reporte;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function pdf(array $reporte): string
    {
        $logo = public_path('images/fondosTowell/logo.png');
        $datosLogo = is_readable($logo) ? (string) file_get_contents($logo) : '';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.mecanicos.ot-fecha', [
            'reporte' => $reporte,
            'logoBase64' => $datosLogo !== '' ? 'data:image/png;base64,'.base64_encode($datosLogo) : null,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function nombreArchivo(array $reporte, string $extension): string
    {
        return 'ot-fecha_'.$reporte['desde'].'_'.$reporte['hasta'].'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function caption(array $reporte, string $formato): string
    {
        $mecanico = $reporte['mecanico_nombre'] !== '' ? $reporte['mecanico_nombre'] : 'Todos los mecánicos';
        $caption = "Órdenes de trabajo por fecha ({$formato})\n";
        $caption .= 'Periodo: '.$reporte['desde'].' al '.$reporte['hasta']."\nMecánico: {$mecanico}";

        $usuario = Auth::user();
        $nombre = $usuario->nombre ?? null;
        if (is_string($nombre) && $nombre !== '') {
            $numero = $usuario->numero_empleado ?? '';
            $caption .= "\nGenerado por: {$nombre}".($numero !== '' ? " ({$numero})" : '');
        }

        return $caption;
    }
}
