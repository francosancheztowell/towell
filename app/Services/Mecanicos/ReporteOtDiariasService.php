<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Sistema\Usuario;
use Carbon\Carbon;
use InvalidArgumentException;

final class ReporteOtDiariasService
{
    public const TZ = 'America/Mexico_City';

    public const MINUTOS_JORNADA = 480;

    public const DIAS_SEMANA = 7;

    public const COLOR_VERDE = '375623';

    public const COLOR_ORO = 'FFC000';

    public const COLOR_TEAL = '4BACC6';

    public const COLOR_MAGENTA = 'C659B4';

    public const COLOR_SALMON = 'F8CBAD';

    private const ESTATUS_REALIZADAS = ['Terminado', 'Calificado', 'Autorizado'];

    private const ESTATUS_FIRMADAS = ['Autorizado'];

    /**
     * @return array{
     *     desde: string,
     *     hasta: string,
     *     semana_iso: int,
     *     etiqueta_semana: string,
     *     dias: list<array{fecha: string, etiqueta: string}>
     * }
     */
    public function rangoDesde(string $fechaInicio): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        $desde = Carbon::createFromFormat('Y-m-d', $fechaInicio, self::TZ);
        if ($desde === false || $desde->format('Y-m-d') !== $fechaInicio) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        $desde = $desde->startOfDay();
        $hasta = $desde->copy()->addDays(self::DIAS_SEMANA - 1);
        $dias = [];
        $cursor = $desde->copy();

        while ($cursor->lte($hasta)) {
            $dias[] = [
                'fecha' => $cursor->toDateString(),
                'etiqueta' => $cursor->copy()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            ];
            $cursor->addDay();
        }

