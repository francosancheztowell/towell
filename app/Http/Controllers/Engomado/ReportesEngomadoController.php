<?php

namespace App\Http\Controllers\Engomado;

use Illuminate\Support\Facades\DB;
use App\Exports\BpmEngomadoExport;
use App\Exports\ControlMermaExport;
use App\Exports\ReporteResumenSemanalEngomadoExport;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngBpmModel;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Services\Engomado\ControlMermaReportService;
use Carbon\Carbon;
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
                'nombre' => 'Produccion OEE-URD-ENG',
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
            [
                'nombre' => 'Control Merma',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.control-merma'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Resumen Engomado',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.resumen-engomado'),
                'disponible' => true,
            ],
        ];

        return view('modulos.engomado.reportes-engomado-index', ['reportes' => $reportes]);
    }

    public function reporteControlMerma(Request $request, ControlMermaReportService $service)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reportes-control-merma', [
                'filas' => collect(),
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
            ]);
        }

        $filas = $service->build($fechaIni, $fechaFin);

        return view('modulos.engomado.reportes-control-merma', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function exportarControlMermaExcel(Request $request, ControlMermaReportService $service)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.control-merma')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $filas = $service->build($fechaIni, $fechaFin);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $fileName = 'control-merma-'.$fechaIniCarbon->format('Ymd').'-'.$fechaFinCarbon->format('Ymd').'.xlsx';

        return Excel::download(new ControlMermaExport($filas), $fileName);
    }

    public function reporteBpm(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reportes-bpm-engomado', [
                'filas' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $filas = $this->filasBpm($fechaIni, $fechaFin, $soloFinalizados);

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

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.bpm')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $filas = $this->filasBpm($fechaIni, $fechaFin, $soloFinalizados);

        $fileName = 'bpm-engomado-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new BpmEngomadoExport($filas), $fileName);
    }


    /**
     * Filas del reporte BPM: una por linea de checklist, con la cabecera repetida
     * (es el formato que consumen la vista y el Excel).
     *
     * Cabeceras y lineas se consultan por separado en vez de con un leftJoin: asi los
     * 11 campos de cabecera viajan una vez por folio y no una vez por linea.
     */
    private function filasBpm(string $fechaIni, string $fechaFin, bool $soloFinalizados): Collection
    {
        $cabeceras = EngBpmModel::query()
            ->from('EngBPM')
            // Fecha es datetime: intervalo semiabierto [ini, fin+1dia) para no perder
            // los registros del ultimo dia capturados despues de medianoche.
            ->where('Fecha', '>=', $fechaIni)
            ->where('Fecha', '<', Carbon::parse($fechaFin)->addDay()->toDateString())
            ->when($soloFinalizados, fn ($q) => $q->whereIn('Status', ['Terminado', 'Autorizado']))
            ->orderBy('Folio')
            ->get([
                'Folio', 'Status', 'Fecha',
                'CveEmplEnt', 'NombreEmplEnt', 'TurnoEntrega',
                'CveEmplRec', 'NombreEmplRec', 'TurnoRecibe',
                'CveEmplAutoriza', 'NomEmplAutoriza',
            ]);

        if ($cabeceras->isEmpty()) {
            return collect();
        }

        $lineasPorFolio = DB::table('EngBPMLine')
            ->whereIn('Folio', $cabeceras->pluck('Folio')->all())
            ->orderBy('Orden')
            ->get(['Folio', 'Orden', 'Actividad', 'Valor'])
            ->groupBy('Folio');

        $filas = collect();

        foreach ($cabeceras as $cabecera) {
            $base = [
                'Folio' => $cabecera->Folio,
                'Status' => $cabecera->Status,
                'Fecha' => $cabecera->Fecha,
                'CveEmplEnt' => $this->normalizarClaveNumero($cabecera->CveEmplEnt ?? null),
                'NombreEmplEnt' => $cabecera->NombreEmplEnt,
                'TurnoEntrega' => $cabecera->TurnoEntrega,
                'CveEmplRec' => $this->normalizarClaveNumero($cabecera->CveEmplRec ?? null),
                'NombreEmplRec' => $cabecera->NombreEmplRec,
                'TurnoRecibe' => $cabecera->TurnoRecibe,
                'CveEmplAutoriza' => $this->normalizarClaveNumero($cabecera->CveEmplAutoriza ?? null),
                // La columna se llama NomEmplAutoriza; la vista y el Excel esperan NombreEmplAutoriza.
                'NombreEmplAutoriza' => $cabecera->NomEmplAutoriza,
            ];

            // Un folio sin lineas sigue apareciendo una vez, igual que con el leftJoin.
            $lineas = $lineasPorFolio->get($cabecera->Folio) ?? collect([null]);

            foreach ($lineas as $linea) {
                $filas->push((object) ($base + [
                    'Orden' => $linea->Orden ?? null,
                    'Actividad' => $linea->Actividad ?? null,
                    'Valor' => $linea->Valor ?? null,
                    'ValorTexto' => $this->mapearValorBpm((int) ($linea->Valor ?? 0)),
                ]));
            }
        }

        return $this->marcarInicioPorFolio($filas);
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

    public function reporteResumenEngomado(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reporte-resumen-engomado', [
                'datosSemanales' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
            ]);
        }

        $datosSemanales = $this->buildReporteSemanalData($fechaIni, $fechaFin);

        return view('modulos.engomado.reporte-resumen-engomado', [
            'datosSemanales' => $datosSemanales,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function exportarResumenEngomadoExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.resumen-engomado')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $datosSemanales = $this->buildReporteSemanalData($fechaIni, $fechaFin);

        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);
        $fileName = 'resumen-semanal-engomado-'.$fechaIniCarbon->format('Ymd').'-'.$fechaFinCarbon->format('Ymd').'.xlsx';

        return Excel::download(new ReporteResumenSemanalEngomadoExport($datosSemanales), $fileName);
    }

    private function buildReporteSemanalData(string $fechaIni, string $fechaFin): array
    {
        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin)->endOfDay();

        $producciones = EngProduccionEngomado::query()
            ->with('programa') // Cargar la relación
            ->whereBetween('Fecha', [$fechaIniCarbon, $fechaFinCarbon])
            ->where(function ($query) {
                $query->where('Finalizar', 1)
                    ->orWhereNull('Finalizar');
            })
            ->orderBy('Fecha')
            ->get();

        $porSemana = [];
        $foliosPorSemana = [];

        foreach ($producciones as $prod) {
            $fecha = $prod->Fecha instanceof Carbon ? $prod->Fecha : Carbon::parse($prod->Fecha);
            $weekYear = $fecha->format('W-o');

            if (! isset($porSemana[$weekYear])) {
                $porSemana[$weekYear] = [
                    'semana_label' => 'SEM-'.$fecha->format('W-o'),
                    'total_ordenes' => 0,
                    'total_julios' => 0,
                    'total_kg' => 0,
                    'total_metros' => 0,
                    'total_cuenta' => 0, // Ensure this is initialized
                    'eficiencia' => 0,
                ];
                $foliosPorSemana[$weekYear] = [];
            }

            if (! in_array($prod->Folio, $foliosPorSemana[$weekYear])) {
                $foliosPorSemana[$weekYear][] = $prod->Folio;
                $porSemana[$weekYear]['total_ordenes']++;
            }

            $porSemana[$weekYear]['total_julios']++;
            $porSemana[$weekYear]['total_kg'] += (float) ($prod->KgNeto ?? 0);

            if ($prod->programa) {
                $porSemana[$weekYear]['total_cuenta'] += (float) ($prod->programa->Cuenta ?? 0);
            }

            $metros = 0;
            if ($prod->Metros1) {
                $metros += (float) $prod->Metros1;
            }
            if ($prod->Metros2) {
                $metros += (float) $prod->Metros2;
            }
            if ($prod->Metros3) {
                $metros += (float) $prod->Metros3;
            }
            $porSemana[$weekYear]['total_metros'] += $metros;
        }

        foreach ($porSemana as &$semana) {
            $semana['peso_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_kg'] / $semana['total_julios'] : 0;
            $semana['metros_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_metros'] / $semana['total_julios'] : 0;
            $semana['cuenta_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_cuenta'] / $semana['total_julios'] : 0;
        }

        return array_values($porSemana);
    }

    private function buildReporteResumenData(string $fechaIni, string $fechaFin): array
    {
        $fechaIniCarbon = $this->parseReportDate($fechaIni);
        $fechaFinCarbon = $this->parseReportDate($fechaFin);

        $producciones = EngProduccionEngomado::query()
            ->whereBetween('Fecha', [$fechaIniCarbon, $fechaFinCarbon])
            ->where('Finalizar', 1)
            ->orderBy('Fecha')
            ->orderBy('Folio')
            ->get();

        $porFecha = [];

        foreach ($producciones as $prod) {
            $fecha = $prod->Fecha instanceof Carbon ? $prod->Fecha->format('Y-m-d') : Carbon::parse($prod->Fecha)->format('Y-m-d');

            if (! isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'totalKg' => 0,
                    'porMaquina' => [
                        'WP2' => ['label' => 'WP2', 'filas' => []],
                        'WP3' => ['label' => 'WP3', 'filas' => []],
                    ],
                ];
            }

            $programa = EngProgramaEngomado::where('Folio', $prod->Folio)->first();
            $maquina = $programa->MaquinaEng ?? 'WP2';

            if (! in_array($maquina, ['WP2', 'WP3'])) {
                $maquina = 'WP2';
            }

            $metros = 0;
            if ($prod->Metros1) {
                $metros += (float) $prod->Metros1;
            }
            if ($prod->Metros2) {
                $metros += (float) $prod->Metros2;
            }
            if ($prod->Metros3) {
                $metros += (float) $prod->Metros3;
            }

            $operadores = [];
            if ($prod->CveEmpl1) {
                $operadores[] = (string) $prod->CveEmpl1;
            }
            if ($prod->CveEmpl2) {
                $operadores[] = (string) $prod->CveEmpl2;
            }
            if ($prod->CveEmpl3) {
                $operadores[] = (string) $prod->CveEmpl3;
            }

            $fila = [
                'orden' => $programa->Folio ?? $prod->Folio,
                'julio' => $prod->NoJulio ?? '',
                'p_neto' => $prod->KgNeto ?? 0,
                'metros' => $metros,
                'ope' => implode(', ', $operadores),
            ];

            $porFecha[$fecha]['porMaquina'][$maquina]['filas'][] = $fila;
            $porFecha[$fecha]['totalKg'] += (float) ($prod->KgNeto ?? 0);
        }

        foreach ($porFecha as &$dia) {
            $dia['porMaquina'] = array_values($dia['porMaquina']);
        }

        return $porFecha;
    }

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
}
