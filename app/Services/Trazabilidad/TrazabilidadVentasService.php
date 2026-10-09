<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Trazabilidad\TrazaProduccion;
use App\Models\Ventas\TwHistVtasModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ventas de Trazabilidad: líneas de factura de dbo.TwHistoricosVentas (ReportesTowel)
 * ligadas por IDFLOG. Las notas de crédito vienen en negativo, así que la suma ya es neta.
 * AMOUNTNETO = AMOUNT - AMOUNTDES (descuento).
 */
class TrazabilidadVentasService
{
    private const CACHE_PREFIX = 'trazabilidad_ventas_v1';

    private const CACHE_TTL = 600;

    /** Con ReportesTowel caído no se reintenta en cada render de Livewire. */
    private const CAIDO_TTL = 120;

    /** Almacenes de TrazaProduccion que cuentan como producto terminado. */
    private const ALMACENES_TERMINADO = ['Ent Prod Term', 'Felpas Prod Term'];

    /**
     * @param  list<string>  $flogs
     * @return array{cantidades: array<string, float>, importes: array<string, float>, clientes: list<array<string, mixed>>, totalClientes: int, ultima: ?string}|null null = sin Flog o sin conexión
     */
    public function resumen(array $flogs, string $articulo = '', string $tamano = ''): ?array
    {
        return $this->conCache('resumen', $flogs, $articulo, $tamano, fn (array $flogs): array => self::resumir(
            $this->consultar($flogs, $articulo, $tamano)
        ));
    }

    /**
     * Detalle: facturas, facturación por mes, valor del pedido y reparto por cliente.
     *
     * @param  list<string>  $flogs
     * @return array<string, mixed>|null null = sin Flog o sin conexión
     */
    public function detalle(array $flogs, string $flog = '', string $articulo = '', string $tamano = ''): ?array
    {
        return $this->conCache('detalle_v2', $flogs, $articulo, $tamano, fn (array $flogs): array => self::armarDetalle(
            $this->consultarLineas($flogs, $articulo, $tamano),
            $this->producidoTerminado($flog, $articulo, $tamano),
        ));
    }

    /**
     * @param  list<string>  $flogs
     */
    private function conCache(string $tipo, array $flogs, string $articulo, string $tamano, callable $calcular): ?array
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

        $clave = self::CACHE_PREFIX.'_'.$tipo.'_'.app()->environment().'_'.md5(implode("\0", [...$flogs, '|', $articulo, $tamano]));

