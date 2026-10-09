<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Trazabilidad\TrazaProduccion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrazabilidadResumenService
{
    public function __construct(
        private TrazabilidadMatrixService $matrixService,
        private TrazabilidadProgramaLookupService $programLookup,
        private TrazabilidadFlogsService $flogsAx,
    ) {}

    /**
     * @param  array{flog?:mixed,articulo?:mixed,tamano?:mixed}  $filtros
     * @param  array<int, array<string, mixed>>  $tablaAvance  Filas de la tarjeta "Programa tejido"
     *                                                         (TrazabilidadProduccionService::buildTablaAvance).
     * @return array<string, mixed>
     */
    public function build(array $filtros, array $summaryValues, array $tablaAvance = []): array
    {
        $query = $this->queryBase($filtros);

        $flogs = collect($summaryValues['flogs'] ?? []);
        $articulos = collect($summaryValues['articulos'] ?? []);
        $tamanos = collect($summaryValues['tamanos'] ?? []);

        $ordenes = (clone $query)
            ->whereNotNull('Orden')
            ->where('Orden', '<>', '')
            ->distinct()
            ->pluck('Orden')
            ->map(fn ($orden) => trim((string) $orden))
            ->filter()
            ->values();

        $programas = $this->programLookup->forOrders($ordenes)->values();
        $fechaInicio = $programas->pluck('FechaInicio')->filter()->min();
        $fechaFin = $programas->pluck('FechaFinal')->filter()->max();
        // Pedido, facturado y pendiente salen juntos de AX para que cuadren entre sí.
        $facturacion = $this->flogsAx->facturacion(
            $flogs->map(fn ($flog): string => (string) $flog)->all(),
            trim((string) ($filtros['articulo'] ?? '')),
            trim((string) ($filtros['tamano'] ?? '')),
        );
        // Sin AX: el pedido cae a lo programado de la tarjeta "Programa tejido" y lo
        // facturado queda sin dato ("—"), no en un 0 que parezca real.
        $pedido = $facturacion['pedido'] ?? ($tablaAvance !== []
            ? (float) collect($tablaAvance)->sum(fn (array $fila): float => (float) ($fila['programado'] ?? 0))
            : ($programas->isNotEmpty()
                ? (float) $programas->sum(fn ($programa) => (float) ($programa->TotalPedido ?? 0))
                : null));
        $facturado = $facturacion['facturado'] ?? null;
        $pendienteFacturacion = $facturacion['porEntregar'] ?? null;
        $produccionPrograma = $programas->isNotEmpty()
            ? (float) $programas->sum(fn ($programa) => (float) ($programa->Produccion ?? 0))
            : null;
        $saldoPedido = $programas->isNotEmpty()
            ? (float) $programas->sum(fn ($programa) => (float) ($programa->SaldoPedido ?? 0))
            : null;

        // Los totales por área ya traen todas las fechas del filtro: se reutilizan
        // como respaldo en vez de lanzar un MIN y un MAX extra sobre la tabla.
        $totalesArea = $this->totalesPorArea(clone $query);

        if (! $fechaInicio) {
            $fechaInicio = $totalesArea->min('Fecha');
        }
        if (! $fechaFin) {
            $fechaFin = $totalesArea->max('Fecha');
        }

        return [
            'flogs' => $this->resumirValores($flogs),
            'articulos' => $this->resumirValores($articulos),
            'tamanos' => $this->resumirValores($tamanos),
            'pedido' => $pedido,
            'facturado' => $facturado,
            'pendienteFacturacion' => $pendienteFacturacion,
            'cancelado' => $facturacion['cancelado'] ?? null,
            'lineasCanceladas' => $facturacion['lineasCanceladas'] ?? 0,
            'produccionPrograma' => $produccionPrograma,
            'saldoPedido' => $saldoPedido,
            'avancePedido' => $pedido > 0 && ! is_null($produccionPrograma)
                ? round(min(100, max(0, $produccionPrograma / $pedido * 100)), 1)
                : null,
            'fechaInicio' => $this->formatearFecha($fechaInicio),
            'fechaFin' => $this->formatearFecha($fechaFin),
            'trazabilidadAreas' => $this->trazabilidadPorArea($totalesArea),
        ];
    }

    /** @param array<string, mixed> $filtros */
    private function queryBase(array $filtros): Builder
    {
        return TrazaProduccion::query()->filtrados($filtros);
    }

    /**
     * Un renglón por área y día del filtro actual.
     *
     * @return Collection<int, object>
     */
    private function totalesPorArea(Builder $query): Collection
    {
        return $query
            ->whereNotNull('NombreAlmacen')
            ->where('NombreAlmacen', '<>', '')
            ->whereNotNull('Fecha')
            ->selectRaw('NombreAlmacen, CAST(Fecha AS date) as Fecha, SUM(Cantidad) as piezas, SUM(Peso) as kilos')
            ->groupByRaw('NombreAlmacen, CAST(Fecha AS date)')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function trazabilidadPorArea(Collection $filas): array
    {
        $totales = $filas->groupBy(fn ($fila) => trim((string) $fila->NombreAlmacen));

        return collect($this->matrixService->areasFijas)
            ->map(function (array $area) use ($totales) {
                $registros = $totales->get($area['nombre'], collect());

                return [
                    'area' => $area['label'] ?? $area['nombre'],
                    // La matriz redondea cada día antes de sumar la fila; el resumen
                    // replica ese cálculo para cuadrar exactamente con el calendario.
                    'piezas' => $registros->isEmpty()
                        ? null
                        : (float) $registros->sum(fn ($fila) => round((float) ($fila->piezas ?? 0), 0)),
                    'kilos' => $registros->isEmpty()
                        ? null
                        : (float) $registros->sum(fn ($fila) => round((float) ($fila->kilos ?? 0), 1)),
                    'fechaInicio' => $registros->isEmpty() ? '—' : $this->formatearFecha($registros->min('Fecha')),
                    'fechaFin' => $registros->isEmpty() ? '—' : $this->formatearFecha($registros->max('Fecha')),
                    'text' => $area['text'],
                    'dot' => $area['dot'],
                    'tint' => $area['tint'],
                ];
            })
            ->filter(fn (array $area) => ! is_null($area['piezas']) || ! is_null($area['kilos']))
            ->values()
            ->all();
    }

    /** @return array{texto:string,total:int} */
    private function resumirValores(Collection $valores): array
    {
        $total = $valores->count();
        $visibles = $valores->take(3)->implode(', ');

        return [
            'texto' => $visibles !== ''
                ? $visibles.($total > 3 ? ' +'.($total - 3) : '')
                : '—',
            'visibles' => $visibles !== '' ? $visibles : '—',
            // La tarjeta despliega la lista completa al tocar "+N más".
            'todos' => $valores->map(fn ($valor): string => (string) $valor)->values()->all(),
            'total' => $total,
        ];
    }

    private function formatearFecha(mixed $fecha): string
    {
        if (blank($fecha)) {
            return '—';
        }

        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
