<?php

namespace App\Http\Controllers\Atadores\Reportes;

use App\Exports\ProgramaAtadoresExport;
use App\Exports\Reporte00EAtadoresRangoExport;
use App\Http\Controllers\Controller;
use App\Services\OeeAtadores\OeeAtadoresFileService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class ReportesAtadoresController extends Controller
{
    /**
     * Selector de reportes: muestra los reportes disponibles
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Reporte de Programa Atadores',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('atadores.reportes.programa'),
                'disponible' => true,
            ],
            [
                'nombre' => 'OEE Atadores',
                'accion' => 'Seleccionar Fechas',
                'url' => route('atadores.reportes.atadores'),
                'disponible' => true,
            ],
        ];

        return view('modulos.atadores.reportes.index', ['reportes' => $reportes]);
    }

    /**
     * Vista del reporte de Programa Atadores con filtros de fecha
     */
    public function reportePrograma(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.atadores.reportes.programa', [
                'fechaIni' => null,
                'fechaFin' => null,
                'datos' => collect(),
            ]);
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        return view('modulos.atadores.reportes.programa', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
        ]);
    }

    /**
     * Exportar Excel del Programa Atadores
     */
    public function exportarExcel(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio') ?? $request->query('fecha_ini');
        $fechaFin = $request->input('fecha_fin') ?? $request->query('fecha_fin');

        if (! $fechaInicio || ! $fechaFin) {
            return redirect()->back()->with('error', 'Debe seleccionar fecha inicio y fecha fin para exportar.');
        }

        $fechaInicioFormateada = Carbon::parse($fechaInicio)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        if ($fechaInicioFormateada > $fechaFinFormateada) {
            return redirect()->back()->with('error', 'La fecha inicio no puede ser mayor que la fecha fin.');
        }

        $nombreArchivo = 'atadores_'.Carbon::parse($fechaInicioFormateada)->format('d-m-Y').'_a_'.Carbon::parse($fechaFinFormateada)->format('d-m-Y').'.xlsx';

        return Excel::download(
            new ProgramaAtadoresExport($fechaInicioFormateada, $fechaFinFormateada),
            $nombreArchivo
        );
    }

    public function reporteAtadores(Request $request)
    {
        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);
        $domingoFin = $lunesFin?->addDays(6);

        return view('modulos.atadores.reportes.atadores', [
            'fechaIni' => $fechaInicio?->toDateString(),
            'fechaFin' => $fechaFin?->toDateString(),
            'lunesIni' => $lunesInicio?->toDateString(),
            'domingoFin' => $domingoFin?->toDateString(),
        ]);
    }

    public function exportarReporteAtadoresExcel(Request $request)
    {
        @set_time_limit(300);

        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (! $fechaInicio || ! $fechaFin || ! $lunesInicio || ! $lunesFin) {
            return redirect()
                ->route('atadores.reportes.atadores')
                ->with('error', 'Debe seleccionar una fecha inicial y final validas para exportar el OEE Atadores.');
        }

        if ($fechaInicio->year !== $fechaFin->year) {
            return redirect()
                ->route('atadores.reportes.atadores', [
                    'fecha_ini' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                ])
                ->with('error', 'Para guardar el archivo anual del OEE Atadores, selecciona un rango dentro del mismo año.');
        }

        [$inicioAnual, $finAnual, $year] = $this->resolverRangoAnualAtadores($fechaFin);
        $nombreArchivo = "OEE Atadores {$year}.xlsx";

        try {
            $rutaGuardado = $this->guardarReporteAtadoresEnRuta(
                new Reporte00EAtadoresRangoExport($inicioAnual, $finAnual),
                $nombreArchivo
            );
        } catch (\Throwable $e) {
            Log::error('No se pudo guardar el reporte anual OEE Atadores', [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('atadores.reportes.atadores', [
                    'fecha_ini' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                ])
                ->with('error', 'No se pudo guardar el archivo anual OEE Atadores. '.$e->getMessage());
        }

        return redirect()
            ->route('atadores.reportes.atadores', [
                'fecha_ini' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
            ])
            ->with('success', "Archivo anual actualizado correctamente en {$rutaGuardado}");
    }

    /**
     * GET /atadores/reportes-atadores/oee/verificar
     * Verifica qué semanas del rango ya tienen datos en OEE_ATADORES.xlsx.
     */
    public function verificarOeeAtadores(Request $request): JsonResponse
    {
        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (! $lunesInicio || ! $lunesFin) {
            return response()->json(['error' => 'Rango de fechas inválido.'], 422);
        }

        $filePath = env('OEE_ATADORES_FILE_PATH', 'C:\\Users\\fsanchez\\Desktop\\OEE_ATADORES.xlsx');

        try {
            $service = new OeeAtadoresFileService($filePath);
            $resultado = $service->verificarSemanasConDatos($lunesInicio, $lunesFin);

            return response()->json($resultado);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /atadores/reportes-atadores/oee/exportar
     * Actualiza el archivo OEE_ATADORES.xlsx con las semanas del rango.
     */
    public function descargarExcelRango(Request $request)
    {
        @set_time_limit(300);

        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (! $fechaInicio || ! $fechaFin || ! $lunesInicio || ! $lunesFin) {
            return redirect()
                ->route('atadores.reportes.atadores')
                ->with('error', 'Debe seleccionar una fecha inicial y final válidas para exportar.');
        }

        $nombreArchivo = 'OEE_Atadores_'
            .$lunesInicio->format('d-m-Y')
            .'_al_'
            .$lunesFin->addDays(6)->format('d-m-Y')
            .'.xlsx';

        return Excel::download(
            new Reporte00EAtadoresRangoExport($lunesInicio, $lunesFin),
            $nombreArchivo
        );
    }

    public function exportarOeeAtadores(Request $request)
    {
        @set_time_limit(600);

        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (! $lunesInicio || ! $lunesFin) {
            return redirect()
                ->route('atadores.reportes.atadores')
                ->with('error', 'Debe seleccionar una fecha inicial y final válidas para exportar a OEE.');
        }

        $filePath = env('OEE_ATADORES_FILE_PATH', 'C:\\Users\\fsanchez\\Desktop\\OEE_ATADORES.xlsx');

        try {
            $service = new OeeAtadoresFileService($filePath);
            $rutaGuardado = $service->actualizarArchivo($lunesInicio, $lunesFin);

            Log::info('OEE Atadores actualizado', [
                'ruta' => $rutaGuardado,
                'desde' => $lunesInicio->toDateString(),
                'hasta' => $lunesFin->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar OEE Atadores', [
                'error' => $e->getMessage(),
                'desde' => $lunesInicio->toDateString(),
                'hasta' => $lunesFin->toDateString(),
            ]);

            return redirect()
                ->route('atadores.reportes.atadores', [
                    'fecha_ini' => $fechaInicio?->toDateString(),
                    'fecha_fin' => $fechaFin?->toDateString(),
                ])
                ->with('error', 'No se pudo actualizar el archivo OEE: '.$e->getMessage());
        }

        return redirect()
            ->route('atadores.reportes.atadores', [
                'fecha_ini' => $fechaInicio?->toDateString(),
                'fecha_fin' => $fechaFin?->toDateString(),
            ])
            ->with('success', 'Archivo OEE actualizado correctamente.');
    }

    private function resolverRangoFechasAtadoresDesdeRequest(Request $request): array
    {
        $fechaIni = trim((string) $request->query('fecha_ini', $request->input('fecha_ini', '')));
        $fechaFin = trim((string) $request->query('fecha_fin', $request->input('fecha_fin', '')));

        if ($fechaIni === '' && $fechaFin === '') {
            [$lunesInicio, $lunesFin] = $this->resolverRangoSemanasDesdeRequest($request);

            if (! $lunesInicio || ! $lunesFin) {
                return [null, null, null, null];
            }

            return [$lunesInicio, $lunesFin->addDays(6), $lunesInicio, $lunesFin];
        }

        $fechaInicio = $this->resolverFecha($fechaIni);
        $fechaFin = $this->resolverFecha($fechaFin);

        if (! $fechaInicio || ! $fechaFin) {
            return [null, null, null, null];
        }

        if ($fechaInicio->greaterThan($fechaFin)) {
            return [null, null, null, null];
        }

        $lunesInicio = $fechaInicio->startOfWeek(CarbonInterface::MONDAY);
        $lunesFin = $fechaFin->startOfWeek(CarbonInterface::MONDAY);

        return [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin];
    }

    private function resolverRangoSemanasDesdeRequest(Request $request): array
    {
        $semana = trim((string) $request->query('semana', $request->input('semana', '')));
        $semanaIni = trim((string) $request->query('semana_ini', $request->input('semana_ini', '')));
        $semanaFin = trim((string) $request->query('semana_fin', $request->input('semana_fin', '')));

        if ($semana !== '') {
            $semanaIni = $semana;
            $semanaFin = $semana;
        }

        $lunesInicio = $this->resolverSemanaIso($semanaIni);
        $lunesFin = $this->resolverSemanaIso($semanaFin);

        if (! $lunesInicio || ! $lunesFin) {
            return [null, null];
        }

        if ($lunesInicio->greaterThan($lunesFin)) {
            return [null, null];
        }

        return [$lunesInicio, $lunesFin];
    }

    private function resolverFecha(?string $fecha): ?CarbonImmutable
    {
        $fecha = trim((string) ($fecha ?? ''));
        if ($fecha === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($fecha)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolverSemanaIso(?string $semana): ?CarbonImmutable
    {
        $semana = trim((string) ($semana ?? ''));
        if ($semana === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})-W(\d{2})$/', $semana, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];

        if ($week < 1 || $week > 53) {
            return null;
        }

        return CarbonImmutable::now(config('app.timezone'))
            ->setISODate($year, $week)
            ->startOfDay();
    }

    private function resolverRangoAnualAtadores(CarbonImmutable $fechaReferencia): array
    {
        $year = $fechaReferencia->year;
        $inicioAnual = $fechaReferencia->startOfYear()->startOfWeek(CarbonInterface::MONDAY);
        $finAnual = $fechaReferencia->endOfYear()->startOfWeek(CarbonInterface::MONDAY);

        return [$inicioAnual, $finAnual, $year];
    }

    private function guardarReporteAtadoresEnRuta(Reporte00EAtadoresRangoExport $export, string $nombreArchivo): string
    {
        $rutaRed = config('filesystems.disks.reports_atadores.root') ?: '\\\\192.168.2.11\\ti-system';
        $sep = PHP_OS_FAMILY === 'Windows' ? '\\' : '/';
        $rutaNormalizada = rtrim(str_replace(['/', '\\'], $sep, $rutaRed), $sep);

        if ($rutaNormalizada === '') {
            throw new RuntimeException('La ruta configurada para reportes de atadores es invalida.');
        }

        $rutaArchivo = $rutaNormalizada.$sep.$nombreArchivo;
        $directorio = dirname($rutaArchivo);

        if (! is_dir($directorio) && ! @mkdir($directorio, 0777, true) && ! is_dir($directorio)) {
            throw new RuntimeException("No se pudo preparar la ruta destino: {$directorio}");
        }

        $contenido = Excel::raw($export, ExcelFormat::XLSX);
        if (! is_string($contenido) || $contenido === '') {
            throw new RuntimeException('No se pudo generar el contenido del archivo Excel.');
        }

        $bytes = @file_put_contents($rutaArchivo, $contenido);
        if ($bytes === false) {
            $lastError = error_get_last();
            $message = $lastError['message'] ?? 'Error desconocido al escribir el archivo.';
            throw new RuntimeException($message);
        }

        Log::info('Reporte anual OEE Atadores guardado en ruta configurada', [
            'archivo' => $nombreArchivo,
            'ruta' => $rutaArchivo,
            'bytes' => $bytes,
        ]);

        return $rutaArchivo;
    }
}
