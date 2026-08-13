<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mecanicos\MecActividadesModel;
use App\Models\Mecanicos\MecVerificaMaquinaLineModel;
use App\Models\Planeacion\ReqTelares;
use Carbon\Carbon;
use InvalidArgumentException;

final class ReporteEstadoMaquinaService
{
    public const TZ = 'America/Mexico_City';

    public const VALORES_VALIDOS = ['1', '2', '3'];

    public const COLOR_CELDA = [
        1 => 'F4C7C3',
        2 => 'F8CBAD',
        3 => 'C6EFCE',
    ];

    public const COLOR_SALON = [
        'Jacquard' => 'C6EFCE',
        'Smith' => 'BDD7EE',
        'KM' => 'FFE699',
    ];

    public const COLOR_SALON_DEFAULT = 'D9E2F3';

    /**
     * @return list<array{lunes: string, domingo: string, desde: string, hasta: string, etiqueta: string}>
     */
    public function semanasQueTocanMes(string $mesYm): array
    {
        [$inicioMes, $finMes] = $this->limitesMes($mesYm);
        $cursor = $inicioMes->copy()->startOfWeek(Carbon::MONDAY);
        $semanas = [];

        while ($cursor->lte($finMes)) {
            $lunes = $cursor->copy()->startOfDay();
            $domingo = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

            if ($domingo->gte($inicioMes) && $lunes->lte($finMes)) {
                $rango = $this->rangoPromedio($mesYm, $lunes->toDateString());
                $semanas[] = [
                    'lunes' => $lunes->toDateString(),
                    'domingo' => $domingo->toDateString(),
                    'desde' => $rango['desde'],
                    'hasta' => $rango['hasta'],
                    'etiqueta' => $lunes->locale('es')->translatedFormat('d M').' – '.$domingo->locale('es')->translatedFormat('d M'),
                ];
            }

            $cursor->addWeek();
        }

        return $semanas;
    }

    /**
     * @return array{desde: string, hasta: string, lunes: string, domingo: string}
     */
    public function rangoPromedio(string $mesYm, string $lunesYmd): array
    {
        [$inicioMes, $finMes] = $this->limitesMes($mesYm);
        $lunes = Carbon::parse($lunesYmd, self::TZ)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $domingo = $lunes->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        if ($domingo->lt($inicioMes) || $lunes->gt($finMes)) {
            throw new InvalidArgumentException('La semana no pertenece al mes seleccionado.');
        }

        $desde = $lunes->greaterThan($inicioMes) ? $lunes : $inicioMes;
        $hasta = $domingo->lessThan($finMes) ? $domingo : $finMes;

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'lunes' => $lunes->toDateString(),
            'domingo' => $domingo->toDateString(),
        ];
    }

    public function redondearCalificacion(?float $promedio): int
    {
        if ($promedio === null) {
            return 0;
        }

        return (int) round($promedio, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  array<string, int|string|null>  $prioridades
     * @return array{
     *     mes: string,
     *     lunes: string,
     *     domingo: string,
     *     desde: string,
     *     hasta: string,
     *     salones: list<array{nombre: string, color: string, telares: list<array{id: string, nombre: string}>}>,
     *     actividades: list<array{id: int, nombre: string, prioridad: string, valores: array<string, int>}>
     * }
     */
    public function build(string $mesYm, string $lunesYmd, array $prioridades = []): array
    {
        $rango = $this->rangoPromedio($mesYm, $lunesYmd);
        $salones = $this->salonesConTelares();
        $telaresIds = [];
        foreach ($salones as $salon) {
            foreach ($salon['telares'] as $telar) {
                $telaresIds[] = $telar['id'];
            }
        }

        $promedios = $this->promediosPorCelda($rango['desde'], $rango['hasta']);
        $actividades = [];

        foreach (MecActividadesModel::query()->orderBy('Orden')->orderBy('Id')->get(['Id', 'Actividad']) as $actividad) {
            $id = (int) $actividad->Id;
            $nombre = (string) $actividad->Actividad;
            $valores = [];

            foreach ($telaresIds as $telarId) {
                $clave = $telarId.'|'.$nombre;
                $valores[$telarId] = $this->redondearCalificacion($promedios[$clave] ?? null);
            }

            $actividades[] = [
                'id' => $id,
                'nombre' => $nombre,
                'prioridad' => $this->prioridadLimpia($prioridades[$id] ?? $prioridades[(string) $id] ?? null),
                'valores' => $valores,
            ];
        }

        return [
            'mes' => $mesYm,
            'lunes' => $rango['lunes'],
            'domingo' => $rango['domingo'],
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'salones' => $salones,
            'actividades' => $actividades,
        ];
    }

    public function colorCelda(int $valor): ?string
    {
        return self::COLOR_CELDA[$valor] ?? null;
    }

    public function colorSalon(string $salon): string
    {
        return self::COLOR_SALON[$salon] ?? self::COLOR_SALON_DEFAULT;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function limitesMes(string $mesYm): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $mesYm)) {
            throw new InvalidArgumentException('Mes inválido.');
        }

        $inicioMes = Carbon::createFromFormat('Y-m-d', $mesYm.'-01', self::TZ);
        if ($inicioMes === false) {
            throw new InvalidArgumentException('Mes inválido.');
        }

        $inicioMes = $inicioMes->startOfMonth()->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->startOfDay();

        return [$inicioMes, $finMes];
    }

    /**
     * @return list<array{nombre: string, color: string, telares: list<array{id: string, nombre: string}>}>
     */
    private function salonesConTelares(): array
    {
        $telares = ReqTelares::query()
            ->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'Nombre', 'SalonTejidoId']);

        $agrupados = [];

        foreach ($telares as $telar) {
            $salon = trim((string) $telar->SalonTejidoId);
            if ($salon === '') {
                $salon = 'Sin salón';
            }

            if (! isset($agrupados[$salon])) {
                $agrupados[$salon] = [
                    'nombre' => $salon,
                    'color' => $this->colorSalon($salon),
                    'telares' => [],
                ];
            }

            $agrupados[$salon]['telares'][] = [
                'id' => (string) $telar->NoTelarId,
                'nombre' => (string) $telar->Nombre,
            ];
        }

        return array_values($agrupados);
    }

    /**
     * @return array<string, float>
     */
    private function promediosPorCelda(string $desde, string $hasta): array
    {
        $filas = MecVerificaMaquinaLineModel::query()
            ->join('MecVerificaMaquinaTable as t', 't.Folio', '=', 'MecVerificaMaquinaLine.Folio')
            ->whereBetween('t.Fecha', [$desde, $hasta])
            ->whereIn('MecVerificaMaquinaLine.Valor', self::VALORES_VALIDOS)
            ->groupBy('MecVerificaMaquinaLine.NoTelarId', 'MecVerificaMaquinaLine.Actividad')
            ->selectRaw('MecVerificaMaquinaLine.NoTelarId, MecVerificaMaquinaLine.Actividad, AVG(CAST(MecVerificaMaquinaLine.Valor AS FLOAT)) AS Promedio')
            ->get();

        $promedios = [];
        foreach ($filas as $fila) {
            $promedios[(string) $fila->NoTelarId.'|'.(string) $fila->Actividad] = (float) $fila->Promedio;
        }

        return $promedios;
    }

    private function prioridadLimpia(mixed $valor): string
    {
        $prioridad = trim((string) $valor);
        if (in_array($prioridad, ['1', '2', '3'], true)) {
            return $prioridad;
        }

        return '';
    }
}
