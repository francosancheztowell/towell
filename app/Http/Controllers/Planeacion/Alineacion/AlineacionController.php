<?php

namespace App\Http\Controllers\Planeacion\Alineacion;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class AlineacionController extends Controller
{
    /**
     * Columnas en orden de visualización (keys usados en cada fila de datos).
     * Campos del modelo ReqProgramaTejido se mapean; los que no existen quedan en blanco.
     *
     * @var array<int, string>
     */
    private array $columnas = [
        'NoTelarId',
        'NoProduccion',
        'FechaCambio',
        'FechaCompromiso',
        'ItemId',
        'NombreProducto',
        'Tolerancia',
        'RazSN',
        'TipoRizo',
        'CalibreRizo',
        'Ancho',
        'LargoCrudo',
        'PesoCrudo',
        'Luchaje',
        'TipoPlano',
        'MedidaPlano',
        'NoTiras',
        'CuentaRizo',
        'CuentaPie',
        'CalibreTrama',
        'PasadasComb1',
        'PasadasComb2',
        'PasadasComb3',
        'PasadasComb4',
        'AnchoToalla',
        'PesoGRM2',
        'PesoMin',
        'PesoMax',
        'MuestraMin',
        'MuestraMax',
        'TotalPedido',
        'ProdAcumMesAnt',
        'ProdAcumMes',
        'Produccion',
        'SaldoPedido',
        'DiasEficiencia',
        'ProdKgDia',
        'DiasPorEjecutar',
        'Observaciones',
    ];

    /**
     * Vista principal de Alineación (programa de tejido en proceso).
     * Tolerancia, Raz s/n, Tipo Rizo, Tipo Plano y Observaciones se obtienen de CatCodificados
     * por clave ItemId (Clave AX) y OrdenTejido (No orden).
     */
    public function index(): View
    {
        $registros = ReqProgramaTejido::query()
            ->enProceso(true)
            ->ordenado()
            ->get();

        [$catCodMap, $catCodMapPorItem] = $this->obtenerCatCodificadosPorItemYOrden($registros);

        $items = $registros->map(fn (ReqProgramaTejido $r) => $this->mapearProgramaTejidoAItem($r, $catCodMap, $catCodMapPorItem))->all();

        return view('planeacion.alineacion.index', [
            'items' => $items,
        ]);
    }

    /**
     * Obtiene mapas de CatCodificados:
     * 1) Por (ItemId | OrdenTejido) exacto
     * 2) Por ItemId como fallback: el más reciente por FechaTejido (cuando no hay NoOrden)
     *
     * @return array{0: array<string, CatCodificados>, 1: array<string, CatCodificados>}
     */
    private function obtenerCatCodificadosPorItemYOrden(Collection $registros): array
    {
        $pares = [];
        $itemIds = [];
        foreach ($registros as $r) {
            $itemId = trim((string) ($r->ItemId ?? ''));
            $noOrden = trim((string) ($r->NoProduccion ?? ''));
            if ($itemId !== '' || $noOrden !== '') {
                $pares[] = ['itemId' => $itemId, 'orden' => $noOrden];
                if ($itemId !== '') {
                    $itemIds[$itemId] = true;
                }
            }
        }

        $mapExacto = [];
        if (! empty($pares)) {
            $cats = CatCodificados::query()
                ->select(['Id', 'ItemId', 'OrdenTejido', 'FechaTejido', 'Tolerancia', 'Razurada', 'TipoRizo', 'DobladilloId', 'Obs5'])
                ->where(function ($q) use ($pares) {
                    foreach ($pares as $p) {
                        $q->orWhereRaw(
                            'CAST([ItemId] AS NVARCHAR(100)) = ? AND CAST([OrdenTejido] AS NVARCHAR(100)) = ?',
                            [(string) $p['itemId'], (string) $p['orden']]
                        );
                    }
                })
                ->orderByDesc('Id')
                ->get();

            foreach ($cats as $c) {
                $key = trim((string) ($c->ItemId ?? '')) . '|' . trim((string) ($c->OrdenTejido ?? ''));
                if (! isset($mapExacto[$key])) {
                    $mapExacto[$key] = $c;
                }
            }
        }

        // Fallback: por ItemId, el más reciente por FechaTejido (fecha orden).
        // Usar CAST para evitar error de conversión varchar->int en SQL Server (ItemId puede ser INT o VARCHAR).
        $mapPorItem = [];
        if (! empty($itemIds)) {
            $ids = array_values(array_map('strval', array_keys($itemIds)));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $catsFallback = CatCodificados::query()
                ->select(['Id', 'ItemId', 'OrdenTejido', 'FechaTejido', 'Tolerancia', 'Razurada', 'TipoRizo', 'DobladilloId', 'Obs5'])
                ->whereRaw("CAST([ItemId] AS NVARCHAR(100)) IN ({$placeholders})", $ids)
                ->whereNotNull('FechaTejido')
                ->orderByDesc('FechaTejido')
                ->get();

            foreach ($catsFallback as $c) {
                $itemId = trim((string) ($c->ItemId ?? ''));
                if ($itemId !== '' && ! isset($mapPorItem[$itemId])) {
                    $mapPorItem[$itemId] = $c;
                }
            }
        }

        return [$mapExacto, $mapPorItem];
    }

