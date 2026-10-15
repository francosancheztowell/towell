<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Sistema\Usuario;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Reporte "OT x Fecha": qué hizo cada mecánico, cuándo y cuánto tardó.
 *
 * Cada renglón de OT guarda una sola Fecha (el día en que empezó) más HoraInicial y
 * HoraFinal. Si el trabajo cruza la medianoche (23:50 → 00:05) cuenta en el día en que
 * empezó, así que filtrar por MecOrdenTrabajoLine.Fecha ya respeta esa regla.
 */
final class ReporteOtFechaService
{
    public const TZ = 'America/Mexico_City';

    private const TRABAJOS = [
        'Ajusto' => 'Ajustó',
        'Reparo' => 'Reparó',
        'Cambio' => 'Cambió',
        'Lubrico' => 'Lubricó',
        'FaltaRefacc' => 'Falta refacción',
    ];

    public function hoy(): string
    {
        return Carbon::now(self::TZ)->toDateString();
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public function validarRango(string $desde, string $hasta): array
    {
        $inicio = $this->fecha($desde);
        $fin = $this->fecha($hasta);

        if ($inicio->gt($fin)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la fecha final.');
        }

        return ['desde' => $inicio->toDateString(), 'hasta' => $fin->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $desde, string $hasta, string $mecanico = ''): array
    {
        $rango = $this->validarRango($desde, $hasta);
        $mecanico = trim($mecanico);

        $reporte = $this->armarReporte($rango, $this->renglonesEnRango($rango['desde'], $rango['hasta'], $mecanico));
        $reporte['mecanico'] = $mecanico;
        $reporte['mecanico_nombre'] = $mecanico === '' ? '' : $this->nombreMecanico($mecanico, $reporte['mecanicos']);

        return $reporte;
    }

    /**
     * build() sin excepción: el rango inválido llega como mensaje para la vista o el 422.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    public function intentar(string $desde, string $hasta, string $mecanico = ''): array
    {
        try {
            return [$this->build($desde, $hasta, $mecanico), null];
        } catch (InvalidArgumentException $exception) {
            return [null, $exception->getMessage()];
        }
    }

    /**
     * Agrupa por mecánico (ordenados por nombre) y ordena sus trabajos por fecha y hora de inicio.
     *
     * @param  array{desde: string, hasta: string}  $rango
     * @param  list<array<string, mixed>>  $renglones
     * @return array<string, mixed>
     */
    public function armarReporte(array $rango, array $renglones): array
    {
        $grupos = [];
        foreach ($renglones as $renglon) {
            $fila = $this->fila($renglon);
            $cve = trim((string) ($renglon['cve'] ?? ''));
            $grupos[$cve] ??= ['cve' => $cve, 'nombre' => '', 'renglones' => []];
            if ($grupos[$cve]['nombre'] === '') {
                $grupos[$cve]['nombre'] = trim((string) ($renglon['nombre'] ?? ''));
            }
            $grupos[$cve]['renglones'][] = $fila;
        }

        $mecanicos = array_map(fn (array $grupo): array => $this->resumenMecanico($grupo), array_values($grupos));
        usort($mecanicos, fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        return [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'mecanicos' => $mecanicos,
            'total_ots' => array_sum(array_column($mecanicos, 'total_ots')),
            'total_trabajos' => array_sum(array_column($mecanicos, 'total_trabajos')),
            'total_minutos' => array_sum(array_column($mecanicos, 'total_minutos')),
        ];
    }

    /**
     * Mecánicos para el filtro: usuarios del área MANTENIMIENTO (misma fuente que OT Diarias).
     *
     * @return list<array{cve: string, nombre: string}>
     */
    public function catalogoMecanicos(): array
    {
        return Usuario::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(area))) = ?', ['MANTENIMIENTO'])
            ->orderBy('nombre')
            ->get(['numero_empleado', 'nombre'])
            ->map(fn (Usuario $usuario): array => [
                'cve' => trim((string) $usuario->numero_empleado),
                'nombre' => trim((string) $usuario->nombre),
            ])
            ->filter(fn (array $mecanico): bool => $mecanico['cve'] !== '')
            ->values()
            ->all();
    }

    public static function duracion(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos.' min';
        }

        return intdiv($minutos, 60).' h '.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT).' min';
    }

    private function fecha(string $valor): Carbon
    {
        $valor = trim($valor);
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1
            ? Carbon::createFromFormat('Y-m-d', $valor, self::TZ)
            : false;

        if (! $fecha instanceof Carbon || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        return $fecha->startOfDay();
    }

    /**
     * @param  array{cve: string, nombre: string, renglones: list<array<string, mixed>>}  $grupo
     * @return array<string, mixed>
     */
    private function resumenMecanico(array $grupo): array
    {
        $renglones = $grupo['renglones'];
        usort($renglones, fn (array $a, array $b): int => [$a['fecha'], $a['hora_inicio']] <=> [$b['fecha'], $b['hora_inicio']]);

        return [
            'cve' => $grupo['cve'],
            'nombre' => $grupo['nombre'] !== '' ? $grupo['nombre'] : $grupo['cve'],
            'total_ots' => count(array_unique(array_column($renglones, 'folio'))),
            'total_trabajos' => count($renglones),
            'total_minutos' => array_sum(array_column($renglones, 'minutos')),
            'renglones' => $renglones,
        ];
    }

    /**
     * @param  array<string, mixed>  $renglon
     * @return array<string, mixed>
     */
    private function fila(array $renglon): array
    {
        $inicio = $this->hora($renglon['hora_inicio'] ?? null);
        $fin = $this->hora($renglon['hora_fin'] ?? null);
        $total = $renglon['total_minutos'] ?? null;

        return [
            'fecha' => substr(trim((string) ($renglon['fecha'] ?? '')), 0, 10),
            'folio' => trim((string) ($renglon['folio'] ?? '')),
            'telar' => trim((string) ($renglon['telar'] ?? '')),
            'falla' => trim((string) ($renglon['falla'] ?? '')),
            'trabajo' => $this->trabajo((array) ($renglon['trabajos'] ?? [])),
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'cruza_dia' => $inicio !== '' && $fin !== '' && $fin < $inicio,
            'minutos' => $total !== null && $total !== '' ? (int) $total : $this->minutosEntre($inicio, $fin),
            'estatus' => trim((string) ($renglon['estatus'] ?? '')) ?: 'Activo',
            'comentarios' => trim((string) ($renglon['comentarios'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $trabajos
     */
    private function trabajo(array $trabajos): string
    {
        $marcados = array_filter(
            self::TRABAJOS,
            fn (string $campo): bool => filter_var($trabajos[$campo] ?? false, FILTER_VALIDATE_BOOLEAN),
            ARRAY_FILTER_USE_KEY,
        );

        return implode(', ', $marcados);
    }

    /** SQL Server entrega TIME como "23:50:00.0000000"; el reporte solo usa HH:MM. */
    private function hora(mixed $valor): string
    {
        $valor = trim((string) $valor);

        return preg_match('/^\d{1,2}:\d{2}/', $valor) === 1 ? substr(str_pad($valor, 8, '0', STR_PAD_LEFT), 0, 5) : '';
    }

    /** Si el fin es menor que el inicio, el trabajo terminó al día siguiente. */
    private function minutosEntre(string $inicio, string $fin): int
    {
        if ($inicio === '' || $fin === '') {
            return 0;
        }

        [$hi, $mi] = array_map('intval', explode(':', $inicio));
        [$hf, $mf] = array_map('intval', explode(':', $fin));
        $minutos = ($hf * 60 + $mf) - ($hi * 60 + $mi);

        return $minutos < 0 ? $minutos + 1440 : $minutos;
    }

    /**
     * @param  list<array<string, mixed>>  $mecanicos
     */
    private function nombreMecanico(string $cve, array $mecanicos): string
    {
        foreach ($mecanicos as $mecanico) {
            if ($mecanico['cve'] === $cve) {
                return $mecanico['nombre'];
            }
        }

        $usuario = Usuario::query()->where('numero_empleado', $cve)->first(['nombre']);

        return $usuario !== null ? trim((string) $usuario->nombre) : $cve;
    }

    /**
     * Renglones capturados (con mecánico) cuya Fecha cae en el rango.
     *
     * @return list<array<string, mixed>>
     */
    private function renglonesEnRango(string $desde, string $hasta, string $mecanico): array
    {
        return MecOrdenTrabajoLineModel::query()
            ->leftJoin('MecOrdenTrabajoTable as t', 't.Folio', '=', 'MecOrdenTrabajoLine.Folio')
            ->whereBetween('MecOrdenTrabajoLine.Fecha', [$desde, $hasta])
            ->whereNotNull('MecOrdenTrabajoLine.CveOperador')
            ->where('MecOrdenTrabajoLine.CveOperador', '<>', '')
            ->when($mecanico !== '', fn ($query) => $query->where('MecOrdenTrabajoLine.CveOperador', $mecanico))
            ->get([
                'MecOrdenTrabajoLine.Folio',
                'MecOrdenTrabajoLine.CveOperador',
                'MecOrdenTrabajoLine.NomOperador',
                'MecOrdenTrabajoLine.Fecha',
                'MecOrdenTrabajoLine.HoraInicial',
                'MecOrdenTrabajoLine.HoraFinal',
                'MecOrdenTrabajoLine.TotalMinutos',
                'MecOrdenTrabajoLine.Ajusto',
                'MecOrdenTrabajoLine.Reparo',
                'MecOrdenTrabajoLine.Cambio',
                'MecOrdenTrabajoLine.Lubrico',
                'MecOrdenTrabajoLine.FaltaRefacc',
                'MecOrdenTrabajoLine.comentarios',
                't.TelarId',
                't.Falla',
                't.Estatus',
            ])
            ->map(fn (MecOrdenTrabajoLineModel $linea): array => [
                'cve' => $linea->CveOperador,
                'nombre' => $linea->NomOperador,
                'fecha' => $linea->Fecha?->toDateString() ?? '',
                'folio' => $linea->Folio,
                'telar' => $linea->getAttribute('TelarId'),
                'falla' => $linea->getAttribute('Falla'),
                'trabajos' => collect(array_keys(self::TRABAJOS))
                    ->mapWithKeys(fn (string $campo): array => [$campo => $linea->getAttribute($campo)])
                    ->all(),
                'hora_inicio' => $linea->HoraInicial,
                'hora_fin' => $linea->HoraFinal,
                'total_minutos' => $linea->TotalMinutos,
                'estatus' => $linea->getAttribute('Estatus'),
                'comentarios' => $linea->comentarios,
            ])
            ->values()
            ->all();
    }
}
