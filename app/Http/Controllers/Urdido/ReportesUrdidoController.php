<?php

namespace App\Http\Controllers\Urdido;

use App\Http\Controllers\Controller;
use App\Exports\BpmUrdidoExport;
use App\Exports\KaizenExport;
use App\Exports\ReportesUrdidoExport;
use App\Exports\RoturasMillonExport;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\UrdProduccionUrdido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportesUrdidoController extends Controller
{
    private function parseReportDate(string $value): Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return Carbon::now();
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable $e) {
                // Intentar con el siguiente formato.
            }
        }

        return Carbon::parse($value)->startOfDay();
    }

    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) return null;
        if (stripos($maquinaId, 'karl mayer') !== false) return 4;
        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquinaId, $matches)) return (int) $matches[1];
        return null;
    }

    private function maquinaLabel(int $num): string
    {
        return $num === 4 ? 'KM' : "MC{$num}";
    }

    /**
     * Extraer WP2 o WP3 del campo MaquinaEng de engomado
     */
    private function extractEngomadoWP(?string $maquinaEng): ?string
    {
        if (empty(trim((string) $maquinaEng))) return null;
        $m = trim($maquinaEng);
        if (preg_match('/west\s*point\s*2|westpoint\s*2|tabla\s*1|izquierda/i', $m) || $m === '2') return 'WP2';
        if (preg_match('/west\s*point\s*3|westpoint\s*3|tabla\s*2|derecha/i', $m) || $m === '3') return 'WP3';
        return null;
    }

    /**
     * Selector de reportes: muestra los 3 reportes disponibles (03-OEE, Kaizen, Roturas x Millón)
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Reportes de Produccion 03-OEE URD-ENG',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.03-oee'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Kaizen urd-eng',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.kaizen'),
                'disponible' => true,
            ],
            [
                'nombre' => 'ROTURAS X MILLÓN',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.roturas'),
                'disponible' => true,
            ],
            [
                'nombre' => 'BPM Urdido',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.bpm'),
                'disponible' => true,
            ],
        ];

        return view('modulos.urdido.reportes-urdido-index', ['reportes' => $reportes]);
    }

    /**
     * Reporte 1: 03-OEE URD-ENG - Producción por máquina (ORDEN, JULIO, P. NETO, METROS, OPE.)
     */
    public function reporte03Oee(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        // Sin rango seleccionado: no consultar (el usuario debe elegir las fechas)
        if (!$fechaIni || !$fechaFin) {
            return view('modulos.urdido.reportes-urdido', [
                'porMaquina' => [],
                'totalKg' => 0,
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $query = UrdProduccionUrdido::query()
            ->leftJoin('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where(function ($q) {
                $q->where('p.Status', 'Finalizado')
                    ->orWhereNull('p.Id');
            });
        }

        $registros = $query
            ->select([
                'UrdProduccionUrdido.Id',
                'UrdProduccionUrdido.Folio',
                'UrdProduccionUrdido.NoJulio',
                'UrdProduccionUrdido.KgNeto',
                'UrdProduccionUrdido.Metros1',
                'UrdProduccionUrdido.Metros2',
                'UrdProduccionUrdido.Metros3',
                'UrdProduccionUrdido.CveEmpl1',
                'UrdProduccionUrdido.NomEmpl1',
                'UrdProduccionUrdido.CveEmpl2',
                'UrdProduccionUrdido.NomEmpl2',
                'UrdProduccionUrdido.CveEmpl3',
                'UrdProduccionUrdido.NomEmpl3',
                'p.MaquinaId',
            ])
            ->orderBy('p.MaquinaId')
            ->orderBy('UrdProduccionUrdido.Folio')
            ->orderBy('UrdProduccionUrdido.NoJulio')
            ->get();

        // Agrupar por máquina (MC1, MC2, MC3, KM)
        $porMaquina = [];
        $totalKg = 0;

        foreach ($registros as $r) {
            $mc = $this->extractMcCoyNumber($r->MaquinaId);
            $label = $mc !== null ? $this->maquinaLabel($mc) : 'Otros';

            if (!isset($porMaquina[$label])) {
                $porMaquina[$label] = [
                    'label' => $label,
                    'filas' => [],
                    'totalKg' => 0,
                ];
            }

            $kg = (float)($r->KgNeto ?? 0);
            $operadores = $this->extraerOperadoresConMetros($r);
            $primerFila = true;
            foreach ($operadores as $op) {
                $porMaquina[$label]['filas'][] = [
                    'orden' => $r->Folio,
                    'julio' => $r->NoJulio,
                    'p_neto' => $primerFila ? $kg : null,
                    'metros' => $op['metros'],
                    'ope' => $op['nombre'],
                ];
                $primerFila = false;
            }
            if (empty($operadores)) {
                $metros = round((float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0));
                if ($metros > 0) {
                    $porMaquina[$label]['filas'][] = [
                        'orden' => $r->Folio,
                        'julio' => $r->NoJulio,
                        'p_neto' => $kg,
                        'metros' => $metros,
                        'ope' => $this->obtenerOperadorDisplayCompleto($r->NomEmpl1, $r->CveEmpl1),
                    ];
                }
            }
            $porMaquina[$label]['totalKg'] += $kg;
            $totalKg += $kg;
        }

        // Ordenar máquinas: MC1, MC2, MC3, KM, Otros
        $ordenMaquinas = ['MC1' => 1, 'MC2' => 2, 'MC3' => 3, 'KM' => 4, 'Otros' => 5];
        uksort($porMaquina, fn($a, $b) => ($ordenMaquinas[$a] ?? 99) <=> ($ordenMaquinas[$b] ?? 99));

        return view('modulos.urdido.reportes-urdido', [
            'porMaquina' => $porMaquina,
            'totalKg' => round($totalKg, 1),
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    /**
     * Reporte 2: Kaizen urd-eng - AX ENGOMADO y AX URDIDO
     */
    public function reporteKaizen(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.urdido.reportes-kaizen', [
                'filasEngomado' => [],
                'filasUrdido' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        [$filasEngomado, $filasUrdido] = $this->obtenerDatosKaizen($fechaIni, $fechaFin, $soloFinalizados);

        return view('modulos.urdido.reportes-kaizen', [
            'filasEngomado' => $filasEngomado,
            'filasUrdido' => $filasUrdido,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    /**
     * Obtener datos para reporte Kaizen (AX ENGOMADO y AX URDIDO).
     */
    private function obtenerDatosKaizen(string $fechaIni, string $fechaFin, bool $soloFinalizados): array
    {
        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
            7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        $queryEng = EngProduccionEngomado::query()
            ->join('EngProgramaEngomado as p', 'EngProduccionEngomado.Folio', '=', 'p.Folio')
            ->whereBetween('EngProduccionEngomado.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $queryEng->where('p.Status', 'Finalizado');
        }

        $registrosEng = $queryEng
            ->select([
                'EngProduccionEngomado.Id',
                'EngProduccionEngomado.Folio',
                'EngProduccionEngomado.Fecha',
                'EngProduccionEngomado.NoJulio',
                'EngProduccionEngomado.KgNeto',
                'EngProduccionEngomado.Metros1',
                'EngProduccionEngomado.Metros2',
                'EngProduccionEngomado.Metros3',
                'p.Calibre',
                'p.Cuenta',
                'p.Fibra',
                'p.MaquinaEng',
                'p.MermaGoma',
                'p.Merma',
            ])
            ->orderBy('EngProduccionEngomado.Fecha')
            ->orderBy('EngProduccionEngomado.Folio')
            ->orderBy('EngProduccionEngomado.NoJulio')
            ->get();

        $filasEngomado = [];
        $vistosEng = [];
        foreach ($registrosEng as $r) {
            if (isset($vistosEng[$r->Id])) continue;
            $vistosEng[$r->Id] = true;

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            if ($metros <= 0) continue;

            $fecha = $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : null;
            $carbon = $fecha ? Carbon::parse($fecha) : Carbon::now();
            $wp = $this->extractEngomadoWP($r->MaquinaEng);
            if ($wp === null) $wp = 'WP2';

            $calibre = $r->Calibre ?? '';

            $filasEngomado[] = [
                'fecha_mod' => $carbon->format('d/m/Y'),
                'anio' => (int) $carbon->format('Y'),
                'mes' => $meses[(int)$carbon->format('n')] ?? '',
                'codigo' => 'JU-ENG-RI-C',
                'localidad' => $wp,
                'estado' => 'Terminado',
                'lote' => $r->Folio,
                'calibre' => $calibre,
                'cantidad' => round((float)($r->KgNeto ?? 0), 2),
                'configuracion' => $r->Fibra ?? '',
                'tamano' => $r->Cuenta ?? '',
                'mts' => (int) round($metros),
                'merma_goma' => (float)($r->MermaGoma ?? 0),
                'merma' => (float)($r->Merma ?? 0),
                'julios' => $r->NoJulio ?? '',
            ];
        }

        $queryUrd = UrdProduccionUrdido::query()
            ->leftJoin('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $queryUrd->where(function ($q) {
                $q->where('p.Status', 'Finalizado')->orWhereNull('p.Id');
            });
        }

        $registrosUrd = $queryUrd
            ->select([
                'UrdProduccionUrdido.Id',
                'UrdProduccionUrdido.Folio',
                'UrdProduccionUrdido.Fecha',
                'UrdProduccionUrdido.NoJulio',
                'UrdProduccionUrdido.KgNeto',
                'UrdProduccionUrdido.Metros1',
                'UrdProduccionUrdido.Metros2',
                'UrdProduccionUrdido.Metros3',
                'p.Calibre',
                'p.Cuenta',
                'p.Fibra',
                'p.MaquinaId',
            ])
            ->orderBy('UrdProduccionUrdido.Fecha')
            ->orderBy('UrdProduccionUrdido.Folio')
            ->orderBy('UrdProduccionUrdido.NoJulio')
            ->get();

        $filasUrdido = [];
        $vistosUrd = [];
        foreach ($registrosUrd as $r) {
            if (isset($vistosUrd[$r->Id])) continue;
            $vistosUrd[$r->Id] = true;

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            if ($metros <= 0) continue;

            $fecha = $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : null;
            $carbon = $fecha ? Carbon::parse($fecha) : Carbon::now();
            $mc = $this->extractMcCoyNumber($r->MaquinaId);
            $localidad = $mc !== null ? $this->maquinaLabel($mc) : 'Otros';

            $calibre = $r->Calibre ?? $r->Cuenta ?? '';
            if (is_string($calibre) && $calibre !== '' && !str_contains($calibre, '/')) {
                $calibre = (string) $calibre;
            }

            $filasUrdido[] = [
                'fecha_mod' => $carbon->format('d/m/Y'),
                'anio' => (int) $carbon->format('Y'),
                'mes' => $meses[(int)$carbon->format('n')] ?? '',
                'codigo' => 'JULIO-URDIDO',
                'localidad' => $localidad,
                'estado' => 'Terminado',
                'lote' => $r->Folio,
                'calibre' => $calibre,
                'cantidad' => round((float)($r->KgNeto ?? 0), 2),
                'configuracion' => $r->Fibra ?? '',
                'tamano' => $r->Cuenta ?? '',
                'mts' => (int) round($metros),
                'julios' => $r->NoJulio ?? '',
            ];
        }

        return [$filasEngomado, $filasUrdido];
    }

    /**
     * Exportar Excel Kaizen (AX ENGOMADO + AX URDIDO)
     */
    public function exportarKaizenExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('urdido.reportes.urdido.kaizen')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        [$filasEngomado, $filasUrdido] = $this->obtenerDatosKaizen($fechaIni, $fechaFin, $soloFinalizados);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $filenameRed = 'Kaizen urd-eng ' . $fechaFinCarbon->format('Y') . '.xlsx';
        $filenameDownload = 'kaizen-urd-eng-' . $fechaIniCarbon->format('Ymd') . '-' . $fechaFinCarbon->format('Ymd') . '.xlsx';

        $export = new KaizenExport($filasEngomado, $filasUrdido);

        return $this->guardarReporteEnRed($export, $filenameRed, $filenameDownload, 'Kaizen');
    }

    /**
     * Guarda el reporte en la ruta de red y devuelve la descarga.
     * Usa config() para la ruta (compatible con config:cache en producción).
     * Usa Log::error() para fallos (visible con LOG_LEVEL=error).
     */
    private function guardarReporteEnRed($export, string $filenameRed, string $filenameDownload, string $nombreReporte)
    {
        $rutaRed = config('filesystems.disks.reports_urdido.root')
            ?? '\\\\192.168.2.11\\produccion\\PRODUCCION\\INDICADORES\\2026\\EFICIENCIAS 2026\\EFIC-CA UR-ENG 2026';
        $sep = (PHP_OS_FAMILY === 'Windows') ? '\\' : '/';
        $rutaArchivoRed = rtrim(str_replace(['/', '\\'], $sep, $rutaRed), $sep) . $sep . $filenameRed;

        try {
            Excel::store($export, $filenameRed, 'local');
            $tempPath = Storage::disk('local')->path($filenameRed);
            $contenido = file_get_contents($tempPath);
            Storage::disk('local')->delete($filenameRed);

            $bytes = $contenido !== false ? @file_put_contents($rutaArchivoRed, $contenido) : false;

            if ($bytes !== false) {
                Log::info("Reporte {$nombreReporte} guardado en red", [
                    'archivo' => $filenameRed,
                    'ruta' => $rutaArchivoRed,
                    'bytes' => $bytes,
                ]);
            } else {
                $lastError = error_get_last();
                Log::error("No se pudo guardar reporte {$nombreReporte} en ruta de red", [
                    'archivo' => $filenameRed,
                    'ruta' => $rutaArchivoRed,
                    'os' => PHP_OS_FAMILY,
                    'contenido_ok' => $contenido !== false,
                    'php_error' => $lastError,
                    'sugerencia' => 'Verifique REPORTS_URDIDO_PATH en .env, permisos de la ruta y que la red sea accesible.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Error al guardar reporte {$nombreReporte} en red", [
                'ruta' => $rutaArchivoRed,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return Excel::download($export, $filenameDownload);
    }

    /**
     * Reporte 3: Roturas x Millón
     */
    public function reporteRoturas(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.urdido.reportes-roturas', [
                'filas' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $filas = $this->obtenerDatosRoturas($fechaIni, $fechaFin, $soloFinalizados);

        return view('modulos.urdido.reportes-roturas', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    /**
     * Obtener datos agrupados por orden para Roturas x Millón.
     */
    private function obtenerDatosRoturas(string $fechaIni, string $fechaFin, bool $soloFinalizados): array
    {
        $query = UrdProduccionUrdido::query()
            ->leftJoin('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where(function ($q) {
                $q->where('p.Status', 'Finalizado')->orWhereNull('p.Id');
            });
        }

        $registros = $query
            ->select([
                'UrdProduccionUrdido.Folio',
                'UrdProduccionUrdido.Fecha',
                'UrdProduccionUrdido.NoJulio',
                'UrdProduccionUrdido.Hilos',
                'UrdProduccionUrdido.Metros1',
                'UrdProduccionUrdido.Metros2',
                'UrdProduccionUrdido.Metros3',
                'UrdProduccionUrdido.Hilatura',
                'UrdProduccionUrdido.Maquina',
                'UrdProduccionUrdido.Operac',
                'UrdProduccionUrdido.Transf',
                'p.MaquinaId',
                'p.LoteProveedor',
                'p.Cuenta',
                'p.Calibre',
                'p.RizoPie',
                'p.Metros as MetrosPrograma',
            ])
            ->orderBy('UrdProduccionUrdido.Folio')
            ->orderBy('UrdProduccionUrdido.NoJulio')
            ->get();

        // Agrupar por Folio (orden)
        $porOrden = [];
        foreach ($registros as $r) {
            $folio = $r->Folio;
            if (!isset($porOrden[$folio])) {
                $mc = $this->extractMcCoyNumber($r->MaquinaId);
                $maqLabel = $mc !== null ? $this->maquinaLabel($mc) : ($r->MaquinaId ?? 'Otros');
                $porOrden[$folio] = [
                    'maq' => $maqLabel,
                    'fecha' => $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : '',
                    'orden' => $folio,
                    'proveedor' => $r->LoteProveedor ?? '',
                    'cuenta' => $r->Cuenta ?? '',
                    'calibre' => $r->Calibre ?? '',
                    'tipo' => $r->RizoPie ?? '',
                    'metros_programa' => (float)($r->MetrosPrograma ?? 0),
                    'total_julios' => 0,
                    'hilos_sum' => 0,
                    'metros_sum' => 0,
                    'rot_hilatura' => 0,
                    'rot_maquina' => 0,
                    'rot_operacion' => 0,
                    'transferencia' => 0,
                ];
            }

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            $porOrden[$folio]['total_julios']++;
            $porOrden[$folio]['hilos_sum'] += (int)($r->Hilos ?? 0);
            $porOrden[$folio]['metros_sum'] += $metros;
            $porOrden[$folio]['rot_hilatura'] += (int)($r->Hilatura ?? 0);
            $porOrden[$folio]['rot_maquina'] += (int)($r->Maquina ?? 0);
            $porOrden[$folio]['rot_operacion'] += (int)($r->Operac ?? 0);
            $porOrden[$folio]['transferencia'] += (int)($r->Transf ?? 0);
        }

        $filas = [];
        foreach ($porOrden as $d) {
            $totalJulios = $d['total_julios'];
            $metrosJulio = $totalJulios > 0 ? round($d['metros_sum'] / $totalJulios) : 0;
            $hilosJulio = $totalJulios > 0 ? round($d['hilos_sum'] / $totalJulios) : 0;
            $metrosOrden = $metrosJulio * $totalJulios;
            $millonMetros = $metrosOrden > 0 && $hilosJulio > 0 ? round(($metrosOrden * $hilosJulio) / 1000000, 2) : 0;
            $totalRoturas = $d['rot_hilatura'] + $d['rot_maquina'] + $d['rot_operacion'] + $d['transferencia'];

            $filas[] = [
                'maq' => $d['maq'],
                'fecha' => $d['fecha'],
                'orden' => $d['orden'],
                'proveedor' => $d['proveedor'],
                'cuenta' => $d['cuenta'],
                'calibre' => $d['calibre'],
                'tipo' => $d['tipo'],
                'metros_julio' => $metrosJulio,
                'total_julios' => $totalJulios,
                'hilos_julio' => $hilosJulio,
                'metros_orden' => $metrosOrden,
                'millon_metros' => $millonMetros,
                'rot_hilatura' => $d['rot_hilatura'],
                'rot_maquina' => $d['rot_maquina'],
                'rot_operacion' => $d['rot_operacion'],
                'transferencia' => $d['transferencia'],
                'total_roturas' => $totalRoturas,
            ];
        }

        return $filas;
    }

    /**
     * Exportar Excel Roturas x Millón
     */
    public function exportarRoturasExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('urdido.reportes.urdido.roturas')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $filas = $this->obtenerDatosRoturas($fechaIni, $fechaFin, $soloFinalizados);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $filenameRed = 'Roturas x Millon ' . $fechaFinCarbon->format('Y') . '.xlsx';
        $filenameDownload = 'roturas-millon-' . $fechaIniCarbon->format('Ymd') . '-' . $fechaFinCarbon->format('Ymd') . '.xlsx';

        $export = new RoturasMillonExport($filas);

        return $this->guardarReporteEnRed($export, $filenameRed, $filenameDownload, 'Roturas x Millón');
    }

    /**
     * Reporte 4: BPM Urdido (Header + Lineas)
     */
    public function reporteBpm(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.urdido.reportes-bpm-urdido', [
                'filas' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $query = UrdBpmModel::query()
            ->from('UrdBPM as h')
            ->leftJoin('UrdBPMLine as l', 'h.Folio', '=', 'l.Folio')
            ->whereBetween('h.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->whereIn('h.Status', ['Terminado', 'Autorizado']);
        }

        $filas = $query
            ->select([
                'h.Folio',
                'h.Status',
                'h.Fecha',
                'h.CveEmplEnt',
                'h.NombreEmplEnt',
                'h.TurnoEntrega',
                'h.CveEmplRec',
                'h.NombreEmplRec',
                'h.TurnoRecibe',
                'h.CveEmplAutoriza',
                'h.NombreEmplAutoriza',
                'l.Orden',
                'l.Actividad',
                'l.Valor',
            ])
            ->orderBy('h.Folio')
            ->orderBy('l.Orden')
            ->get()
            ->map(function ($row) {
                $valor = (int) ($row->Valor ?? 0);
                $row->ValorTexto = $this->mapearValorBpm($valor);
                $row->CveEmplEnt = $this->normalizarClaveNumero($row->CveEmplEnt ?? null);
                $row->CveEmplRec = $this->normalizarClaveNumero($row->CveEmplRec ?? null);
                $row->CveEmplAutoriza = $this->normalizarClaveNumero($row->CveEmplAutoriza ?? null);
                return $row;
            });

        $filas = $this->marcarInicioPorFolio($filas);

        return view('modulos.urdido.reportes-bpm-urdido', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    /**
     * Exportar Excel BPM Urdido
     */
    public function exportarBpmExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('urdido.reportes.urdido.bpm')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $query = UrdBpmModel::query()
            ->from('UrdBPM as h')
            ->leftJoin('UrdBPMLine as l', 'h.Folio', '=', 'l.Folio')
            ->whereBetween('h.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->whereIn('h.Status', ['Terminado', 'Autorizado']);
        }

        $filas = $query
            ->select([
                'h.Folio',
                'h.Status',
                'h.Fecha',
                'h.CveEmplEnt',
                'h.NombreEmplEnt',
                'h.TurnoEntrega',
                'h.CveEmplRec',
                'h.NombreEmplRec',
                'h.TurnoRecibe',
                'h.CveEmplAutoriza',
                'h.NombreEmplAutoriza',
                'l.Orden',
                'l.Actividad',
                'l.Valor',
            ])
            ->orderBy('h.Folio')
            ->orderBy('l.Orden')
            ->get()
            ->map(function ($row) {
                $row->ValorTexto = $this->mapearValorBpm((int) ($row->Valor ?? 0));
                $row->CveEmplEnt = $this->normalizarClaveNumero($row->CveEmplEnt ?? null);
                $row->CveEmplRec = $this->normalizarClaveNumero($row->CveEmplRec ?? null);
                $row->CveEmplAutoriza = $this->normalizarClaveNumero($row->CveEmplAutoriza ?? null);
                return $row;
            });

        $filas = $this->marcarInicioPorFolio($filas);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $filenameRed = 'BPM Urdido ' . $fechaFinCarbon->format('Y') . '.xlsx';
        $filenameDownload = 'bpm-urdido-' . $fechaIniCarbon->format('Ymd') . '-' . $fechaFinCarbon->format('Ymd') . '.xlsx';

        $export = new BpmUrdidoExport($filas);

        return $this->guardarReporteEnRed($export, $filenameRed, $filenameDownload, 'BPM Urdido');
    }

    private function mapearValorBpm(int $valor): string
    {
        if ($valor === 1) {
            return 'CORRECTO';
        }
        if ($valor === 2) {
            return 'INCORRECTO';
        }

        return 'S/N';
    }

    private function normalizarClaveNumero(mixed $clave): ?int
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        $texto = trim((string) $clave);
        if ($texto === '') {
            return null;
        }

        if (is_numeric($texto)) {
            return (int) $texto;
        }

        return null;
    }

    private function marcarInicioPorFolio(Collection $filas): Collection
    {
        $folioAnterior = null;

        return $filas->map(function ($fila) use (&$folioAnterior) {
            $folioActual = (string) ($fila->Folio ?? '');
            $esInicio = $folioActual !== '' && $folioActual !== $folioAnterior;

            $fila->InicioFolio = $esInicio ? '•' : null;
            $folioAnterior = $folioActual;

            return $fila;
        });
    }

    /**
     * Obtener texto para mostrar del operador (nombre o clave). Nombre completo.
     */
    private function obtenerOperadorDisplayCompleto(?string $nomEmpl, ?string $cveEmpl): string
    {
        $nom = trim((string) ($nomEmpl ?? ''));
        $cve = trim((string) ($cveEmpl ?? ''));
        if ($nom !== '') return $nom;
        if ($cve !== '') return $cve;
        return '';
    }

    /**
     * Obtener texto para mostrar del operador (nombre o clave). Trunca para vistas compactas.
     */
    private function obtenerOperadorDisplay(?string $nomEmpl, ?string $cveEmpl): string
    {
        return $this->obtenerOperadorDisplayCompleto($nomEmpl, $cveEmpl);
    }

    /**
     * Extraer 1, 2 o 3 operadores con sus metros del registro.
     * Retorna array de ['nombre' => string, 'metros' => int].
     */
    private function extraerOperadoresConMetros(object $r): array
    {
        $ops = [];
        foreach ([1, 2, 3] as $n) {
            $nom = $r->{"NomEmpl{$n}"} ?? null;
            $cve = $r->{"CveEmpl{$n}"} ?? null;
            $mts = (float)($r->{"Metros{$n}"} ?? 0);
            if ($mts <= 0) continue;
            $nombre = $this->obtenerOperadorDisplayCompleto($nom, $cve);
            $ops[] = ['nombre' => $nombre !== '' ? $nombre : "Turno {$n}", 'metros' => round($mts)];
        }
        return $ops;
    }

    /**
     * Descargar Excel con el reporte (usa los mismos filtros: fecha_ini, fecha_fin, solo_finalizados)
     */
    public function exportarExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('urdido.reportes.urdido')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $query = UrdProduccionUrdido::query()
            ->leftJoin('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where(function ($q) {
                $q->where('p.Status', 'Finalizado')
                    ->orWhereNull('p.Id');
            });
        }

        $registros = $query
            ->select([
                'UrdProduccionUrdido.Id',
                'UrdProduccionUrdido.Folio',
                'UrdProduccionUrdido.Fecha',
                'UrdProduccionUrdido.NoJulio',
                'UrdProduccionUrdido.KgNeto',
                'UrdProduccionUrdido.Metros1',
                'UrdProduccionUrdido.Metros2',
                'UrdProduccionUrdido.Metros3',
                'UrdProduccionUrdido.CveEmpl1',
                'UrdProduccionUrdido.NomEmpl1',
                'UrdProduccionUrdido.CveEmpl2',
                'UrdProduccionUrdido.NomEmpl2',
                'UrdProduccionUrdido.CveEmpl3',
                'UrdProduccionUrdido.NomEmpl3',
                'p.MaquinaId',
            ])
            ->orderBy('UrdProduccionUrdido.Fecha')
            ->orderBy('p.MaquinaId')
            ->orderBy('UrdProduccionUrdido.Folio')
            ->orderBy('UrdProduccionUrdido.NoJulio')
            ->get();

        $ordenMaquinas = ['MC1' => 1, 'MC2' => 2, 'MC3' => 3, 'KM' => 4, 'Otros' => 5];
        $porFecha = [];
        foreach ($registros as $r) {
            $mc = $this->extractMcCoyNumber($r->MaquinaId);
            $label = $mc !== null ? $this->maquinaLabel($mc) : 'Otros';

            $fecha = $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : '';
            if (!isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'porMaquina' => [],
                    'porOperador' => [],
                    'totalKg' => 0,
                    'engomado' => ['WP2' => ['filas' => []], 'WP3' => ['filas' => []]],
                ];
            }
            if (!isset($porFecha[$fecha]['porMaquina'][$label])) {
                $porFecha[$fecha]['porMaquina'][$label] = ['label' => $label, 'filas' => []];
            }

            $kg = (float)($r->KgNeto ?? 0);
            $operadores = $this->extraerOperadoresConMetros($r);
            $primerFila = true;
            foreach ($operadores as $op) {
                $porFecha[$fecha]['porMaquina'][$label]['filas'][] = [
                    'orden' => $r->Folio,
                    'julio' => $r->NoJulio,
                    'p_neto' => $primerFila ? $kg : null,
                    'metros' => $op['metros'],
                    'ope' => $op['nombre'],
                ];
                $opeKey = trim($op['nombre']) !== '' ? $op['nombre'] : 'Sin asignar';
                if (!isset($porFecha[$fecha]['porOperador'][$opeKey])) {
                    $porFecha[$fecha]['porOperador'][$opeKey] = ['nombre' => $opeKey, 'metros' => 0];
                }
                $porFecha[$fecha]['porOperador'][$opeKey]['metros'] += $op['metros'];
                $primerFila = false;
            }
            if (empty($operadores)) {
                $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
                if ($metros > 0) {
                    $ope = $this->obtenerOperadorDisplayCompleto($r->NomEmpl1, $r->CveEmpl1);
                    $opeKey = trim($ope) !== '' ? $ope : 'Sin asignar';
                    $porFecha[$fecha]['porMaquina'][$label]['filas'][] = [
                        'orden' => $r->Folio,
                        'julio' => $r->NoJulio,
                        'p_neto' => $kg,
                        'metros' => round($metros),
                        'ope' => $ope,
                    ];
                    if (!isset($porFecha[$fecha]['porOperador'][$opeKey])) {
                        $porFecha[$fecha]['porOperador'][$opeKey] = ['nombre' => $opeKey, 'metros' => 0];
                    }
                    $porFecha[$fecha]['porOperador'][$opeKey]['metros'] += round($metros);
                }
            }
            $porFecha[$fecha]['totalKg'] += $kg;
        }

        foreach ($porFecha as $f => $datos) {
            uksort($porFecha[$f]['porMaquina'], fn($a, $b) => ($ordenMaquinas[$a] ?? 99) <=> ($ordenMaquinas[$b] ?? 99));
            $porFecha[$f]['porMaquina'] = array_values($porFecha[$f]['porMaquina']);
        }

        // Datos de engomado para Hoja 2 (WP2, WP3)
        $queryEng = EngProduccionEngomado::query()
            ->join('EngProgramaEngomado as p', 'EngProduccionEngomado.Folio', '=', 'p.Folio')
            ->whereBetween('EngProduccionEngomado.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $queryEng->where('p.Status', 'Finalizado');
        }

        $registrosEng = $queryEng
            ->select([
                'EngProduccionEngomado.Fecha',
                'EngProduccionEngomado.Folio',
                'EngProduccionEngomado.NoJulio',
                'EngProduccionEngomado.KgNeto',
                'EngProduccionEngomado.Metros1',
                'EngProduccionEngomado.Metros2',
                'EngProduccionEngomado.Metros3',
                'EngProduccionEngomado.CveEmpl1',
                'EngProduccionEngomado.NomEmpl1',
                'p.MaquinaEng',
            ])
            ->orderBy('EngProduccionEngomado.Fecha')
            ->orderBy('p.MaquinaEng')
            ->orderBy('EngProduccionEngomado.Folio')
            ->orderBy('EngProduccionEngomado.NoJulio')
            ->get();

        foreach ($registrosEng as $r) {
            $wp = $this->extractEngomadoWP($r->MaquinaEng);
            if ($wp === null) continue;

            $fecha = $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : '';
            if (!isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'porMaquina' => [],
                    'porOperador' => [],
                    'totalKg' => 0,
                    'engomado' => ['WP2' => ['filas' => []], 'WP3' => ['filas' => []]],
                ];
            }
            if (!isset($porFecha[$fecha]['engomado'])) {
                $porFecha[$fecha]['engomado'] = ['WP2' => ['filas' => []], 'WP3' => ['filas' => []]];
            }

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            if ($metros <= 0) continue;

            $ope = $this->obtenerOperadorDisplay($r->NomEmpl1, $r->CveEmpl1);
            $kg = (float)($r->KgNeto ?? 0);

            $porFecha[$fecha]['engomado'][$wp]['filas'][] = [
                'orden' => $r->Folio,
                'julio' => $r->NoJulio,
                'p_neto' => $kg,
                'metros' => round($metros),
                'ope' => $ope,
            ];
        }

        foreach ($porFecha as $f => &$datos) {
            if (!isset($datos['engomado'])) {
                $datos['engomado'] = ['WP2' => ['filas' => []], 'WP3' => ['filas' => []]];
            }
        }
        unset($datos);

        ksort($porFecha);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);

        $filenameRed = '03-0EE URD-ENG-' . $fechaFinCarbon->format('Y') . '.xlsx';
        $filenameDownload = 'reporte-urdido-' . $fechaIniCarbon->format('Ymd') . '-' . $fechaFinCarbon->format('Ymd') . '.xlsx';

        $export = new ReportesUrdidoExport($porFecha);

        return $this->guardarReporteEnRed($export, $filenameRed, $filenameDownload, '03-OEE URD-ENG');
    }
}
