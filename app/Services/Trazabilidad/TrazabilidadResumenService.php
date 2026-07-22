<?php

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Trazabilidad\TrazaProduccion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrazabilidadResumenService
{
    public function __construct(private TrazabilidadMatrixService $matrixService) {}

    /**
     * @param  array{flog?:mixed,articulo?:mixed,tamano?:mixed,color?:mixed,mes?:mixed}  $filtros
     * @return array<string, mixed>
     */
    public function build(array $filtros): array
    {
        $query = $this->queryBase($filtros);

        $flogs = $this->valoresUnicos(clone $query, 'Flogs');
        $tamanos = $this->valoresUnicos(clone $query, 'Tamano');
        $articulos = (clone $query)
            ->whereNotNull('Articulo')
            ->where('Articulo', '<>', '')
            ->selectRaw("Articulo as codigo, MAX(NULLIF(LTRIM(RTRIM(NombreArticulo)), '')) as nombre")
            ->groupBy('Articulo')
            ->orderBy('Articulo')
            ->get()
            ->map(fn ($fila) => trim((string) $fila->codigo)
                .(filled($fila->nombre) ? ' · '.trim((string) $fila->nombre) : ''));

        $ordenes = (clone $query)
            ->whereNotNull('Orden')
            ->where('Orden', '<>', '')
            ->distinct()
            ->pluck('Orden')
            ->map(fn ($orden) => trim((string) $orden))
            ->filter()
            ->values();

        $programas = $this->programasPorOrden($ordenes);
        $fechaInicio = $programas->pluck('FechaInicio')->filter()->min();
        $fechaFin = $programas->pluck('FechaFinal')->filter()->max();
        $pedido = $programas->isNotEmpty()
            ? (float) $programas->sum(fn ($programa) => (float) ($programa->TotalPedido ?? 0))
            : null;
        $facturado = 0.0;
        $pendienteFacturacion = is_null($pedido) ? null : max(0, $pedido - $facturado);
        $produccionPrograma = $programas->isNotEmpty()
            ? (float) $programas->sum(fn ($programa) => (float) ($programa->Produccion ?? 0))
            : null;
        $saldoPedido = $programas->isNotEmpty()
            ? (float) $programas->sum(fn ($programa) => (float) ($programa->SaldoPedido ?? 0))
            : null;

        if (! $fechaInicio) {
            $fechaInicio = (clone $query)->min('Fecha');
        }
        if (! $fechaFin) {
            $fechaFin = (clone $query)->max('Fecha');
        }

        return [
            'flogs' => $this->resumirValores($flogs),
            'articulos' => $this->resumirValores($articulos),
            'tamanos' => $this->resumirValores($tamanos),
            'pedido' => $pedido,
            'facturado' => $facturado,
            'pendienteFacturacion' => $pendienteFacturacion,
            'produccionPrograma' => $produccionPrograma,
            'saldoPedido' => $saldoPedido,
            'avancePedido' => $pedido > 0 && ! is_null($produccionPrograma)
                ? round(min(100, max(0, $produccionPrograma / $pedido * 100)), 1)
                : null,
            'fechaInicio' => $this->formatearFecha($fechaInicio),
            'fechaFin' => $this->formatearFecha($fechaFin),
            'trazabilidadAreas' => $this->trazabilidadPorArea(clone $query),
        ];
    }

    /** @param array<string, mixed> $filtros */
    private function queryBase(array $filtros): Builder
    {
        $meses = collect(explode(',', (string) ($filtros['mes'] ?? '')))
            ->map(fn ($mes) => (int) trim($mes))
            ->filter(fn (int $mes) => $mes >= 1 && $mes <= 12)
            ->unique()
            ->values()
            ->all();

        return TrazaProduccion::query()
            ->when($filtros['flog'] ?? null, fn ($q, $valor) => $q->where('Flogs', $valor))
            ->when($filtros['articulo'] ?? null, fn ($q, $valor) => $q->where('Articulo', $valor))
            ->when($filtros['tamano'] ?? null, fn ($q, $valor) => $q->where('Tamano', $valor))
            ->when(! empty($meses), fn ($q) => $q->whereRaw('MONTH(Fecha) IN ('.implode(',', $meses).')'));
    }

    private function valoresUnicos(Builder $query, string $columna): Collection
    {
        return $query
            ->whereNotNull($columna)
            ->where($columna, '<>', '')
            ->distinct()
            ->orderBy($columna)
            ->pluck($columna)
            ->map(fn ($valor) => trim((string) $valor))
            ->filter()
            ->values();
    }

    /** @return Collection<int, object> */
    private function programasPorOrden(Collection $ordenes): Collection
    {
        $programas = collect();

        foreach ($ordenes->chunk(1000) as $lote) {
            $programas = $programas->concat(
                ReqProgramaTejido::query()
                    ->whereIn('NoProduccion', $lote->all())
                    ->orderByDesc('EnProceso')
                    ->orderByDesc('Id')
                    ->get([
                        'NoProduccion', 'TotalPedido', 'Produccion', 'SaldoPedido',
                        'FechaInicio', 'FechaFinal',
                    ])
            );
        }

        return $programas
            ->unique(fn ($programa) => trim((string) $programa->NoProduccion))
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function trazabilidadPorArea(Builder $query): array
    {
        $totales = $query
            ->whereNotNull('NombreAlmacen')
            ->where('NombreAlmacen', '<>', '')
            ->whereNotNull('Fecha')
            ->selectRaw('NombreAlmacen, Fecha, SUM(Cantidad) as piezas, SUM(Peso) as kilos')
            ->groupBy('NombreAlmacen', 'Fecha')
            ->get()
            ->groupBy(fn ($fila) => trim((string) $fila->NombreAlmacen));

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
