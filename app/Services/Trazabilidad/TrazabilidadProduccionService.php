<?php

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Trazabilidad\TrazaProduccion;
use Illuminate\Support\Collection;

/**
 * Construye la sección "Producción" de Trazabilidad en dos bloques:
 *  - Crudo: tarjetas por orden/telar (programa + catálogo + traza área Crudo).
 *  - Rollos Teñido: tarjetas por orden/localidad solo desde TrazaProduccion.
 */
class TrazabilidadProduccionService
{
    /**
     * @param  array  $filtros  ['flog','articulo','tamano','color','nombrecolor'(pipe), 'mes'(CSV)]
     * @return array{crudo: array, rollosTenido: array}
     */
    public function build(array $filtros): array
    {
        $mesesSel = $this->mesesSeleccionados($filtros);
        $nombresColorSel = $this->nombresColorSeleccionados($filtros);
        $base = $this->queryBase($filtros, $mesesSel);

        return [
            'crudo' => $this->buildCrudo($base, $mesesSel),
            'rollosTenido' => $this->buildRollosTenido($base, $nombresColorSel),
        ];
    }

    /**
     * @return array{ordenes: array, noEncontradas: array, resumen: array}
     */
    private function buildCrudo(callable $base, array $mesesSel): array
    {
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

        $nombresMeses = $this->nombresMesesCortos();
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

        $ordenes = $rows->pluck('Orden')->map(fn ($o) => trim((string) $o))
            ->filter()->unique()->values();

        $programa = $this->cargarProgramaPorOrdenes($ordenes);
        $faltantes = $ordenes->reject(fn ($o) => $programa->has($o))->values();
        $codificados = $faltantes->isNotEmpty()
            ? $this->cargarCodificadosPorOrdenes($faltantes)
            : collect();

        $soloDigitos = fn ($t) => preg_replace('/\D/', '', (string) $t);
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
            $porLoc = [];
            foreach ($filasLocalidad as $r) {
                $localidad = trim((string) ($r->Localidad ?? ''));
                $cant = (float) $r->cantidad_crudo;
                $peso = (float) $r->peso_crudo;
                if ($cant <= 0 && $peso <= 0) {
                    continue;
                }
                if (! isset($porLoc[$localidad])) {
                    $porLoc[$localidad] = [
                        'localidad' => $localidad,
                        'numLoc' => $soloDigitos($localidad),
                        'cantidad' => 0.0,
                        'peso' => 0.0,
                    ];
                }
                $porLoc[$localidad]['cantidad'] += $cant;
                $porLoc[$localidad]['peso'] += $peso;
            }

            $producidasCanon = 0.0;
            $kgCanon = 0.0;
            $extras = [];
            foreach ($porLoc as $loc) {
                $coincideLoc = $numOrden !== '' && $loc['numLoc'] === $numOrden;
                if ($coincideLoc) {
                    $producidasCanon += $loc['cantidad'];
                    $kgCanon += $loc['peso'];
                } else {
                    $extras[] = $loc;
                }
            }

            $hayGrupo = ! empty($extras);
            $grupoKey = $hayGrupo ? $orden : $orden.'_solo';

            $avancePrograma = null;
            if ($programaDatos !== null && $programaDatos['totalPedido'] > 0) {
                $avancePrograma = round($programaDatos['produccion'] / $programaDatos['totalPedido'] * 100, 1);
            }
            $avanceCodificados = null;
            if ($codificadosDatos !== null && $codificadosDatos['pedido'] > 0) {
                $avanceCodificados = round($codificadosDatos['produccion'] / $codificadosDatos['pedido'] * 100, 1);
            }
            $avanceTrazaCanon = $programadas > 0 ? round($producidasCanon / $programadas * 100, 1) : 0.0;
            $avanceCanon = $avancePrograma ?? $avanceCodificados ?? $avanceTrazaCanon;

            $estado = $fuente === 'programa' ? 'activo' : 'terminado';
            $telarSort = ($n = $numOrden) !== '' ? (int) $n : PHP_INT_MAX;

            $baseCard = [
                'orden' => $orden,
                'fuente' => $fuente,
                'estado' => $estado,
                'meses' => $mesesPorOrden[$orden] ?? [],
                'programadas' => $programadas,
                'pzasDia' => $stdDia,
                'programa' => $programaDatos,
                'codificados' => $codificadosDatos,
                'grupoKey' => $grupoKey,
                'grupoMulti' => $hayGrupo,
                'telarSort' => $telarSort,
            ];

            $ordenCards[] = array_merge($baseCard, [
                'esOtroTelar' => false,
                'telar' => $this->formatearTelar($telarOrden, $numOrden),
                'localidad' => $telarOrden,
                'enProceso' => $enProceso,
                'coincide' => true,
                'alerta' => false,
                'localidadesConflicto' => [],
                'producidas' => $producidasCanon,
                'kg' => $kgCanon,
                'avance' => $avanceCanon,
                'avanceTraza' => $avanceTrazaCanon,
                'usarTrazaEnProducido' => false,
            ]);

            foreach ($extras as $extra) {
                $avanceTrazaExtra = $programadas > 0
                    ? round($extra['cantidad'] / $programadas * 100, 1)
                    : 0.0;

                $ordenCards[] = array_merge($baseCard, [
                    'esOtroTelar' => true,
                    'telar' => $this->formatearTelar($extra['localidad'], $extra['numLoc']),
                    'localidad' => $extra['localidad'],
                    'enProceso' => false,
                    'coincide' => false,
                    'alerta' => false,
                    'localidadesConflicto' => [],
                    'producidas' => $extra['cantidad'],
                    'kg' => $extra['peso'],
                    'avance' => $avanceTrazaExtra,
                    'avanceTraza' => $avanceTrazaExtra,
                    'usarTrazaEnProducido' => true,
                ]);
            }
        }