        try {
            return Cache::remember($clave, self::CACHE_TTL, fn (): array => $calcular($flogs));
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
        // ponytail: lee del índice IX_TwHistoricosVentas_IdFlog (13–39 ms medido); caché de 10 min encima.
        return $this->porLotes($flogs, $articulo, $tamano, fn ($query) => $query
            // selectRaw: agregados con alias que lee resumir().
            ->selectRaw('CUSTNAME AS cliente, UNITID AS unidad, CURRENCYCODE AS moneda,
                SUM(QTY) AS cantidad, SUM(AMOUNTNETO) AS importe, MAX(FECHAFACTURA) AS ultima')
            ->groupBy('CUSTNAME', 'UNITID', 'CURRENCYCODE'));
    }

    /**
     * Una fila por factura (y unidad/moneda): de ahí salen facturas, meses y clientes.
     *
     * @param  list<string>  $flogs
     */
    private function consultarLineas(array $flogs, string $articulo, string $tamano): Collection
    {
        return $this->porLotes($flogs, $articulo, $tamano, fn ($query) => $query
            // selectRaw: agregados con alias que lee armarDetalle().
            ->selectRaw('INVOICEID AS folio, FECHAFACTURA AS fecha, PURCHASEORDERID AS oc, CUSTNAME AS cliente,
                TIPODOCUMENTO AS tipo, CURRENCYCODE AS moneda, UNITID AS unidad,
                SUM(QTY) AS cantidad, SUM(AMOUNT) AS bruto, SUM(AMOUNTDES) AS descuento, SUM(AMOUNTNETO) AS neto')
            ->groupBy('INVOICEID', 'FECHAFACTURA', 'PURCHASEORDERID', 'CUSTNAME', 'TIPODOCUMENTO', 'CURRENCYCODE', 'UNITID'));
    }

    /**
     * @param  list<string>  $flogs
     */
    private function porLotes(array $flogs, string $articulo, string $tamano, callable $armar): Collection
    {
        $filas = collect();

        foreach (array_chunk($flogs, 1000) as $lote) {
            $query = TwHistVtasModel::query()->toBase()
                ->whereIn('IDFLOG', $lote)
                ->when($articulo !== '', fn ($query) => $query->where('ITEMID', $articulo))
                ->when($tamano !== '', fn ($query) => $query->where('INVENTSIZEID', $tamano));
            $filas = $filas->concat($armar($query)->get());
        }

        return $filas;
    }

    /**
     * Piezas que entraron a producto terminado con los mismos filtros.
     */
    private function producidoTerminado(string $flog, string $articulo, string $tamano): float
    {
        return (float) TrazaProduccion::query()
            ->filtrados(['flog' => $flog, 'articulo' => $articulo, 'tamano' => $tamano])
            // CAST: NombreAlmacen es VARCHAR y la 2a llave de IX_TrazaProduccion_Flogs_Cubre (ver filtrados()).
            ->whereRaw('NombreAlmacen IN (CAST(? AS varchar(60)), CAST(? AS varchar(60)))', self::ALMACENES_TERMINADO)
            ->sum('Cantidad');
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

    /**
     * @param  Collection<int, object>  $lineas  una fila por factura (consultarLineas)
     * @param  float  $producido  piezas en producto terminado
     * @return array<string, mixed>
     */
    public static function armarDetalle(Collection $lineas, float $producido): array
    {
        $lineas = $lineas->map(fn ($fila): array => [
            'folio' => trim((string) $fila->folio),
            'fecha' => substr((string) $fila->fecha, 0, 10),
            'oc' => trim((string) $fila->oc),
            'cliente' => trim((string) $fila->cliente) ?: '—',
            'notaCredito' => mb_strtoupper(trim((string) $fila->tipo)) === 'NOTA CREDITO',
            'moneda' => trim((string) $fila->moneda),
            'unidad' => mb_strtolower(trim((string) $fila->unidad)),
            'cantidad' => (float) $fila->cantidad,
            'bruto' => (float) $fila->bruto,
            'descuento' => (float) $fila->descuento,
            'neto' => (float) $fila->neto,
        ]);

        // La unidad y la moneda con más volumen mandan en las gráficas: no se suman pza con kg ni MXP con USD.
        $dominante = static fn (string $campo, string $medida): string => (string) $lineas->groupBy($campo)
            ->map(fn (Collection $g): float => abs($g->sum($medida)))->sortDesc()->keys()->first();
        $unidad = $dominante('unidad', 'cantidad') ?: 'pza';
        $moneda = $dominante('moneda', 'neto') ?: 'MXP';

        $facturas = $lineas
            ->groupBy(fn (array $l): string => $l['folio']."\0".$l['moneda'])
            ->map(fn (Collection $g): array => [
                ...collect($g->first())->only(['folio', 'fecha', 'oc', 'cliente', 'notaCredito', 'moneda'])->all(),
                'cantidad' => $g->where('unidad', $unidad)->sum('cantidad'),
                'neto' => $g->sum('neto'),
            ])
            ->sortByDesc('fecha')
            ->values();

        $meses = $lineas->groupBy(fn (array $l): string => substr($l['fecha'], 0, 7))
            ->sortKeys()
            ->map(fn (Collection $g, string $mes): array => [
                'mes' => $mes,
                'neto' => $g->where('moneda', $moneda)->sum('neto'),
                'cantidad' => $g->where('unidad', $unidad)->sum('cantidad'),
            ])
            ->values();

        $clientes = $lineas->where('moneda', $moneda)->groupBy('cliente')
            ->map(fn (Collection $g, string $cliente): array => ['cliente' => $cliente, 'neto' => $g->sum('neto')])
            ->sortByDesc('neto')
            ->values();

        // Precio neto promedio por unidad: con él se estima el valor de lo que falta por facturar.
        $base = $lineas->where('unidad', $unidad)->where('moneda', $moneda);
        $vendidoBase = $base->sum('cantidad');

        $fechas = $lineas->pluck('fecha')->sort()->values();

        return [
            'unidad' => $unidad,
            'moneda' => $moneda,
            'precio' => $vendidoBase > 0 ? $base->sum('neto') / $vendidoBase : null,
            'facturas' => $facturas->all(),
            'meses' => $meses->all(),
            'clientes' => $clientes->all(),
            'totales' => [
                'facturas' => $facturas->where('notaCredito', false)->pluck('folio')->unique()->count(),
                'notasCredito' => $facturas->where('notaCredito', true)->count(),
                'devuelto' => -$lineas->where('notaCredito', true)->where('unidad', $unidad)->sum('cantidad'),
                'clientes' => $lineas->pluck('cliente')->unique()->count(),
                'vendido' => $lineas->where('unidad', $unidad)->sum('cantidad'),
                // Producción cuenta piezas: solo se compara cuando se vende por pieza.
                'producido' => $unidad === 'pza' ? $producido : 0.0,
                'importes' => $lineas->groupBy('moneda')->map(fn (Collection $g): array => [
                    'bruto' => $g->sum('bruto'), 'descuento' => $g->sum('descuento'), 'neto' => $g->sum('neto'),
                ])->all(),
                'primera' => $fechas->first(),
                'ultima' => $fechas->last(),
            ],
        ];
    }
}