    /**
     * Mapea un registro ReqProgramaTejido al array asociativo esperado por la vista.
     * Tolerancia, RazSN, TipoRizo, TipoPlano y Observaciones vienen de CatCodificados.
     * Si no hay match por (ItemId + NoOrden), usa el más reciente por FechaTejido del ItemId.
     *
     * @param  array<string, CatCodificados>  $catCodMap
     * @param  array<string, CatCodificados>  $catCodMapPorItem
     * @return array<string, mixed>
     */
    private function mapearProgramaTejidoAItem(ReqProgramaTejido $r, array $catCodMap = [], array $catCodMapPorItem = []): array
    {
        $itemId = trim((string) ($r->ItemId ?? ''));
        $noOrden = trim((string) ($r->NoProduccion ?? ''));
        $catKey = $itemId . '|' . $noOrden;
        $cat = $catCodMap[$catKey] ?? $catCodMapPorItem[$itemId] ?? null;

        $item = [];
        $mapeoEspecial = [
            'FechaCambio' => null,
            'FechaCompromiso' => 'EntregaCte',
            'Tolerancia' => null,  // CatCodificados.Tolerancia
            'RazSN' => null,      // CatCodificados.Razurada (Raz s/n)
            'TipoRizo' => null,   // CatCodificados.TipoRizo
            'TipoPlano' => null,  // CatCodificados.DobladilloId (Tipo plano)
            'PesoMin' => 'PesoMuesMin',
            'PesoMax' => 'PesoMuesMax',
            'MuestraMin' => null,
            'MuestraMax' => null,
            'ProdAcumMesAnt' => null,
            'ProdAcumMes' => null,
            'DiasPorEjecutar' => null,
        ];

        $deCat = [
            'Tolerancia' => fn () => $cat?->Tolerancia,
            'RazSN' => fn () => $cat?->Razurada,
            'TipoRizo' => fn () => $cat?->TipoRizo,
            'TipoPlano' => fn () => $cat?->DobladilloId,
            'Observaciones' => fn () => $cat?->Obs5,
        ];

        foreach ($this->columnas as $key) {
            if (isset($deCat[$key])) {
                $item[$key] = $deCat[$key]() ?? '';
                continue;
            }
            if (array_key_exists($key, $mapeoEspecial)) {
                $attr = $mapeoEspecial[$key];
                if ($attr === null) {
                    $item[$key] = '';
                    continue;
                }
                $value = $r->getAttribute($attr);
                $item[$key] = $value ? (
                    $attr === 'UpdatedAt' ? $this->formatDateAlineacion($value, 'd M Y H:i') : (
                        $attr === 'EntregaCte' ? $this->formatDateAlineacion($value, 'd M Y') : $value
                    )
                ) : '';
                continue;
            }
            $value = $r->getAttribute($key);
            if ($value === null) {
                $item[$key] = '';
                continue;
            }
            $item[$key] = $value;
        }
        return $item;
    }

    /**
     * Formatea fecha/datetime en español para la vista Alineación.
     *
     * @param mixed $value
     */
    private function formatDateAlineacion($value, string $format): string
    {
        try {
            return Carbon::parse($value)->locale('es')->translatedFormat($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