        usort($ordenCards, function ($a, $b) {
            if ($a['telarSort'] !== $b['telarSort']) {
                return $a['telarSort'] <=> $b['telarSort'];
            }
            if ($a['grupoKey'] !== $b['grupoKey']) {
                return strcmp($a['grupoKey'], $b['grupoKey']);
            }

            return ($a['esOtroTelar'] ?? false) <=> ($b['esOtroTelar'] ?? false);
        });

        $noEncontradas = array_values($noEncontradas);
        $canonicas = collect($ordenCards)->where(fn ($c) => ! ($c['esOtroTelar'] ?? false));

        return [
            'ordenes' => $ordenCards,
            'noEncontradas' => $noEncontradas,
            'resumen' => [
                'ordenes' => $canonicas->count(),
                'activos' => $canonicas->where('estado', 'activo')->count(),
                'terminados' => $canonicas->where('estado', 'terminado')->count(),
                'alertas' => $canonicas->where('grupoMulti', true)->count(),
                'noEncontradas' => count($noEncontradas),
            ],
        ];
    }

    /**
     * Rollos Teñido: una tarjeta por Orden + Localidad, solo TrazaProduccion.
     *
     * @return array{ordenes: array, resumen: array}
     */
    private function buildRollosTenido(callable $base, array $nombresColorSel): array
    {
        $area = 'Rollos Teñido';

        $rollosBase = fn () => $base()
            ->where('NombreAlmacen', $area)
            ->when(! empty($nombresColorSel), fn ($q) => $q->whereIn('NombreColor', $nombresColorSel));

        $rows = $rollosBase()
            ->whereNotNull('Orden')->where('Orden', '<>', '')
            ->selectRaw('
                Orden,
                Localidad,
                MAX(Tipo) as tipo,
                MAX(Articulo) as articulo,
                MAX(NombreArticulo) as nombre_articulo,
                MAX(Color) as color,
                MAX(NombreColor) as nombre_color,
                SUM(Cantidad) as cantidad,
                SUM(Peso) as peso
            ')
            ->groupBy('Orden', 'Localidad')
            ->get();

        if ($rows->isEmpty()) {
            return ['ordenes' => [], 'resumen' => ['ordenes' => 0]];
        }

        $nombresMeses = $this->nombresMesesCortos();
        $mesesPorClave = $rollosBase()
            ->whereNotNull('Orden')->where('Orden', '<>', '')
            ->whereNotNull('Fecha')
            ->selectRaw('Orden, Localidad, MONTH(Fecha) as mes')
            ->distinct()
            ->get()
            ->groupBy(fn ($r) => trim((string) $r->Orden).'|'.trim((string) $r->Localidad))
            ->map(fn ($g) => $g->pluck('mes')->map(fn ($m) => (int) $m)
                ->filter()->unique()->sort()->values()
                ->map(fn ($m) => $nombresMeses[$m] ?? (string) $m)->all());

        $ordenCards = [];
        foreach ($rows as $r) {
            $orden = trim((string) $r->Orden);
            $localidad = trim((string) ($r->Localidad ?? ''));
            $clave = $orden.'|'.$localidad;

            $ordenCards[] = [
                'orden' => $orden,
                'localidad' => $localidad,
                'titulo' => $localidad !== '' ? $localidad : 'Sin localidad',
                'tipo' => trim((string) ($r->tipo ?? '')),
                'articulo' => trim((string) ($r->articulo ?? '')),
                'nombreArticulo' => trim((string) ($r->nombre_articulo ?? '')),
                'color' => trim((string) ($r->color ?? '')),
                'nombreColor' => trim((string) ($r->nombre_color ?? '')),
                'cantidad' => (float) ($r->cantidad ?? 0),
                'peso' => (float) ($r->peso ?? 0),
                'meses' => $mesesPorClave[$clave] ?? [],
            ];
        }

        usort($ordenCards, function ($a, $b) {
            $cmpLoc = strcasecmp($a['localidad'], $b['localidad']);
            if ($cmpLoc !== 0) {
                return $cmpLoc;
            }

            return strcasecmp($a['orden'], $b['orden']);
        });

        return [
            'ordenes' => $ordenCards,
            'resumen' => ['ordenes' => count($ordenCards)],
        ];
    }

    private function mesesSeleccionados(array $filtros): array
    {
        return collect(explode(',', (string) ($filtros['mes'] ?? '')))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();
    }

    /** @return list<string> */
    private function nombresColorSeleccionados(array $filtros): array
    {
        return collect(explode('|', (string) ($filtros['nombrecolor'] ?? '')))
            ->map(fn ($v) => trim($v))->filter()->unique()->values()->all();
    }

    private function queryBase(array $filtros, array $mesesSel): callable
    {
        return fn () => TrazaProduccion::query()
            ->when($filtros['flog'] ?? null, fn ($q, $v) => $q->where('Flogs', $v))
            ->when($filtros['articulo'] ?? null, fn ($q, $v) => $q->where('Articulo', $v))
            ->when($filtros['tamano'] ?? null, fn ($q, $v) => $q->where('Tamano', $v))
            ->when($filtros['color'] ?? null, fn ($q, $v) => $q->where('Color', $v))
            ->when(! empty($mesesSel), fn ($q) => $q->whereRaw('MONTH(Fecha) IN ('.implode(',', $mesesSel).')'));
    }

    /** @return array<int, string> */
    private function nombresMesesCortos(): array
    {
        return ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    }

    private function formatearTelar(string $localidad, string $numLoc): string
    {
        if ($numLoc !== '') {
            $n = ltrim($numLoc, '0');

            return 'Telar '.($n !== '' ? $n : $numLoc);
        }

        return $localidad !== '' ? $localidad : 'Sin telar';
    }

    private function resumenVacio(): array
    {
        return ['ordenes' => 0, 'activos' => 0, 'terminados' => 0, 'alertas' => 0, 'noEncontradas' => 0];
    }

    private function cargarProgramaPorOrdenes(Collection $ordenes): Collection
    {
        $columnas = [
            'NoProduccion', 'NoTelarId', 'SalonTejidoId',
            'TotalPedido', 'Produccion', 'SaldoPedido', 'TotalPzas',
            'StdDia', 'ProdKgDia', 'EnProceso',
            'FechaInicio', 'FechaFinal',
            'OrdCompartida', 'OrdCompartidaLider',
        ];

        $filas = collect();
        foreach ($ordenes->chunk(1000) as $lote) {
            $filas = $filas->concat(
                ReqProgramaTejido::query()
                    ->whereIn('NoProduccion', $lote->values()->all())
                    ->orderByDesc('EnProceso')
                    ->orderByDesc('Id')
                    ->get($columnas)
            );
        }

        return $filas
            ->unique(fn ($r) => trim((string) $r->NoProduccion))
            ->keyBy(fn ($r) => trim((string) $r->NoProduccion));
    }

    private function cargarCodificadosPorOrdenes(Collection $ordenes): Collection
    {
        $filas = collect();
        foreach ($ordenes->chunk(1000) as $lote) {
            $filas = $filas->concat(
                CatCodificados::query()
                    ->whereIn('OrdenTejido', $lote->values()->all())
                    ->orderByDesc('Id')
                    ->get(['OrdenTejido', 'TelarId', 'Pedido', 'Produccion', 'Saldos'])
            );
        }

        return $filas
            ->unique(fn ($r) => trim((string) $r->OrdenTejido))
            ->keyBy(fn ($r) => trim((string) $r->OrdenTejido));
    }
}