        $semanaIso = (int) $desde->isoWeek();

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'semana_iso' => $semanaIso,
            'etiqueta_semana' => 'SEMANA '.$semanaIso,
            'dias' => $dias,
        ];
    }

    /**
     * @param  list<array{cve: string, nombre: string}>  $mecanicos
     * @param  list<array{cve: string, nombre: string, fecha: string, minutos: float, estatus: string}>  $renglones
     * @param  array<string, array{ot_trama?: float|int|string|null, cumplidas_trama?: float|int|string|null, ocupacion_pct?: float|int|string|null}>  $inputs
     * @return array<string, mixed>
     */
    public function armarReporte(string $fechaInicio, array $mecanicos, array $renglones, array $inputs = []): array
    {
        $rango = $this->rangoDesde($fechaInicio);
        $fechas = array_column($rango['dias'], 'fecha');
        $filasPorCve = [];

        foreach ($mecanicos as $mecanico) {
            $cve = trim($mecanico['cve']);
            if ($cve === '') {
                continue;
            }
            $filasPorCve[$cve] = $this->filaVacia($cve, trim($mecanico['nombre']), $fechas);
        }

        foreach ($renglones as $renglon) {
            $cve = trim((string) ($renglon['cve'] ?? ''));
            $fecha = $this->fechaRenglon($renglon['fecha'] ?? null);
            if ($cve === '' || $fecha === null || ! in_array($fecha, $fechas, true)) {
                continue;
            }

            if (! isset($filasPorCve[$cve])) {
                $filasPorCve[$cve] = $this->filaVacia($cve, trim((string) ($renglon['nombre'] ?? $cve)), $fechas);
            }

            $minutos = $this->numero($renglon['minutos'] ?? 0);
            $estatus = trim((string) ($renglon['estatus'] ?? ''));
            $filasPorCve[$cve]['dias'][$fecha]['ocupacion'] += $minutos;

            if (in_array($estatus, self::ESTATUS_REALIZADAS, true)) {
                $filasPorCve[$cve]['dias'][$fecha]['realizadas'] += 1.0;
            }
            if (in_array($estatus, self::ESTATUS_FIRMADAS, true)) {
                $filasPorCve[$cve]['dias'][$fecha]['firmadas'] += 1.0;
            }
        }

        $mecanicosFila = [];
        foreach ($filasPorCve as $cve => $fila) {
            $input = $inputs[$cve] ?? [];
            $mecanicosFila[] = $this->aplicarFormulas(
                $fila,
                $this->numero($input['ot_trama'] ?? 0),
                $this->numero($input['cumplidas_trama'] ?? 0),
                $this->numero($input['ocupacion_pct'] ?? 0),
            );
        }

        return [
            ...$rango,
            'mecanicos' => $mecanicosFila,
            'pie' => $this->pie($mecanicosFila),
        ];
    }

    /**
     * @param  array<string, array{ot_trama?: float|int|string|null, cumplidas_trama?: float|int|string|null, ocupacion_pct?: float|int|string|null}>  $inputs
     * @return array<string, mixed>
     */
    public function build(string $fechaInicio, array $inputs = []): array
    {
        $rango = $this->rangoDesde($fechaInicio);

        return $this->armarReporte(
            $fechaInicio,
            $this->catalogoMecanicos(),
            $this->renglonesEnRango($rango['desde'], $rango['hasta']),
            $inputs,
        );
    }

    public function lunesActual(): string
    {
        return Carbon::now(self::TZ)->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function aplicarFormulas(array $fila, float $otTrama, float $cumplidasTrama, float $ocupacionPct): array
    {
        $realizadasSemana = 0.0;
        $firmadasSemana = 0.0;
        $minSemana = 0.0;

        foreach ($fila['dias'] as $dia) {
            $realizadasSemana += $dia['realizadas'];
            $firmadasSemana += $dia['firmadas'];
            $minSemana += $dia['ocupacion'];
        }

        $totalRealizadas = $realizadasSemana + $otTrama;
        $totalCumplidas = $firmadasSemana + $cumplidasTrama;
        $capacidad = self::MINUTOS_JORNADA * self::DIAS_SEMANA;
        $pctCapacidad = $capacidad > 0 ? ($minSemana / $capacidad) * 100 : 0.0;
        $pctCumplimiento = $totalRealizadas == 0.0 ? 0.0 : ($totalCumplidas / $totalRealizadas) * 100;
        $pctOtFinal = ($ocupacionPct + $this->redondear($pctCumplimiento, 1)) / 2;

        return [
            ...$fila,
            'ot_trama' => $this->redondear($otTrama, 1),
            'cumplidas_trama' => $this->redondear($cumplidasTrama, 1),
            'ocupacion_pct' => $this->redondear($ocupacionPct, 1),
            'realizadas_semana' => $this->redondear($realizadasSemana, 1),
            'firmadas_semana' => $this->redondear($firmadasSemana, 1),
            'total_realizadas' => $this->redondear($totalRealizadas, 1),
            'total_cumplidas' => $this->redondear($totalCumplidas, 1),
            'min_semana' => $this->redondear($minSemana, 1),
            'pct_capacidad' => $this->redondear($pctCapacidad, 2),
            'pct_cumplimiento' => $this->redondear($pctCumplimiento, 1),
            'pct_ot_final' => $this->redondear($pctOtFinal, 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{ot_trama: float, cumplidas_trama: float, pct_ot_final: float}
     */
    private function pie(array $filas): array
    {
        $otTrama = 0.0;
        $cumplidasTrama = 0.0;
        $otFinal = 0.0;
        $n = count($filas);

        foreach ($filas as $fila) {
            $otTrama += (float) $fila['ot_trama'];
            $cumplidasTrama += (float) $fila['cumplidas_trama'];
            $otFinal += (float) $fila['pct_ot_final'];
        }

        return [
            'ot_trama' => $this->redondear($otTrama, 1),
            'cumplidas_trama' => $this->redondear($cumplidasTrama, 1),
            'pct_ot_final' => $n === 0 ? 0.0 : $this->redondear($otFinal / $n, 1),
        ];
    }

    /**
     * @param  list<string>  $fechas
     * @return array{cve: string, nombre: string, dias: array<string, array{realizadas: float, firmadas: float, ocupacion: float}>}
     */
    private function filaVacia(string $cve, string $nombre, array $fechas): array
    {
        $dias = [];
        foreach ($fechas as $fecha) {
            $dias[$fecha] = [
                'realizadas' => 0.0,
                'firmadas' => 0.0,
                'ocupacion' => 0.0,
            ];
        }

        return [
            'cve' => $cve,
            'nombre' => $nombre !== '' ? $nombre : $cve,
            'dias' => $dias,
        ];
    }

    /**
     * @return list<array{cve: string, nombre: string}>
     */
    private function catalogoMecanicos(): array
    {
        return Usuario::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(area))) = ?', ['MANTENIMIENTO'])
            ->orderBy('nombre')
            ->get(['numero_empleado', 'nombre'])
            ->map(fn (Usuario $usuario) => [
                'cve' => trim((string) $usuario->numero_empleado),
                'nombre' => trim((string) $usuario->nombre),
            ])
            ->filter(fn (array $mecanico) => $mecanico['cve'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{cve: string, nombre: string, fecha: string, minutos: float, estatus: string}>
     */
    private function renglonesEnRango(string $desde, string $hasta): array
    {
        return MecOrdenTrabajoLineModel::query()
            ->leftJoin('MecOrdenTrabajoTable as t', 't.Folio', '=', 'MecOrdenTrabajoLine.Folio')
            ->whereBetween('MecOrdenTrabajoLine.Fecha', [$desde, $hasta])
            ->get([
                'MecOrdenTrabajoLine.CveOperador',
                'MecOrdenTrabajoLine.NomOperador',
                'MecOrdenTrabajoLine.Fecha',
                'MecOrdenTrabajoLine.TotalMinutos',
                't.Estatus',
            ])
            ->map(function (MecOrdenTrabajoLineModel $linea): array {
                $fecha = $linea->Fecha;
                $fechaStr = $fecha instanceof Carbon ? $fecha->toDateString() : trim((string) $fecha);

                return [
                    'cve' => trim((string) $linea->CveOperador),
                    'nombre' => trim((string) $linea->NomOperador),
                    'fecha' => $fechaStr,
                    'minutos' => $this->numero($linea->TotalMinutos),
                    'estatus' => trim((string) ($linea->Estatus ?? '')),
                ];
            })
            ->all();
    }

    private function fechaRenglon(mixed $fecha): ?string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->toDateString();
        }

        $valor = trim((string) $fecha);
        if ($valor === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            return null;
        }

        return substr($valor, 0, 10);
    }

    private function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) $valor;
    }

    private function redondear(float $valor, int $decimales): float
    {
        return round($valor, $decimales, PHP_ROUND_HALF_UP);
    }
}
