<?php

namespace App\Http\Controllers\Engomado;

use App\Http\Controllers\Controller;
use App\Exports\ReportesEngomadoExport;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesEngomadoController extends Controller
{
    private function maquinaLabel(?string $maquinaEng): string
    {
        if (empty(trim((string) $maquinaEng))) {
            return 'Sin máquina';
        }
        return trim($maquinaEng);
    }

    /**
     * Vista de reportes de engomado por máquina (ORDEN, JULIO, P. NETO, METROS, Operador)
     */
    public function index(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.engomado.reportes-engomado', [
                'porMaquina' => [],
                'totalKg' => 0,
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $query = EngProduccionEngomado::query()
            ->join('EngProgramaEngomado as p', 'EngProduccionEngomado.Folio', '=', 'p.Folio')
            ->whereBetween('EngProduccionEngomado.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where('p.Status', 'Finalizado');
        }

        $registros = $query
            ->select([
                'EngProduccionEngomado.Id',
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
            ->orderBy('p.MaquinaEng')
            ->orderBy('EngProduccionEngomado.Folio')
            ->orderBy('EngProduccionEngomado.NoJulio')
            ->get();

        $porMaquina = [];
        $totalKg = 0;

        foreach ($registros as $r) {
            $label = $this->maquinaLabel($r->MaquinaEng);
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

        ksort($porMaquina);

        return view('modulos.engomado.reportes-engomado', [
            'porMaquina' => $porMaquina,
            'totalKg' => round($totalKg, 2),
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

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
     * Descargar Excel con el reporte
     */
    public function exportarExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('engomado.reportes.engomado')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $query = EngProduccionEngomado::query()
            ->join('EngProgramaEngomado as p', 'EngProduccionEngomado.Folio', '=', 'p.Folio')
            ->whereBetween('EngProduccionEngomado.Fecha', [$fechaIni, $fechaFin]);

        if ($soloFinalizados) {
            $query->where('p.Status', 'Finalizado');
        }

        $registros = $query
            ->select([
                'EngProduccionEngomado.Id',
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
            ->orderBy('p.MaquinaEng')
            ->orderBy('EngProduccionEngomado.Folio')
            ->orderBy('EngProduccionEngomado.NoJulio')
            ->get();

        $porMaquina = [];
        foreach ($registros as $r) {
            $label = $this->maquinaLabel($r->MaquinaEng);
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

        ksort($porMaquina);
        $porMaquina = array_values($porMaquina);

        $fechaStr = Carbon::parse($fechaIni)->format('Ymd') . '-' . Carbon::parse($fechaFin)->format('Ymd');
        $filename = "reporte-engomado-{$fechaStr}.xlsx";

        return Excel::download(new ReportesEngomadoExport($porMaquina), $filename);
    }
}
