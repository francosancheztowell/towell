<?php

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Trazabilidad\TrazaProduccion;

/**
 * Construye la sección "Producción" de Trazabilidad: los telares que están en el
 * flog (o filtro) actual, con su tamaño y color.
 *
 * El telar de cada registro se conoce por dos vías que deben coincidir:
 *  - La columna `Localidad` de TrazaProduccion (el telar según trazabilidad).
 *  - El telar de la `Orden`: se busca primero en ReqProgramaTejido por NoProduccion
 *    (NoTelarId) y, si no está, en CatCodificados por OrdenTejido (TelarId).
 *
 * Cuando el telar de la orden no coincide con la Localidad —o la orden no se
 * localiza en ninguna de las dos tablas— se marca una alerta en esa fila.
 */
class TrazabilidadProduccionService
{
    /**
     * @param  array  $filtros  ['flog','articulo','tamano','color','mes'(CSV de meses)]
     * @return array{telares:array, resumen:array}
     */
    public function build(array $filtros): array
    {
        $mesesSel = collect(explode(',', (string) ($filtros['mes'] ?? '')))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();

        // Query base con los filtros presentes (todos opcionales). Misma cascada
        // que la matriz para que ambas secciones muestren el mismo universo.
        $base = fn () => TrazaProduccion::query()
            ->when($filtros['flog'] ?? null, fn ($q, $v) => $q->where('Flogs', $v))
            ->when($filtros['articulo'] ?? null, fn ($q, $v) => $q->where('Articulo', $v))
            ->when($filtros['tamano'] ?? null, fn ($q, $v) => $q->where('Tamano', $v))
            ->when($filtros['color'] ?? null, fn ($q, $v) => $q->where('Color', $v))
            ->when(! empty($mesesSel), fn ($q) => $q->whereRaw('MONTH(Fecha) IN ('.implode(',', $mesesSel).')'));

        // Una fila por Orden + Localidad (telar) + Tamaño + Color. La cantidad/peso
        // producidos se toman del área "Crudo" (lo que el telar tejió).
        $rows = $base()
            ->whereNotNull('Orden')->where('Orden', '<>', '')
            ->selectRaw("
                Orden,
                Localidad,
                Tamano,
                Color,
                MAX(NombreColor) as NombreColor,
                MAX(Articulo) as Articulo,
                MAX(NombreArticulo) as NombreArticulo,
                SUM(CASE WHEN NombreAlmacen = 'Crudo' THEN Cantidad ELSE 0 END) as cantidad_crudo,
                SUM(CASE WHEN NombreAlmacen = 'Crudo' THEN Peso ELSE 0 END) as peso_crudo
            ")
            ->groupBy('Orden', 'Localidad', 'Tamano', 'Color')
            ->get();

        if ($rows->isEmpty()) {
            return ['telares' => [], 'resumen' => $this->resumenVacio()];
        }

        // --- Mapa Orden => telar, en lote para no consultar fila por fila ---
        $ordenes = $rows->pluck('Orden')->map(fn ($o) => trim((string) $o))
            ->filter()->unique()->values();

        $programa = ReqProgramaTejido::query()
            ->whereIn('NoProduccion', $ordenes->all())
            ->whereNotNull('NoTelarId')
            ->get(['NoProduccion', 'NoTelarId'])
            ->keyBy(fn ($r) => trim((string) $r->NoProduccion));

        // Las órdenes que no están en el programa de tejido se buscan en CatCodificados.
        $faltantes = $ordenes->reject(fn ($o) => $programa->has($o))->values();
        $codificados = collect();
        if ($faltantes->isNotEmpty()) {
            $codificados = CatCodificados::query()
                ->whereIn('OrdenTejido', $faltantes->all())
                ->whereNotNull('TelarId')
                ->get(['OrdenTejido', 'TelarId'])
                ->keyBy(fn ($r) => trim((string) $r->OrdenTejido));
        }

        // Solo dígitos: la Localidad puede venir "T-202" / "TELAR 202" y el telar de
        // la orden como "202". Comparamos por el número para no marcar falsos positivos.
        $soloDigitos = fn ($t) => preg_replace('/\D/', '', (string) $t);

        $telares = $rows->map(function ($r) use ($programa, $codificados, $soloDigitos) {
            $orden = trim((string) $r->Orden);
            $localidad = trim((string) ($r->Localidad ?? ''));

            $telarOrden = null;
            $fuente = null;
            if ($programa->has($orden)) {
                $telarOrden = trim((string) $programa[$orden]->NoTelarId);
                $fuente = 'programa';
            } elseif ($codificados->has($orden)) {
                $telarOrden = trim((string) $codificados[$orden]->TelarId);
                $fuente = 'codificados';
            }

            $numOrden = $soloDigitos($telarOrden);
            $numLoc = $soloDigitos($localidad);
            $coincide = $telarOrden !== null && $numOrden !== '' && $numOrden === $numLoc;

            // Alerta: la orden no se localizó, o el telar de la orden no coincide.
            $alerta = null;
            if ($telarOrden === null || $telarOrden === '') {
                $alerta = 'no_encontrado';
            } elseif (! $coincide) {
                $alerta = 'no_coincide';
            }

            return [
                'orden' => $orden,
                'localidad' => $localidad,
                'telarOrden' => $telarOrden,
                'fuente' => $fuente,
                'tamano' => trim((string) ($r->Tamano ?? '')),
                'color' => trim(($r->Color ?? '').(filled($r->NombreColor) ? ' / '.$r->NombreColor : '')),
                'articulo' => trim(($r->Articulo ?? '').(filled($r->NombreArticulo) ? ' / '.$r->NombreArticulo : '')),
                'cantidad' => (float) $r->cantidad_crudo,
                'peso' => (float) $r->peso_crudo,
                'alerta' => $alerta,
            ];
        })
            // Ordenar por telar (Localidad) y luego por orden para una lectura estable.
            ->sortBy([
                fn ($a, $b) => $soloDigitos($a['localidad']) <=> $soloDigitos($b['localidad']),
                fn ($a, $b) => $a['orden'] <=> $b['orden'],
            ])
            ->values()
            ->all();

        $col = collect($telares);
        $resumen = [
            'telares' => $col->pluck('localidad')->filter()->unique()->count(),
            'ordenes' => $col->count(),
            'cantidad' => $col->sum('cantidad'),
            'peso' => $col->sum('peso'),
            'alertas' => $col->filter(fn ($t) => $t['alerta'] !== null)->count(),
        ];

        return ['telares' => $telares, 'resumen' => $resumen];
    }

    private function resumenVacio(): array
    {
        return ['telares' => 0, 'ordenes' => 0, 'cantidad' => 0, 'peso' => 0, 'alertas' => 0];
    }
}
