<?php

namespace App\Http\Controllers\Urdido;

use App\Http\Controllers\Controller;
use App\Exports\ReportesUrdidoExport;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
                'disponible' => false,
            ],
            [
                'nombre' => 'ROTURAS X MILLÓN',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.roturas'),
                'disponible' => false,
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

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            $ope = $this->obtenerOperadorDisplay($r->NomEmpl1, $r->CveEmpl1);
            $kg = (float)($r->KgNeto ?? 0);

            $porMaquina[$label]['filas'][] = [
                'orden' => $r->Folio,
                'julio' => $r->NoJulio,
                'p_neto' => $kg,
                'metros' => round($metros),
                'ope' => $ope,
            ];
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
     * Reporte 2: Kaizen urd-eng (en desarrollo)
     */
    public function reporteKaizen()
    {
        return view('modulos.urdido.reportes-urdido-placeholder', [
            'titulo' => 'Kaizen urd-eng',
            'mensaje' => 'Este reporte está en desarrollo. Próximamente podrá consultar por rango de fechas.',
        ]);
    }

    /**
     * Reporte 3: Roturas x Millón (en desarrollo)
     */
    public function reporteRoturas()
    {
        return view('modulos.urdido.reportes-urdido-placeholder', [
            'titulo' => 'ROTURAS X MILLÓN',
            'mensaje' => 'Este reporte está en desarrollo. Próximamente podrá consultar por rango de fechas.',
        ]);
    }

    /**
     * Obtener texto para mostrar del operador (nombre o clave).
     * Prioriza NomEmpl1, si está vacío usa CveEmpl1. Trunca nombres largos.
     */
    private function obtenerOperadorDisplay(?string $nomEmpl, ?string $cveEmpl): string
    {
        $nom = trim((string) ($nomEmpl ?? ''));
        $cve = trim((string) ($cveEmpl ?? ''));
        if ($nom !== '') {
            return mb_strlen($nom) > 15 ? mb_substr($nom, 0, 15) . '…' : $nom;
        }
        if ($cve !== '') {
            return mb_strlen($cve) > 10 ? mb_substr($cve, 0, 10) . '…' : $cve;
        }
        return '';
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

            $metros = (float)($r->Metros1 ?? 0) + (float)($r->Metros2 ?? 0) + (float)($r->Metros3 ?? 0);
            $ope = $this->obtenerOperadorDisplay($r->NomEmpl1, $r->CveEmpl1);
            $kg = (float)($r->KgNeto ?? 0);

            $porFecha[$fecha]['porMaquina'][$label]['filas'][] = [
                'orden' => $r->Folio,
                'julio' => $r->NoJulio,
                'p_neto' => $kg,
                'metros' => round($metros),
                'ope' => $ope,
            ];
            $porFecha[$fecha]['totalKg'] += $kg;

            $opeKey = trim($ope) !== '' ? $ope : 'Sin asignar';
            if (!isset($porFecha[$fecha]['porOperador'][$opeKey])) {
                $porFecha[$fecha]['porOperador'][$opeKey] = ['nombre' => $opeKey, 'metros' => 0];
            }
            $porFecha[$fecha]['porOperador'][$opeKey]['metros'] += round($metros);
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

        // Guardar en ruta de red (igual que DescargarProgramaController: file_put_contents directo)
        $rutaRed = env('REPORTS_URDIDO_PATH', '\\\\192.168.2.11\\produccion\\PRODUCCION\\INDICADORES\\2026\\EFICIENCIAS 2026\\EFIC-CA UR-ENG 2026');
        $sep = (PHP_OS_FAMILY === 'Windows') ? '\\' : '/';
        $rutaArchivoRed = rtrim(str_replace(['/', '\\'], $sep, $rutaRed), $sep) . $sep . $filenameRed;

        try {
            Excel::store($export, $filenameRed, 'local');
            $tempPath = Storage::disk('local')->path($filenameRed);
            $contenido = file_get_contents($tempPath);
            Storage::disk('local')->delete($filenameRed);

            $bytes = $contenido !== false ? file_put_contents($rutaArchivoRed, $contenido) : false;
            $resultado = $bytes !== false;

            if ($resultado) {
                Log::info('Reporte Urdido guardado en red', ['archivo' => $filenameRed, 'ruta' => $rutaArchivoRed]);
            } else {
                $lastError = error_get_last();
                Log::warning('No se pudo guardar reporte Urdido en ruta de red', [
                    'archivo' => $filenameRed,
                    'ruta' => $rutaArchivoRed,
                    'os' => PHP_OS_FAMILY,
                    'contenido_ok' => $contenido !== false,
                    'bytes_escritos' => $bytes,
                    'php_error' => $lastError,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Error al guardar reporte Urdido en red', [
                'archivo' => $filenameRed,
                'ruta' => $rutaArchivoRed,
                'os' => PHP_OS_FAMILY,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return Excel::download($export, $filenameDownload);
    }
}
