<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReporteInvTelasExport;

class ReporteInvTelasController extends Controller
{
    public const MAX_DIAS = 5;
    private const ORDEN_SECCIONES = [
        [
            'nombre' => 'JACQUARD SULZER',
            'telares' => ['207', '208', '209', '210', '211', '215'],
        ],
        [
            'nombre' => 'JACQUARD SMIT',
            'telares' => ['201', '202', '203', '204', '205', '206', '213', '214'],
        ],
        [
            'nombre' => 'SMIT',
            'telares' => ['305', '306', '307', '308', '309', '310', '311', '312', '313', '314', '315', '316'],
        ],
        [
            'nombre' => 'ITEMA VIEJO',
            'telares' => ['303', '304', '317', '318'],
        ],
        [
            'nombre' => 'ITEMA NUEVO',
            'telares' => ['299', '300', '301', '302', '319', '320'],
        ],
    ];

    /**
     * Vista del reporte con modal para elegir rango de fechas (máx 5 días)
     */
    public function index(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.tejido.reportes.inv-telas', [
                'fechaIni' => null,
                'fechaFin' => null,
                'secciones' => [],
                'dias' => [],
            ]);
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        if ($fechaIniFormateada > $fechaFinFormateada) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'La fecha inicial no puede ser mayor que la final.');
        }

        $diasDiferencia = Carbon::parse($fechaIniFormateada)->diffInDays(Carbon::parse($fechaFinFormateada)) + 1;
        if ($diasDiferencia > self::MAX_DIAS) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'El rango debe ser de máximo ' . self::MAX_DIAS . ' días.');
        }

        $datos = $this->obtenerDatosReporte($fechaIniFormateada, $fechaFinFormateada);

        return view('modulos.tejido.reportes.inv-telas', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
            'secciones' => $datos['secciones'],
            'dias' => $datos['dias'],
        ]);
    }

    /**
     * Exportar Excel del reporte
     */
    public function exportarExcel(Request $request)
    {
        $fechaIni = $request->input('fecha_ini') ?? $request->query('fecha_ini');
        $fechaFin = $request->input('fecha_fin') ?? $request->query('fecha_fin');

        if (!$fechaIni || !$fechaFin) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'Debe seleccionar fecha inicial y fecha final para exportar.');
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        if ($fechaIniFormateada > $fechaFinFormateada) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'La fecha inicial no puede ser mayor que la final.');
        }

        $diasDiferencia = Carbon::parse($fechaIniFormateada)->diffInDays(Carbon::parse($fechaFinFormateada)) + 1;
        if ($diasDiferencia > self::MAX_DIAS) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'El rango debe ser de máximo ' . self::MAX_DIAS . ' días.');
        }

        $nombreArchivo = 'reporte_inv_telas_'
            . Carbon::parse($fechaIniFormateada)->format('d-m-Y')
            . '_a_'
            . Carbon::parse($fechaFinFormateada)->format('d-m-Y')
            . '.xlsx';

        $datos = $this->obtenerDatosReporte($fechaIniFormateada, $fechaFinFormateada);

        return Excel::download(
            new ReporteInvTelasExport($datos['secciones'], $datos['dias']),
            $nombreArchivo
        );
    }

    /**
     * Exportar PDF del reporte
     */
    public function exportarPdf(Request $request)
    {
        $fechaIni = $request->input('fecha_ini') ?? $request->query('fecha_ini');
        $fechaFin = $request->input('fecha_fin') ?? $request->query('fecha_fin');

        if (!$fechaIni || !$fechaFin) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'Debe seleccionar fecha inicial y fecha final para exportar.');
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        if ($fechaIniFormateada > $fechaFinFormateada) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'La fecha inicial no puede ser mayor que la final.');
        }

        $diasDiferencia = Carbon::parse($fechaIniFormateada)->diffInDays(Carbon::parse($fechaFinFormateada)) + 1;
        if ($diasDiferencia > self::MAX_DIAS) {
            return redirect()
                ->route('tejido.reportes.inv-telas')
                ->with('error', 'El rango debe ser de maximo ' . self::MAX_DIAS . ' dias.');
        }

        $datos = $this->obtenerDatosReporte($fechaIniFormateada, $fechaFinFormateada);
        $resumenSecciones = $this->construirResumenPdf($datos['secciones']);
        $logoBase64 = $this->cargarLogoBase64();

        $html = view('modulos.tejido.reportes.inv-telas-pdf', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
            'secciones' => $datos['secciones'],
            'dias' => $datos['dias'],
            'resumenSecciones' => $resumenSecciones,
            'logoBase64' => $logoBase64,
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $filename = 'reporte_inv_telas_' . $fechaIniFormateada . '_a_' . $fechaFinFormateada . '.pdf';

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Construye la estructura de datos del reporte de inventario telas
     */
    protected function obtenerDatosReporte(string $fechaIni, string $fechaFin): array
    {
        $dias = $this->construirDias($fechaIni, $fechaFin);
        $fechasDia = array_column($dias, 'fecha');
        $noTelaresOrdenados = $this->obtenerNoTelaresOrdenados();
        $fallbackReqPorTelar = $this->obtenerFallbackReqPorTelar($noTelaresOrdenados);

        $registros = TejInventarioTelares::where('status', 'Activo')
            ->whereDate('fecha', '>=', $fechaIni)
            ->whereDate('fecha', '<=', $fechaFin)
            ->orderByDesc('fecha')
            ->orderBy('no_telar')
            ->orderBy('tipo')
            ->get(['no_telar', 'tipo', 'cuenta', 'calibre', 'hilo', 'fecha']);

        $agrupadoPorTelar = [];
        foreach ($registros as $r) {
            $noTelar = trim((string) ($r->no_telar ?? ''));
            if ($noTelar === '') {
                continue;
            }

            if (!isset($agrupadoPorTelar[$noTelar])) {
                $agrupadoPorTelar[$noTelar] = $this->crearFilaTelarBase($noTelar, $fechasDia);
            }

            $fibra = trim((string) ($r->hilo ?? ''));
            if ($agrupadoPorTelar[$noTelar]['fibra'] === '' && $fibra !== '') {
                $agrupadoPorTelar[$noTelar]['fibra'] = $fibra;
            }

            $calibre = $this->formatearCalibre($r->calibre ?? null);
            if ($agrupadoPorTelar[$noTelar]['calibre'] === '' && $calibre !== '') {
                $agrupadoPorTelar[$noTelar]['calibre'] = $calibre;
            }

            $tipo = strtoupper(trim((string) ($r->tipo ?? '')));
            $cuenta = trim((string) ($r->cuenta ?? ''));
            if ($tipo === 'RIZO') {
                if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['cuentas_rizo'], true)) {
                    $agrupadoPorTelar[$noTelar]['cuentas_rizo'][] = $cuenta;
                }
            } elseif ($tipo === 'PIE') {
                if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['cuentas_pie'], true)) {
                    $agrupadoPorTelar[$noTelar]['cuentas_pie'][] = $cuenta;
                }
            }

            $fechaStr = $r->fecha ? Carbon::parse($r->fecha)->format('Y-m-d') : null;
            if ($fechaStr && isset($agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr])) {
                if ($tipo === 'RIZO') {
                    if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr]['rizo'], true)) {
                        $agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr]['rizo'][] = $cuenta;
                    }
                } elseif ($tipo === 'PIE') {
                    if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr]['pie'], true)) {
                        $agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr]['pie'][] = $cuenta;
                    }
                }
            }
        }
        unset($r);

        $secciones = [];
        foreach (self::ORDEN_SECCIONES as $defSeccion) {
            $filas = [];
            foreach ($defSeccion['telares'] as $noTelar) {
                if (isset($agrupadoPorTelar[$noTelar])) {
                    $fila = $agrupadoPorTelar[$noTelar];
                } else {
                    $fila = $this->crearFilaTelarBase($noTelar, $fechasDia);
                }
                $fila = $this->aplicarFallbackReqPrograma($fila, $fallbackReqPorTelar[$noTelar] ?? []);
                $filas[] = $this->finalizarFilaTelar($fila);
            }
            $secciones[] = ['nombre' => $defSeccion['nombre'], 'filas' => $filas];
        }

        return [
            'secciones' => $secciones,
            'dias' => $dias,
        ];
    }

    protected function construirDias(string $fechaIni, string $fechaFin): array
    {
        $dias = [];
        $cursor = Carbon::parse($fechaIni);
        $fin = Carbon::parse($fechaFin);

        while ($cursor->lte($fin)) {
            $dias[] = [
                'fecha' => $cursor->format('Y-m-d'),
                'label' => $this->aMayusculas($cursor->copy()->locale('es')->isoFormat('ddd DD-MMM')),
                'dia_nombre' => $this->aMayusculas($cursor->copy()->locale('es')->isoFormat('dddd')),
                'fecha_excel' => $this->aMayusculas($cursor->copy()->locale('es')->isoFormat('DD-MMM')),
            ];
            $cursor->addDay();
        }

        return $dias;
    }

    protected function crearFilaTelarBase(string $noTelar, array $fechasDia): array
    {
        return [
            'no_telar' => $noTelar,
            'cuenta_rizo' => '',
            'cuenta_pie' => '',
            'cuentas_rizo' => [],
            'cuentas_pie' => [],
            'fibra' => '',
            'calibre' => '',
            'por_dia' => array_fill_keys($fechasDia, ['rizo' => [], 'pie' => []]),
        ];
    }

    protected function crearFilaTelarVacia(string $noTelar, array $fechasDia): array
    {
        return $this->finalizarFilaTelar($this->crearFilaTelarBase($noTelar, $fechasDia));
    }

    protected function finalizarFilaTelar(array $fila): array
    {
        $fila['cuenta_rizo'] = implode('/', $fila['cuentas_rizo'] ?? []);
        $fila['cuenta_pie'] = implode('/', $fila['cuentas_pie'] ?? []);

        foreach ($fila['por_dia'] as $fecha => $detalle) {
            if (!is_array($detalle)) {
                $fila['por_dia'][$fecha] = '';
                continue;
            }

            $partes = [];
            $rizo = $detalle['rizo'] ?? [];
            $pie = $detalle['pie'] ?? [];

            if (!empty($rizo)) {
                $partes[] = 'R: ' . implode('/', $rizo);
            }
            if (!empty($pie)) {
                $partes[] = 'P: ' . implode('/', $pie);
            }

            $fila['por_dia'][$fecha] = implode(' | ', $partes);
        }

        unset($fila['cuentas_rizo'], $fila['cuentas_pie']);

        return $fila;
    }

    protected function formatearCalibre(mixed $calibre): string
    {
        if ($calibre === null || $calibre === '') {
            return '';
        }

        return number_format((float) $calibre, 2, '.', '');
    }

    protected function aMayusculas(string $texto): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($texto, 'UTF-8')
            : strtoupper($texto);
    }

    protected function construirResumenPdf(array $secciones): array
    {
        $resumen = [];

        foreach ($secciones as $seccion) {
            $grupos = [];
            $telaresSeccion = [];

            foreach ($seccion['filas'] as $fila) {
                $noTelar = trim((string) ($fila['no_telar'] ?? ''));
                if ($noTelar !== '') {
                    $telaresSeccion[] = $noTelar;
                }

                $fibra = trim((string) ($fila['fibra'] ?? ''));
                $calibre = trim((string) ($fila['calibre'] ?? ''));
                $key = implode('|', [$fibra, $calibre]);

                if (!isset($grupos[$key])) {
                    $grupos[$key] = [
                        'fibra' => $fibra,
                        'calibre' => $calibre,
                        'cuentas_rizo' => [],
                        'cuentas_pie' => [],
                    ];
                }

                foreach ($this->normalizarCuentas((string) ($fila['cuenta_rizo'] ?? '')) as $cuentaRizo) {
                    if (!in_array($cuentaRizo, $grupos[$key]['cuentas_rizo'], true)) {
                        $grupos[$key]['cuentas_rizo'][] = $cuentaRizo;
                    }
                }

                foreach ($this->normalizarCuentas((string) ($fila['cuenta_pie'] ?? '')) as $cuentaPie) {
                    if (!in_array($cuentaPie, $grupos[$key]['cuentas_pie'], true)) {
                        $grupos[$key]['cuentas_pie'][] = $cuentaPie;
                    }
                }
            }

            foreach ($grupos as &$grupo) {
                sort($grupo['cuentas_rizo']);
                sort($grupo['cuentas_pie']);
                $rizo = empty($grupo['cuentas_rizo']) ? '-' : implode('/', $grupo['cuentas_rizo']);
                $pie = empty($grupo['cuentas_pie']) ? '-' : implode('/', $grupo['cuentas_pie']);
                $grupo['cuenta_unificada'] = 'R: ' . $rizo . ' | P: ' . $pie;
            }
            unset($grupo);

            $lineas = array_values($grupos);
            usort($lineas, function (array $a, array $b): int {
                $cmpFibra = strcmp((string) $a['fibra'], (string) $b['fibra']);
                if ($cmpFibra !== 0) {
                    return $cmpFibra;
                }
                return strcmp((string) $a['calibre'], (string) $b['calibre']);
            });

            $telaresSeccion = array_values(array_unique($telaresSeccion));
            sort($telaresSeccion);

            $resumen[] = [
                'nombre' => (string) ($seccion['nombre'] ?? ''),
                'telares' => $telaresSeccion,
                'lineas' => $lineas,
            ];
        }

        return $resumen;
    }

    protected function normalizarCuentas(string $texto): array
    {
        $partes = preg_split('/[\\/,\\s]+/', trim($texto)) ?: [];
        $cuentas = [];

        foreach ($partes as $parte) {
            $valor = trim((string) $parte);
            if ($valor !== '') {
                $cuentas[] = $valor;
            }
        }

        return array_values(array_unique($cuentas));
    }

    protected function obtenerNoTelaresOrdenados(): array
    {
        $noTelares = [];
        foreach (self::ORDEN_SECCIONES as $seccion) {
            foreach (($seccion['telares'] ?? []) as $noTelar) {
                $valor = trim((string) $noTelar);
                if ($valor !== '') {
                    $noTelares[] = $valor;
                }
            }
        }

        return array_values(array_unique($noTelares));
    }

    protected function obtenerFallbackReqPorTelar(array $noTelares): array
    {
        if (empty($noTelares)) {
            return [];
        }

        $rows = ReqProgramaTejido::query()
            ->whereIn('NoTelarId', $noTelares)
            ->where('EnProceso', 1)
            ->orderByDesc('Id')
            ->get([
                'NoTelarId',
                'CuentaRizo',
                'CuentaPie',
                'FibraRizo',
                'FibraPie',
                'CalibreRizo',
                'CalibrePie',
            ]);

        $fallback = [];
        foreach ($rows as $row) {
            $noTelar = trim((string) ($row->NoTelarId ?? ''));
            if ($noTelar === '' || isset($fallback[$noTelar])) {
                continue;
            }

            $fibra = $this->primerNoVacio([
                (string) ($row->FibraRizo ?? ''),
                (string) ($row->FibraPie ?? ''),
            ]);

            $calibreRaw = $this->primerNoNulo([
                $row->CalibreRizo ?? null,
                $row->CalibrePie ?? null,
            ]);

            $fallback[$noTelar] = [
                'fibra' => $fibra,
                'calibre' => $this->formatearCalibre($calibreRaw),
                'cuenta_rizo' => trim((string) ($row->CuentaRizo ?? '')),
                'cuenta_pie' => trim((string) ($row->CuentaPie ?? '')),
            ];
        }

        return $fallback;
    }

    protected function aplicarFallbackReqPrograma(array $fila, array $fallback): array
    {
        if (($fila['fibra'] ?? '') === '' && ($fallback['fibra'] ?? '') !== '') {
            $fila['fibra'] = (string) $fallback['fibra'];
        }

        if (($fila['calibre'] ?? '') === '' && ($fallback['calibre'] ?? '') !== '') {
            $fila['calibre'] = (string) $fallback['calibre'];
        }

        $cuentaRizo = trim((string) ($fallback['cuenta_rizo'] ?? ''));
        if ($cuentaRizo !== '' && !in_array($cuentaRizo, $fila['cuentas_rizo'] ?? [], true)) {
            $fila['cuentas_rizo'][] = $cuentaRizo;
        }

        $cuentaPie = trim((string) ($fallback['cuenta_pie'] ?? ''));
        if ($cuentaPie !== '' && !in_array($cuentaPie, $fila['cuentas_pie'] ?? [], true)) {
            $fila['cuentas_pie'][] = $cuentaPie;
        }

        return $fila;
    }

    protected function primerNoVacio(array $valores): string
    {
        foreach ($valores as $valor) {
            $texto = trim((string) $valor);
            if ($texto !== '') {
                return $texto;
            }
        }
        return '';
    }

    protected function primerNoNulo(array $valores): mixed
    {
        foreach ($valores as $valor) {
            if ($valor !== null && $valor !== '') {
                return $valor;
            }
        }
        return null;
    }

    protected function cargarLogoBase64(): ?string
    {
        $logoPath = public_path('images/fondosTowell/logo.png');
        if (!is_file($logoPath) || !is_readable($logoPath)) {
            return null;
        }

        $logoData = file_get_contents($logoPath);
        if ($logoData === false || $logoData === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($logoData);
    }
}
