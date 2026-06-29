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
 *  - El telar de la `Orden`: ReqProgramaTejido (activo) o CatCodificados (finalizado).
 *
 * Campos por fuente en cada tarjeta:
 *  - Programa: NoTelarId, TotalPedido, Produccion, SaldoPedido, SalonTejidoId, StdDia,
 *    FechaInicio, FechaFinal, OrdCompartida, OrdCompartidaLider.
 *  - CatCodificados: TelarId, OrdenTejido, Pedido, Produccion, Saldos.
 *
 * Cuando TrazaProduccion registra la misma Orden en otra Localidad distinta al
 * telar del programa, se marca alerta en la tarjeta única (sin duplicar la orden).
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
            return ['ordenes' => [], 'noEncontradas' => [], 'resumen' => $this->resumenVacio()];
        }

        // Meses de producción por orden (para el badge de mes de cada orden).
        $nombresMeses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $mesesPorOrden = $base()
            ->whereNotNull('Orden')->where('Orden', '<>', '')
            ->whereNotNull('Fecha')
            ->selectRaw('Orden, MONTH(Fecha) as mes')
            ->distinct()
            ->get()
            ->groupBy(fn ($r) => trim((string) $r->Orden))
            ->map(fn ($g) => $g->pluck('mes')->map(fn ($m) => (int) $m)
                ->filter()->unique()->sort()->values()
                ->map(fn ($m) => $nombresMeses[$m] ?? (string) $m)->all());

        // --- Mapa Orden => datos del programa de tejido, en lote ---
        $ordenes = $rows->pluck('Orden')->map(fn ($o) => trim((string) $o))
            ->filter()->unique()->values();

        // Si hay más de un registro por NoProduccion, priorizar EnProceso y el Id más reciente.
        $programa = ReqProgramaTejido::query()
            ->whereIn('NoProduccion', $ordenes->all())
            ->orderByDesc('EnProceso')
            ->orderByDesc('Id')
            ->get([
                'NoProduccion', 'NoTelarId', 'SalonTejidoId',
                'TotalPedido', 'Produccion', 'SaldoPedido', 'TotalPzas',
                'StdDia', 'ProdKgDia', 'EnProceso',
                'FechaInicio', 'FechaFinal',
                'OrdCompartida', 'OrdCompartidaLider',
            ])
            ->unique(fn ($r) => trim((string) $r->NoProduccion))
            ->keyBy(fn ($r) => trim((string) $r->NoProduccion));

        // Las órdenes que no están en el programa se buscan en CatCodificados.
        $faltantes = $ordenes->reject(fn ($o) => $programa->has($o))->values();
        $codificados = collect();
        if ($faltantes->isNotEmpty()) {
            $codificados = CatCodificados::query()
                ->whereIn('OrdenTejido', $faltantes->all())
                ->orderByDesc('Id')
                ->get(['OrdenTejido', 'TelarId', 'Pedido', 'Produccion', 'Saldos'])
                ->unique(fn ($r) => trim((string) $r->OrdenTejido))
                ->keyBy(fn ($r) => trim((string) $r->OrdenTejido));
        }

        // Solo dígitos: la Localidad puede venir "T-202" / "TELAR 202" y el telar de
        // la orden como "202". Comparamos por el número para no marcar falsos positivos.
        $soloDigitos = fn ($t) => preg_replace('/\D/', '', (string) $t);

        // --- Una tarjeta por orden (telar canónico desde programa / cat) ---
        // TrazaProduccion puede repetir la misma Orden en varias Localidades (p. ej.
        // movimientos históricos en otro telar). Solo mostramos el telar del programa;
        // las piezas/KG son las del área Crudo en ese telar. Si hay producción en
        // otras localidades, se marca alerta sin duplicar la tarjeta.
        $porOrden = $rows->groupBy(fn ($r) => trim((string) $r->Orden));
        $ordenCards = [];
        $noEncontradas = [];

        foreach ($porOrden as $orden => $filasLocalidad) {
            $telarOrden = null;
            $fuente = null;
            $programadas = 0.0;
            $stdDia = null;
            $enProceso = false;
            $programaDatos = null;
            $codificadosDatos = null;

            if ($programa->has($orden)) {
                $p = $programa[$orden];
                $telarOrden = trim((string) $p->NoTelarId);
                $fuente = 'programa';
                $totalPedido = (float) ($p->TotalPedido ?? 0);
                $produccionBd = (float) ($p->Produccion ?? 0);
                $programadas = $totalPedido > 0 ? $totalPedido : (float) ($p->TotalPzas ?? 0);
                $stdDia = $p->StdDia !== null ? (float) $p->StdDia : null;
                $prodKgDia = $p->ProdKgDia !== null ? (float) $p->ProdKgDia : null;
                $enProceso = (bool) $p->EnProceso;
                $ordCompartida = $p->OrdCompartida;
                $esOrdCompartida = filled($ordCompartida) && (int) $ordCompartida > 0;
                $programaDatos = [
                    'noTelarId' => $telarOrden,
                    'noProduccion' => $orden,
                    'salonTejidoId' => trim((string) ($p->SalonTejidoId ?? '')),
                    'totalPedido' => $totalPedido,
                    'produccion' => $produccionBd,
                    'saldoPedido' => (float) ($p->SaldoPedido ?? 0),
                    'stdDia' => $stdDia,
                    'prodKgDia' => $prodKgDia,
                    'fechaInicio' => filled($p->FechaInicio) ? formatearFecha($p->FechaInicio) : null,
                    'fechaFinal' => filled($p->FechaFinal) ? formatearFecha($p->FechaFinal) : null,
                    'esOrdCompartida' => $esOrdCompartida,
                    'ordCompartida' => $esOrdCompartida ? (int) $ordCompartida : null,
                    'esLiderOrdCompartida' => (bool) $p->OrdCompartidaLider,
                ];
            } elseif ($codificados->has($orden)) {
                $c = $codificados[$orden];
                $telarOrden = trim((string) $c->TelarId);
                $fuente = 'codificados';
                $pedido = (float) ($c->Pedido ?? 0);
                $produccionCat = (float) ($c->Produccion ?? 0);
                $programadas = $pedido;
                $codificadosDatos = [
                    'telarId' => $telarOrden,
                    'ordenTejido' => $orden,
                    'pedido' => $pedido,
                    'produccion' => $produccionCat,
                    'saldos' => (float) ($c->Saldos ?? 0),
                ];
            }

            if ($telarOrden === null || $telarOrden === '') {
                $primeraLoc = trim((string) ($filasLocalidad->first()->Localidad ?? ''));
                if (! isset($noEncontradas[$orden])) {
                    $noEncontradas[$orden] = ['orden' => $orden, 'localidad' => $primeraLoc];
                }

                continue;
            }

            $numOrden = $soloDigitos($telarOrden);
            $producidas = 0.0;
            $kg = 0.0;
            $localidadesExtra = [];

            foreach ($filasLocalidad as $r) {
                $localidad = trim((string) ($r->Localidad ?? ''));
                $numLoc = $soloDigitos($localidad);
                $coincideLoc = $numOrden !== '' && $numOrden === $numLoc;

                if ($coincideLoc) {
                    $producidas += (float) $r->cantidad_crudo;
                    $kg += (float) $r->peso_crudo;
                } elseif ((float) $r->cantidad_crudo > 0 || (float) $r->peso_crudo > 0) {
                    $localidadesExtra[] = $this->formatearTelar($localidad, $numLoc);
                }
            }

            $localidadesExtra = array_values(array_unique($localidadesExtra));

            $avancePrograma = null;
            if ($programaDatos !== null && $programaDatos['totalPedido'] > 0) {
                $avancePrograma = round($programaDatos['produccion'] / $programaDatos['totalPedido'] * 100, 1);
            }
            $avanceCodificados = null;
            if ($codificadosDatos !== null && $codificadosDatos['pedido'] > 0) {
                $avanceCodificados = round($codificadosDatos['produccion'] / $codificadosDatos['pedido'] * 100, 1);
            }
            $avanceTraza = $programadas > 0 ? round($producidas / $programadas * 100, 1) : 0.0;
            $avance = $avancePrograma ?? $avanceCodificados ?? $avanceTraza;

            $ordenCards[] = [
                'orden' => $orden,
                'telar' => $this->formatearTelar($telarOrden, $numOrden),
                'localidad' => $telarOrden,
                'fuente' => $fuente,
                'estado' => $fuente === 'programa' ? 'activo' : 'terminado',
                'enProceso' => $enProceso,
                'coincide' => empty($localidadesExtra),
                'alerta' => ! empty($localidadesExtra),
                'localidadesConflicto' => $localidadesExtra,
                'meses' => $mesesPorOrden[$orden] ?? [],
                'programadas' => $programadas,
                'producidas' => $producidas,
                'kg' => $kg,
                'pzasDia' => $stdDia,
                'avance' => $avance,
                'avanceTraza' => $avanceTraza,
                'programa' => $programaDatos,
                'codificados' => $codificadosDatos,
            ];
        }

        // Ordenar por número de telar (canónico del programa / cat).
        usort($ordenCards, function ($a, $b) use ($soloDigitos) {
            $na = ($nA = $soloDigitos($a['localidad'])) !== '' ? (int) $nA : PHP_INT_MAX;
            $nb = ($nB = $soloDigitos($b['localidad'])) !== '' ? (int) $nB : PHP_INT_MAX;

            return $na <=> $nb;
        });

        $noEncontradas = array_values($noEncontradas);

        $col = collect($ordenCards);
        $resumen = [
            'ordenes' => $col->count(),
            'activos' => $col->where('estado', 'activo')->count(),
            'terminados' => $col->where('estado', 'terminado')->count(),
            'alertas' => $col->where('alerta', true)->count(),
            'noEncontradas' => count($noEncontradas),
        ];

        return ['ordenes' => $ordenCards, 'noEncontradas' => $noEncontradas, 'resumen' => $resumen];
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
        return ['ordenes' => 0, 'activos' => 0, 'terminados' => 0, 'alertas' => 0, 'noEncontradas' => 0];
    }
}
