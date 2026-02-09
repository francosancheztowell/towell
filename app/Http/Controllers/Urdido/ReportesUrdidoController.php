<?php

namespace App\Http\Controllers\Urdido;

use App\Http\Controllers\Controller;
use App\Exports\ReportesUrdidoExport;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Vista de reportes de urdido por máquina (ORDEN, JULIO, P. NETO, METROS, OPE.)
     */
    public function index(Request $request)
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
            ->join('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where('p.Status', 'Finalizado');
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
            if ($mc === null) continue;

            $label = $this->maquinaLabel($mc);
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

        // Ordenar máquinas: MC1, MC2, MC3, KM
        $ordenMaquinas = ['MC1' => 1, 'MC2' => 2, 'MC3' => 3, 'KM' => 4];
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
            ->join('UrdProgramaUrdido as p', 'UrdProduccionUrdido.Folio', '=', 'p.Folio')
            ->whereBetween('UrdProduccionUrdido.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where('p.Status', 'Finalizado');
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

        $ordenMaquinas = ['MC1' => 1, 'MC2' => 2, 'MC3' => 3, 'KM' => 4];
        $porFecha = [];
        foreach ($registros as $r) {
            $mc = $this->extractMcCoyNumber($r->MaquinaId);
            if ($mc === null) continue;

            $fecha = $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : '';
            if (!isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'porMaquina' => [],
                    'porOperador' => [],
                    'totalKg' => 0,
                ];
            }

            $label = $this->maquinaLabel($mc);
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
        ksort($porFecha);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $fechaNombre = !empty($porFecha)
            ? $this->parseReportDate((string) array_key_last($porFecha))
            : $fechaFinCarbon;

        $filenameRed = $fechaNombre->format('m') . '-0EE URD-ENG-' . $fechaNombre->format('Y') . '.xlsx';
        $filenameDownload = 'reporte-urdido-' . $fechaIniCarbon->format('Ymd') . '-' . $fechaFinCarbon->format('Ymd') . '.xlsx';

        $export = new ReportesUrdidoExport($porFecha);

        // Guardar en ruta de red (EFIC-CA UR-ENG 2026)
        $diskRed = 'reports_urdido';
        if (config("filesystems.disks.{$diskRed}.root")) {
            try {
                Excel::store($export, $filenameRed, $diskRed);
            } catch (\Throwable $e) {
                Log::warning('No se pudo guardar reporte Urdido en ruta de red: ' . $e->getMessage());
            }
        }

        return Excel::download($export, $filenameDownload);
    }
}
