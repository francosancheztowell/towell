<?php

namespace App\Http\Controllers\Engomado;

use App\Exports\BpmEngomadoExport;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngBpmModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ReportesEngomadoController extends Controller
{
    /**
     * Selector de reportes: 03-OEE URD-ENG y Kaizen (usan el controlador de Urdido)
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
                'nombre' => 'Kaizen urd-eng (AX ENGOMADO / AX URDIDO)',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.kaizen'),
                'disponible' => true,
            ],
            [
                'nombre' => 'BPM Engomado',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.bpm'),
                'disponible' => true,
            ],
        ];

        return view('modulos.engomado.reportes-engomado-index', ['reportes' => $reportes]);
    }

    public function reporteBpm(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.engomado.reportes-bpm-engomado', [
                'filas' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $query = EngBpmModel::query()
            ->from('EngBPM as h')
            ->leftJoin('EngBPMLine as l', 'h.Folio', '=', 'l.Folio')
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
                'h.NomEmplAutoriza as NombreEmplAutoriza',
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

        return view('modulos.engomado.reportes-bpm-engomado', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    public function exportarBpmExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (!$fechaIni || !$fechaFin) {
            return redirect()->route('engomado.reportes.bpm')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $query = EngBpmModel::query()
            ->from('EngBPM as h')
            ->leftJoin('EngBPMLine as l', 'h.Folio', '=', 'l.Folio')
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
                'h.NomEmplAutoriza as NombreEmplAutoriza',
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

        $fileName = 'bpm-engomado-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(new BpmEngomadoExport($filas), $fileName);
    }

    private function mapearValorBpm(int $valor): string
    {
        if ($valor === 1) {
            return '☑';
        }
        if ($valor === 2) {
            return '☒';
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
}
