<?php

namespace App\Http\Controllers\Atadores\Reportes;

use App\Exports\ProgramaAtadoresExport;
use App\Exports\Reporte00EAtadoresRangoExport;
use App\Http\Controllers\Controller;
use App\Jobs\ActualizarOeeAtadoresJob;
use App\Services\OeeAtadores\OeeAtadoresFileService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ReportesAtadoresController extends Controller
{
    private const OEE_QUEUE = 'oee-atadores';

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

        $filePath = $this->oeeAtadoresFilePath();

        try {
            $service = new OeeAtadoresFileService($filePath);
            $resultado = $service->verificarSemanasConDatos($lunesInicio, $lunesFin);

            return response()->json($resultado);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /atadores/reportes-atadores/atadores/descargar
     * Descarga un Excel del rango directamente (no toca OEE_ATADORES.xlsx).
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

    public function despacharOeeAtadores(Request $request): JsonResponse
    {
        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (! $lunesInicio || ! $lunesFin) {
            return response()->json(['error' => 'Rango de fechas inválido.'], 422);
        }

        $filePath = $this->oeeAtadoresFilePath();

        if (! is_file($filePath)) {
            return response()->json(['error' => "El archivo OEE no existe: {$filePath}"], 422);
        }

        $token = bin2hex(random_bytes(16));
        $cacheKey = "oee_job_{$token}";

        Cache::store('file')->put($cacheKey, ['estado' => 'despachado'], 600);

        ActualizarOeeAtadoresJob::dispatch(
            $filePath,
            $lunesInicio->toDateString(),
            $lunesFin->toDateString(),
            $cacheKey,
            $token,
        )->onQueue(self::OEE_QUEUE);

        $this->bootOeeQueueWorker();

        return response()->json(['token' => $token]);
    }

    public function estadoOeeAtadores(Request $request, string $token): JsonResponse
    {
        $cacheKey = "oee_job_{$token}";
        $estado = Cache::store('file')->get($cacheKey);

        if ($estado === null) {
            return response()->json(['estado' => 'desconocido']);
        }

        $statusFile = $estado['status_file'] ?? null;
        if (is_string($statusFile) && is_file($statusFile)) {
            $json = json_decode((string) file_get_contents($statusFile), true);
            if (is_array($json)) {
                unset($estado['status_file']);

                return response()->json(array_merge($estado, $json));
            }
        }

        unset($estado['status_file']);

        return response()->json($estado);
    }

    /**
     * Resuelve la ruta del archivo OEE_ATADORES.xlsx.
     * Prioridad: env OEE_ATADORES_FILE_PATH > share de red configurado en filesystems.reports_atadores.
     */
    private function oeeAtadoresFilePath(): string
    {
        $envPath = env('OEE_ATADORES_FILE_PATH');
        if (is_string($envPath) && $envPath !== '') {
            return $envPath;
        }

        $rutaRed = config('filesystems.disks.reports_atadores.root') ?: '\\\\192.168.2.11\\ti-system';

        return rtrim($rutaRed, '\\/').DIRECTORY_SEPARATOR.'OEE_ATADORES.xlsx';
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


    private function bootOeeQueueWorker(): void
    {
        $queueConnection = (string) config('queue.default', env('QUEUE_CONNECTION', 'sync'));
        if ($queueConnection === 'sync') {
            return;
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $command = implode(' ', [
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            'queue:work',
            escapeshellarg($queueConnection),
            '--queue='.self::OEE_QUEUE,
            '--once',
            '--tries=1',
            '--timeout=600',
            '--stop-when-empty',
        ]);

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                @pclose(@popen('start /B "" '.$command.' >NUL 2>&1', 'r'));
            } else {
                @exec($command.' >/dev/null 2>&1 &');
            }

            Log::info('Worker OEE Atadores iniciado', [
                'queue_connection' => $queueConnection,
                'queue' => self::OEE_QUEUE,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo iniciar el worker OEE Atadores', [
                'queue_connection' => $queueConnection,
                'queue' => self::OEE_QUEUE,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
