<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Ventas\TwHistVtasModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tarjeta Ventas de Trazabilidad: líneas de factura de dbo.TwHistoricosVentas (ReportesTowel)
 * ligadas por IDFLOG. Las notas de crédito vienen en negativo, así que la suma ya es neta.
 */
class TrazabilidadVentasService
{
    private const CACHE_PREFIX = 'trazabilidad_ventas_v1';

    private const CACHE_TTL = 600;

    /** Con ReportesTowel caído no se reintenta en cada render de Livewire. */
    private const CAIDO_TTL = 120;

    /**
     * @param  list<string>  $flogs
     * @return array{cantidades: array<string, float>, importes: array<string, float>, clientes: list<array<string, mixed>>, totalClientes: int, ultima: ?string}|null null = sin Flog o sin conexión
     */
    public function resumen(array $flogs, string $articulo = '', string $tamano = ''): ?array
    {
        $flogs = array_values(array_unique(array_filter(array_map('trim', $flogs))));
        if ($flogs === []) {
            return null;
        }
        sort($flogs);

        $claveCaido = self::CACHE_PREFIX.'_caido_'.app()->environment();
        if (Cache::has($claveCaido)) {
            return null;
        }

        $clave = self::CACHE_PREFIX.'_'.app()->environment().'_'.md5(implode("\0", [...$flogs, '|', $articulo, $tamano]));

        try {
            return Cache::remember($clave, self::CACHE_TTL, fn (): array => self::resumir($this->consultar($flogs, $articulo, $tamano)));
        } catch (Throwable $e) {
            report($e);
            Cache::put($claveCaido, true, self::CAIDO_TTL);

            return null;
        }
    }

    /**
     * @param  list<string>  $flogs
     */
    private function consultar(array $flogs, string $articulo, string $tamano): Collection
    {
        $filas = collect();

        // ponytail: la tabla es un heap sin índices (~0.7 s el escaneo); la caché de 10 min lo absorbe.
        foreach (array_chunk($flogs, 1000) as $lote) {
            $filas = $filas->concat(TwHistVtasModel::query()->toBase()
                // selectRaw: agregados con alias que lee resumir().
                ->selectRaw('CUSTNAME AS cliente, UNITID AS unidad, CURRENCYCODE AS moneda,
                    SUM(QTY) AS cantidad, SUM(AMOUNTNETO) AS importe, MAX(FECHAFACTURA) AS ultima')
                ->whereIn('IDFLOG', $lote)
                ->when($articulo !== '', fn ($query) => $query->where('ITEMID', $articulo))
                ->when($tamano !== '', fn ($query) => $query->where('INVENTSIZEID', $tamano))
                ->groupBy('CUSTNAME', 'UNITID', 'CURRENCYCODE')
                ->get());
        }

        return $filas;
    }

    /**
     * Junta las filas por cliente + unidad + moneda (un mismo cliente puede salir en varios lotes).
     * Cantidades e importes se totalizan por unidad y por moneda: no se suman pza con kg ni MXP con USD.
     */
    public static function resumir(Collection $filas): array
    {
        $clientes = $filas
            ->map(fn ($fila): array => [
                'cliente' => trim((string) $fila->cliente) ?: '—',
                'unidad' => mb_strtolower(trim((string) $fila->unidad)),
                'moneda' => trim((string) $fila->moneda),
                'cantidad' => (float) $fila->cantidad,
                'importe' => (float) $fila->importe,
                'ultima' => $fila->ultima,
            ])
            ->groupBy(fn (array $fila): string => $fila['cliente']."\0".$fila['unidad']."\0".$fila['moneda'])
            ->map(fn (Collection $grupo): array => [
                ...$grupo->first(),
                'cantidad' => $grupo->sum('cantidad'),
                'importe' => $grupo->sum('importe'),
                'ultima' => $grupo->max('ultima'),
            ])
            ->sortByDesc('importe')
            ->values();

        $ultima = $clientes->max('ultima');

        return [
            'cantidades' => $clientes->groupBy('unidad')->map->sum('cantidad')->all(),
            'importes' => $clientes->groupBy('moneda')->map->sum('importe')->all(),
            'clientes' => $clientes->take(5)->all(),
            'totalClientes' => $clientes->pluck('cliente')->unique()->count(),
            'ultima' => $ultima ? Carbon::parse($ultima)->format('d/m/Y') : null,
        ];
    }
}
