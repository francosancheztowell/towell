<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Exports\ReporteMantenimientoExport;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesMantenimientoController extends Controller
{
    /** "Reportes" existe en varios módulos; aquí se identifica por idrol. */
    private const MODULO = 57;

    /**
     * Selector de reportes (como urdido).
     * GET /mantenimiento/reportes
     */
    public function index()
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a los reportes de mantenimiento.');

        $reportes = [
            [
                'nombre' => 'Fallas y Paros',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('mantenimiento.reportes.fallas-paros'),
                'disponible' => true,
            ],
        ];

        return view('modulos.mantenimiento.reportes-mantenimiento-index', [
            'reportes' => $reportes,
        ]);
    }

    /**
     * Reporte Fallas y Paros con filtro por fechas.
     * GET /mantenimiento/reportes/fallas-paros
     */
    public function reporteFallasParos(Request $request)
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a los reportes de mantenimiento.');

        [$fechaIni, $fechaFin] = $this->rango($request);

        return view('modulos.mantenimiento.reportes-mantenimiento-fallas-paros', [
            'registros' => $this->consulta($fechaIni, $fechaFin)->paginate(50)->withQueryString(),
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
        ]);
    }

    /**
     * Exportar reporte a Excel.
     * GET /mantenimiento/reportes/fallas-paros/excel
     */
    public function exportarExcel(Request $request)
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a los reportes de mantenimiento.');

        [$fechaIni, $fechaFin] = $this->rango($request);

        return Excel::download(
            new ReporteMantenimientoExport($this->consulta($fechaIni, $fechaFin)->get()),
            'Reporte_Mantenimiento_'.now()->format('Y-m-d_His').'.xlsx'
        );
    }

    /**
     * Rango solicitado. Sin fechas se usa el mes en curso: la tabla completa de
     * paros no cabe en una pantalla ni en una hoja de Excel.
     *
     * @return array{0: string, 1: string}
     */
    private function rango(Request $request): array
    {
        $fechaIni = trim((string) $request->query('fecha_ini', ''));
        $fechaFin = trim((string) $request->query('fecha_fin', ''));

        if ($fechaIni === '' || $fechaFin === '') {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        }

        return $fechaIni <= $fechaFin ? [$fechaIni, $fechaFin] : [$fechaFin, $fechaIni];
    }

    private function consulta(string $fechaIni, string $fechaFin): Builder
    {
        return ManFallasParos::query()
            ->whereBetween('Fecha', [$fechaIni, $fechaFin])
            ->orderBy('Fecha')
            ->orderBy('Hora');
    }
}
