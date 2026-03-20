<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Exports\ReporteInvTelasExport;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReporteInvTelasController extends Controller
{
    public const MAX_DIAS = 5;

    private const STATUS_ACTIVO = 'Activo';
    private const COLOR_BLUE = 'blue';
    private const COLOR_ORANGE = 'orange';
    private const COLOR_YELLOW = 'yellow';
    private const SESSION_LIBERAR_DIAS = 'liberar_ordenes_dias';
    private const DEFAULT_LIBERAR_DIAS = 10.999;

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

    private const LEYENDA_COLORES = [
        [
            'color' => self::COLOR_BLUE,
            'label' => 'Azul',
            'descripcion' => 'Reservado',
        ],
        [
            'color' => self::COLOR_ORANGE,
            'label' => 'Naranja',
            'descripcion' => 'Programado',
        ],
        [
            'color' => self::COLOR_YELLOW,
            'label' => 'Amarillo',
            'descripcion' => 'Sin reservar / Sin programar',
        ],
    ];

    /**
     * Vista del reporte con modal para elegir rango de fechas (max 5 dias)
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
                'leyendaColores' => $this->obtenerLeyendaColores(),
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
                ->with('error', 'El rango debe ser de maximo ' . self::MAX_DIAS . ' dias.');
        }

        $datos = $this->obtenerDatosReporte($fechaIniFormateada, $fechaFinFormateada);

        return view('modulos.tejido.reportes.inv-telas', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
            'secciones' => $datos['secciones'],
            'dias' => $datos['dias'],
            'leyendaColores' => $this->obtenerLeyendaColores(),
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
                ->with('error', 'El rango debe ser de maximo ' . self::MAX_DIAS . ' dias.');
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
        $amarilloPorTelarFecha = $this->obtenerAmarilloPorTelarFecha($noTelaresOrdenados, $fechaIni, $fechaFin);

        $registros = TejInventarioTelares::where('status', self::STATUS_ACTIVO)
            ->whereDate('fecha', '>=', $fechaIni)
            ->whereDate('fecha', '<=', $fechaFin)
            ->orderByDesc('fecha')
            ->orderBy('no_telar')
            ->orderBy('tipo')
            ->get([
                'id',
                'no_telar',
                'tipo',
                'cuenta',
                'calibre',
                'hilo',
                'fecha',
                'turno',
                'no_julio',
                'no_orden',
                'Reservado',
                'Programado',
            ]);

        $agrupadoPorTelar = [];
        foreach ($registros as $registro) {
            $noTelar = trim((string) ($registro->no_telar ?? ''));
            if ($noTelar === '') {
                continue;
            }

            if (!isset($agrupadoPorTelar[$noTelar])) {
                $agrupadoPorTelar[$noTelar] = $this->crearFilaTelarBase($noTelar, $fechasDia);
            }

            $fibra = trim((string) ($registro->hilo ?? ''));
            if ($agrupadoPorTelar[$noTelar]['fibra'] === '' && $fibra !== '') {
                $agrupadoPorTelar[$noTelar]['fibra'] = $fibra;
            }

            $calibre = $this->formatearCalibre($registro->calibre ?? null);
            if ($agrupadoPorTelar[$noTelar]['calibre'] === '' && $calibre !== '') {
                $agrupadoPorTelar[$noTelar]['calibre'] = $calibre;
            }

            $tipo = strtoupper(trim((string) ($registro->tipo ?? '')));
            $cuenta = trim((string) ($registro->cuenta ?? ''));
            if ($tipo === 'RIZO') {
                if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['cuentas_rizo'], true)) {
                    $agrupadoPorTelar[$noTelar]['cuentas_rizo'][] = $cuenta;
                }
            } elseif ($tipo === 'PIE') {
                if ($cuenta !== '' && !in_array($cuenta, $agrupadoPorTelar[$noTelar]['cuentas_pie'], true)) {
                    $agrupadoPorTelar[$noTelar]['cuentas_pie'][] = $cuenta;
                }
            }

            $fechaStr = $registro->fecha ? Carbon::parse($registro->fecha)->format('Y-m-d') : null;
            if ($fechaStr && isset($agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr])) {
                $detalleDia = &$agrupadoPorTelar[$noTelar]['por_dia'][$fechaStr];
                $turno = (int) ($registro->turno ?? 1);
                if ($turno < 1 || $turno > 3) {
                    $turno = 1;
                }

                $colorRegistro = $this->resolverColorCeldaInventario($registro);
                $detalleDia['turnos'][$turno]['color'] = $this->priorizarColor(
                    $detalleDia['turnos'][$turno]['color'] ?? null,
                    $colorRegistro
                );

                if ($colorRegistro === self::COLOR_BLUE) {
                    $noJulio = trim((string) ($registro->no_julio ?? ''));
                    if ($noJulio !== '' && !in_array($noJulio, $detalleDia['turnos'][$turno]['no_julios'], true)) {
                        $detalleDia['turnos'][$turno]['no_julios'][] = $noJulio;
                    }
                }

                if ($colorRegistro === self::COLOR_ORANGE) {
                    $noOrden = trim((string) ($registro->no_orden ?? ''));
                    if ($noOrden !== '' && !in_array($noOrden, $detalleDia['turnos'][$turno]['no_ordenes'], true)) {
                        $detalleDia['turnos'][$turno]['no_ordenes'][] = $noOrden;
                    }
                }

                if ($tipo === 'RIZO') {
                    if ($cuenta !== '' && !in_array($cuenta, $detalleDia['turnos'][$turno]['rizo'], true)) {
                        $detalleDia['turnos'][$turno]['rizo'][] = $cuenta;
                    }
                } elseif ($tipo === 'PIE') {
                    if ($cuenta !== '' && !in_array($cuenta, $detalleDia['turnos'][$turno]['pie'], true)) {
                        $detalleDia['turnos'][$turno]['pie'][] = $cuenta;
                    }
                }
                unset($detalleDia);
            }
        }

        $secciones = [];
        foreach (self::ORDEN_SECCIONES as $defSeccion) {
            $filas = [];
            foreach ($defSeccion['telares'] as $noTelar) {
                $fila = $agrupadoPorTelar[$noTelar] ?? $this->crearFilaTelarBase($noTelar, $fechasDia);
                $fila = $this->aplicarFallbackReqPrograma($fila, $fallbackReqPorTelar[$noTelar] ?? []);
                $fila = $this->aplicarColorAmarilloPendiente($fila, $amarilloPorTelarFecha[$noTelar] ?? []);
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
        $turnoBase = [
            1 => ['rizo' => [], 'pie' => [], 'color' => null, 'no_julios' => [], 'no_ordenes' => []],
            2 => ['rizo' => [], 'pie' => [], 'color' => null, 'no_julios' => [], 'no_ordenes' => []],
            3 => ['rizo' => [], 'pie' => [], 'color' => null, 'no_julios' => [], 'no_ordenes' => []],
        ];

        return [
            'no_telar' => $noTelar,
            'cuenta_rizo' => '',
            'cuenta_pie' => '',
            'cuentas_rizo' => [],
            'cuentas_pie' => [],
            'fibra' => '',
            'calibre' => '',
            'por_dia' => array_fill_keys($fechasDia, ['turnos' => $turnoBase]),
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
            if (!is_array($detalle) || !isset($detalle['turnos'])) {
                $fila['por_dia'][$fecha] = ['turnos' => [
                    1 => ['texto' => '', 'color' => null],
                    2 => ['texto' => '', 'color' => null],
                    3 => ['texto' => '', 'color' => null],
                ]];
                continue;
            }

            $turnosFinal = [];
            foreach ([1, 2, 3] as $t) {
                $turnoData = $detalle['turnos'][$t] ?? ['rizo' => [], 'pie' => [], 'color' => null];
                $partes = [];
                $rizo = $turnoData['rizo'] ?? [];
                $pie = $turnoData['pie'] ?? [];

                $tieneAmbos = !empty($rizo) && !empty($pie);
                if (!empty($rizo)) {
                    $partes[] = ($tieneAmbos ? 'R: ' : '') . implode('/', $rizo);
                }
                if (!empty($pie)) {
                    $partes[] = ($tieneAmbos ? 'P: ' : '') . implode('/', $pie);
                }

                $noJulios = $turnoData['no_julios'] ?? [];
                if (!empty($noJulios) && ($turnoData['color'] ?? null) === self::COLOR_BLUE) {
                    $partes[] = implode('/', $noJulios);
                }

                $noOrdenes = $turnoData['no_ordenes'] ?? [];
                if (!empty($noOrdenes) && ($turnoData['color'] ?? null) === self::COLOR_ORANGE) {
                    $partes[] = implode('/', $noOrdenes);
                }

                $turnosFinal[$t] = [
                    'texto' => implode(' | ', $partes),
                    'color' => $turnoData['color'] ?? null,
                ];
            }

            $fila['por_dia'][$fecha] = ['turnos' => $turnosFinal];
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

    protected function obtenerLeyendaColores(): array
    {
        return self::LEYENDA_COLORES;
    }

    protected function resolverColorCeldaInventario(TejInventarioTelares $registro): ?string
    {
        // Reservado: si tiene el flag Reservado o tiene julio asignado
        if ((bool) ($registro->Reservado ?? false) || trim((string) ($registro->no_julio ?? '')) !== '') {
            return self::COLOR_BLUE;
        }

        if ((bool) ($registro->Programado ?? false)) {
            return self::COLOR_ORANGE;
        }

        return self::COLOR_YELLOW;
    }

    protected function obtenerEstadosOrdenesActivas(array $noTelares): array
    {
        if (empty($noTelares)) {
            return ['por_folio' => [], 'por_telar_tipo' => []];
        }

        $objetivo = array_fill_keys($noTelares, true);
        $porFolio = [];
        $porTelarTipo = [];
        $statuses = ['Programado', 'En Proceso', 'Parcial'];

        $procesarOrdenes = function (iterable $rows) use (&$porFolio, &$porTelarTipo, $objetivo): void {
            foreach ($rows as $row) {
                $color = $this->obtenerColorEstadoPorStatusOrden($row->Status ?? null);
                if ($color === null) {
                    continue;
                }

                $folio = trim((string) ($row->Folio ?? ''));
                if ($folio !== '') {
                    $porFolio[$folio] = $this->priorizarColor($porFolio[$folio] ?? null, $color);
                }

                $tipo = $this->normalizarTipo($row->RizoPie ?? null);
                if ($tipo === null) {
                    continue;
                }

                foreach ($this->desglosarTelaresOrden($row->NoTelarId ?? null) as $noTelar) {
                    if (!isset($objetivo[$noTelar])) {
                        continue;
                    }

                    $porTelarTipo[$noTelar][$tipo] = $this->priorizarColor(
                        $porTelarTipo[$noTelar][$tipo] ?? null,
                        $color
                    );
                }
            }
        };

        $procesarOrdenes(
            UrdProgramaUrdido::query()
                ->whereIn('Status', $statuses)
                ->get(['Folio', 'NoTelarId', 'RizoPie', 'Status'])
        );

        $procesarOrdenes(
            EngProgramaEngomado::query()
                ->whereIn('Status', $statuses)
                ->get(['Folio', 'NoTelarId', 'RizoPie', 'Status'])
        );

        return [
            'por_folio' => $porFolio,
            'por_telar_tipo' => $porTelarTipo,
        ];
    }

    protected function obtenerColorEstadoPorStatusOrden(?string $status): ?string
    {
        $statusNormalizado = trim((string) $status);

        if (in_array($statusNormalizado, ['En Proceso', 'Parcial'], true)) {
            return self::COLOR_BLUE;
        }

        if ($statusNormalizado === 'Programado') {
            return self::COLOR_ORANGE;
        }

        return null;
    }

    protected function desglosarTelaresOrden(?string $telaresRaw): array
    {
        $telares = preg_split('/\s*,\s*/', trim((string) $telaresRaw)) ?: [];
        $resultado = [];

        foreach ($telares as $telar) {
            $valor = trim((string) $telar);
            if ($valor !== '') {
                $resultado[] = $valor;
            }
        }

        return array_values(array_unique($resultado));
    }

    protected function obtenerAmarilloPorTelarFecha(array $noTelares, string $fechaIni, string $fechaFin): array
    {
        if (empty($noTelares)) {
            return [];
        }

        $diasLiberacion = $this->obtenerDiasLiberacion();
        $hoy = Carbon::now()->startOfDay();
        $fechaFormula = $hoy->copy()->addDays($diasLiberacion);

        $rows = ReqProgramaTejido::query()
            ->whereIn('NoTelarId', $noTelares)
            ->where(function ($query) {
                $query->whereNull('NoProduccion')
                    ->orWhere('NoProduccion', '');
            })
            ->where(function ($query) {
                $query->whereNull('NoExisteBase')
                    ->orWhere('NoExisteBase', '');
            })
            ->whereNotNull('FechaInicio')
            ->whereDate('FechaInicio', '>=', $fechaIni)
            ->whereDate('FechaInicio', '<=', $fechaFin)
            ->get(['NoTelarId', 'FechaInicio', 'CuentaRizo', 'CuentaPie']);

        $resultado = [];
        foreach ($rows as $row) {
            $noTelar = trim((string) ($row->NoTelarId ?? ''));
            if ($noTelar === '' || empty($row->FechaInicio)) {
                continue;
            }

            $fechaInicio = $row->FechaInicio instanceof Carbon
                ? $row->FechaInicio->copy()->startOfDay()
                : Carbon::parse($row->FechaInicio)->startOfDay();

            if (!$fechaInicio->lt($fechaFormula)) {
                continue;
            }

            $fechaKey = $fechaInicio->format('Y-m-d');
            if (!isset($resultado[$noTelar][$fechaKey])) {
                $resultado[$noTelar][$fechaKey] = [
                    'aplica' => true,
                    'cuenta_rizo' => trim((string) ($row->CuentaRizo ?? '')),
                    'cuenta_pie' => trim((string) ($row->CuentaPie ?? '')),
                ];
            }
        }

        return $resultado;
    }

    protected function obtenerDiasLiberacion(): float
    {
        $dias = (float) session(self::SESSION_LIBERAR_DIAS, self::DEFAULT_LIBERAR_DIAS);
        if ($dias < 0 || $dias > 999.999) {
            $dias = self::DEFAULT_LIBERAR_DIAS;
        }

        return round($dias, 3);
    }

    protected function aplicarColorAmarilloPendiente(array $fila, array $amarilloPorFecha): array
    {
        foreach ($amarilloPorFecha as $fecha => $info) {
            $aplica = is_array($info) ? ($info['aplica'] ?? false) : $info;
            if (!$aplica || !isset($fila['por_dia'][$fecha])) {
                continue;
            }

            // Verificar si algún turno ya tiene datos
            $algunTurnoConDatos = false;
            foreach ([1, 2, 3] as $t) {
                $turnoData = $fila['por_dia'][$fecha]['turnos'][$t] ?? ['rizo' => [], 'pie' => []];
                if (!empty($turnoData['rizo']) || !empty($turnoData['pie'])) {
                    $algunTurnoConDatos = true;
                    break;
                }
            }

            if ($algunTurnoConDatos) {
                // Aplicar amarillo a turnos que tengan datos pero no tengan color más prioritario
                foreach ([1, 2, 3] as $t) {
                    $turnoData = $fila['por_dia'][$fecha]['turnos'][$t] ?? ['rizo' => [], 'pie' => [], 'color' => null];
                    if (!empty($turnoData['rizo']) || !empty($turnoData['pie'])) {
                        $fila['por_dia'][$fecha]['turnos'][$t]['color'] = $this->priorizarColor(
                            $turnoData['color'] ?? null,
                            self::COLOR_YELLOW
                        );
                    }
                }
            } else {
                // Sin datos de inventario: inyectar cuenta del ReqProgramaTejido en turno 1
                $fila['por_dia'][$fecha]['turnos'][1]['color'] = $this->priorizarColor(
                    $fila['por_dia'][$fecha]['turnos'][1]['color'] ?? null,
                    self::COLOR_YELLOW
                );
                if (is_array($info)) {
                    $cuentaRizo = $info['cuenta_rizo'] ?? '';
                    $cuentaPie = $info['cuenta_pie'] ?? '';
                    if ($cuentaRizo !== '') {
                        $fila['por_dia'][$fecha]['turnos'][1]['rizo'][] = $cuentaRizo;
                    }
                    if ($cuentaPie !== '') {
                        $fila['por_dia'][$fecha]['turnos'][1]['pie'][] = $cuentaPie;
                    }
                }
            }
        }

        return $fila;
    }

    protected function priorizarColor(?string $actual, ?string $nuevo): ?string
    {
        if ($nuevo === null || $nuevo === '') {
            return $actual;
        }

        if ($actual === null || $actual === '') {
            return $nuevo;
        }

        $prioridades = [
            self::COLOR_BLUE => 3,
            self::COLOR_ORANGE => 2,
            self::COLOR_YELLOW => 1,
        ];

        return ($prioridades[$nuevo] ?? 0) >= ($prioridades[$actual] ?? 0) ? $nuevo : $actual;
    }

    protected function normalizarTipo(mixed $tipo): ?string
    {
        $valor = mb_strtolower(trim((string) $tipo), 'UTF-8');

        if ($valor === 'rizo') {
            return 'Rizo';
        }

        if ($valor === 'pie') {
            return 'Pie';
        }

        return null;
    }
}
