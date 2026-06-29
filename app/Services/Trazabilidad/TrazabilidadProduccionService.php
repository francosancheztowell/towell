<?php

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Trazabilidad\TrazaProduccion;

/**
 * Construye la sección "Producción" de Trazabilidad: una tarjeta por telar del
 * flog (o filtro) actual, con sus piezas Programadas / Producidas / KG y avance.
 *
 * El telar de cada registro se conoce por dos vías que deben coincidir:
 *  - La columna `Localidad` de TrazaProduccion (el telar según trazabilidad) → es
 *    el telar que titula cada tarjeta.
 *  - El telar de la `Orden`: se busca primero en ReqProgramaTejido por NoProduccion
 *    (NoTelarId) y, si no está, en CatCodificados por OrdenTejido (TelarId). De esas
 *    tablas también salen las piezas Programadas y el estándar de Pzas/Día.
 *
 * Cuando el telar de la orden no coincide con la Localidad —o la orden no se
 * localiza en ninguna de las dos tablas— se marca una alerta en esa tarjeta.
 *
 * Producidas/KG provienen de la propia trazabilidad (área "Crudo": lo que el telar
 * tejió); Programadas/Pzas-Día provienen del programa de tejido.
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

        // Una fila por Orden + Localidad (telar). Producidas/KG = área "Crudo".
        $rows = $base()
            ->whereNotNull('Orden')->where('Orden', '<>', '')
            ->selectRaw("
                Orden,
                Localidad,
                SUM(CASE WHEN NombreAlmacen = 'Crudo' THEN Cantidad ELSE 0 END) as cantidad_crudo,
                SUM(CASE WHEN NombreAlmacen = 'Crudo' THEN Peso ELSE 0 END) as peso_crudo
            ")
            ->groupBy('Orden', 'Localidad')
            ->get();

        if ($rows->isEmpty()) {
            return ['telares' => [], 'noEncontradas' => [], 'resumen' => $this->resumenVacio()];
        }

        // --- Mapa Orden => datos del programa de tejido, en lote ---
        $ordenes = $rows->pluck('Orden')->map(fn ($o) => trim((string) $o))
            ->filter()->unique()->values();

        $programa = ReqProgramaTejido::query()
            ->whereIn('NoProduccion', $ordenes->all())
            ->get(['NoProduccion', 'NoTelarId', 'TotalPzas', 'TotalPedido', 'StdDia', 'EnProceso'])
            ->keyBy(fn ($r) => trim((string) $r->NoProduccion));

        // Las órdenes que no están en el programa se buscan en CatCodificados.
        $faltantes = $ordenes->reject(fn ($o) => $programa->has($o))->values();
        $codificados = collect();
        if ($faltantes->isNotEmpty()) {
            $codificados = CatCodificados::query()
                ->whereIn('OrdenTejido', $faltantes->all())
                ->get(['OrdenTejido', 'TelarId', 'TotalPzas', 'Pedido', 'Total'])
                ->keyBy(fn ($r) => trim((string) $r->OrdenTejido));
        }

        // Solo dígitos: la Localidad puede venir "T-202" / "TELAR 202" y el telar de
        // la orden como "202". Comparamos por el número para no marcar falsos positivos.
        $soloDigitos = fn ($t) => preg_replace('/\D/', '', (string) $t);

        // --- Agregar por telar (Localidad) ---
        // Solo las órdenes localizadas en ReqProgramaTejido / CatCodificados generan
        // tarjeta. Las que no se encuentran en ninguna tabla se acumulan aparte para
        // mostrarlas en un mensaje (no como card).
        $porTelar = [];
        $noEncontradas = [];
        foreach ($rows as $r) {
            $orden = trim((string) $r->Orden);
            $localidad = trim((string) ($r->Localidad ?? ''));
            $numLoc = $soloDigitos($localidad);

            // Resolver telar / programadas / std-día de la orden.
            $telarOrden = null;
            $fuente = null;
            $programadas = 0.0;
            $stdDia = null;
            $enProceso = false;

            if ($programa->has($orden)) {
                $p = $programa[$orden];
                $telarOrden = trim((string) $p->NoTelarId);
                $fuente = 'programa';
                $programadas = (float) ($p->TotalPzas ?: $p->TotalPedido ?: 0);
                $stdDia = $p->StdDia !== null ? (float) $p->StdDia : null;
                $enProceso = (bool) $p->EnProceso;
            } elseif ($codificados->has($orden)) {
                $c = $codificados[$orden];
                $telarOrden = trim((string) $c->TelarId);
                $fuente = 'codificados';
                $programadas = (float) ($c->TotalPzas ?: $c->Pedido ?: $c->Total ?: 0);
            }

            // No localizada en ninguna tabla → fuera de las tarjetas, va al mensaje.
            if ($telarOrden === null || $telarOrden === '') {
                if (! isset($noEncontradas[$orden])) {
                    $noEncontradas[$orden] = ['orden' => $orden, 'localidad' => $localidad];
                }

                continue;
            }

            $clave = $numLoc !== '' ? $numLoc : ($localidad !== '' ? $localidad : 'sin_telar');
            $numOrden = $soloDigitos($telarOrden);
            // Alerta de discrepancia: el telar de la orden no coincide con la Localidad.
            $coincide = $numOrden !== '' && $numOrden === $numLoc;

            if (! isset($porTelar[$clave])) {
                $porTelar[$clave] = [
                    'telar' => $this->formatearTelar($localidad, $numLoc),
                    'localidad' => $localidad,
                    'numTelar' => $numLoc,
                    'programadas' => 0.0,
                    'producidas' => 0.0,
                    'kg' => 0.0,
                    'pzasDia' => null,
                    'enProceso' => false,
                    'alerta' => false,
                ];
            }

            $t = &$porTelar[$clave];
            $t['programadas'] += $programadas;
            $t['producidas'] += (float) $r->cantidad_crudo;
            $t['kg'] += (float) $r->peso_crudo;
            // Pzas/Día: estándar del telar (tomamos el mayor entre sus órdenes).
            if ($stdDia !== null) {
                $t['pzasDia'] = max((float) ($t['pzasDia'] ?? 0), $stdDia);
            }
            $t['enProceso'] = $t['enProceso'] || $enProceso;
            if (! $coincide) {
                $t['alerta'] = true;
            }
            unset($t);
        }

        // Avance por telar + orden por número de telar.
        $telares = collect($porTelar)->map(function ($t) {
            $t['avance'] = $t['programadas'] > 0
                ? round($t['producidas'] / $t['programadas'] * 100, 1)
                : 0.0;

            return $t;
        })
            ->sortBy(fn ($t) => $t['numTelar'] !== '' ? (int) $t['numTelar'] : PHP_INT_MAX)
            ->values()
            ->all();

        $noEncontradas = array_values($noEncontradas);

        $col = collect($telares);
        $resumen = [
            'telares' => $col->count(),
            'activos' => $col->filter(fn ($t) => $t['enProceso'])->count(),
            'alertas' => $col->filter(fn ($t) => $t['alerta'])->count(),
            'noEncontradas' => count($noEncontradas),
        ];

        return ['telares' => $telares, 'noEncontradas' => $noEncontradas, 'resumen' => $resumen];
    }

    /**
     * Etiqueta del telar para la tarjeta: "T-202" si hay número; si no, el texto
     * crudo de la Localidad o "Sin telar".
     */
    private function formatearTelar(string $localidad, string $numLoc): string
    {
        if ($numLoc !== '') {
            $n = ltrim($numLoc, '0');

            return 'T-'.($n !== '' ? $n : $numLoc);
        }

        return $localidad !== '' ? $localidad : 'Sin telar';
    }

    private function resumenVacio(): array
    {
        return ['telares' => 0, 'activos' => 0, 'alertas' => 0, 'noEncontradas' => 0];
    }
}
