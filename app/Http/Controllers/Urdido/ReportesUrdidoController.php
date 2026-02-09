<?php

namespace App\Http\Controllers\Urdido;

use App\Http\Controllers\Controller;
use App\Exports\ReportesUrdidoExport;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesUrdidoController extends Controller
{
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

        $porMaquina = [];
        foreach ($registros as $r) {
            $mc = $this->extractMcCoyNumber($r->MaquinaId);
            if ($mc === null) continue;

            $label = $this->maquinaLabel($mc);
            if (!isset($porMaquina[$label])) {
                $porMaquina[$label] = ['label' => $label, 'filas' => []];
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
        }

        $ordenMaquinas = ['MC1' => 1, 'MC2' => 2, 'MC3' => 3, 'KM' => 4];
        uksort($porMaquina, fn($a, $b) => ($ordenMaquinas[$a] ?? 99) <=> ($ordenMaquinas[$b] ?? 99));

        $fechaStr = Carbon::parse($fechaIni)->format('Ymd') . '-' . Carbon::parse($fechaFin)->format('Ymd');
        $filename = "reporte-urdido-{$fechaStr}.xlsx";

        return Excel::download(new ReportesUrdidoExport($porMaquina), $filename);
    }
}
