<?php

namespace App\Http\Controllers\Planeacion\Alineacion;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
     * por OrdenTejido (No orden). Si no existe la orden, no se hace nada.
     */
    public function index(): View
    {
        $items = $this->obtenerItemsAlineacion();

        return view('planeacion.alineacion.index', [
            'items' => $items,
        ]);
    }

    /**
     * API: Devuelve los items de alineación en JSON (para refresco automático cada 5 min).
     */
    public function apiData(): JsonResponse
    {
        $items = $this->obtenerItemsAlineacion();

        return response()->json(['s' => true, 'items' => $items]);
    }

    /**
     * Obtiene los items de alineación (ReqProgramaTejido + CatCodificados).
     *
     * @return array<int, array<string, mixed>>
     */
    private function obtenerItemsAlineacion(): array
    {
        $registros = ReqProgramaTejido::query()
            ->enProceso(true)
            ->ordenado()
            ->get();

        $catCodPorOrden = $this->obtenerCatCodificadosPorOrden($registros);

        return $registros->map(fn (ReqProgramaTejido $r) => $this->mapearProgramaTejidoAItem($r, $catCodPorOrden))->all();
    }

    /**
     * Obtiene mapa de CatCodificados por OrdenTejido (No orden).
     * Solo busca por orden; si no aparece, no hace nada.
     *
     * @return array<string, CatCodificados>
     */
    private function obtenerCatCodificadosPorOrden(Collection $registros): array
    {
        $ordenes = [];
        foreach ($registros as $r) {
            $noOrden = trim((string) ($r->NoProduccion ?? ''));
            if ($noOrden !== '') {
                $ordenes[$noOrden] = true;
            }
        }

        if (empty($ordenes)) {
            return [];
        }

        $ids = array_values(array_map('strval', array_keys($ordenes)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cats = CatCodificados::query()
            ->select(['Id', 'ItemId', 'OrdenTejido', 'FechaTejido', 'Tolerancia', 'Razurada', 'TipoRizo', 'DobladilloId', 'Obs5'])
            ->whereRaw("CAST([OrdenTejido] AS NVARCHAR(100)) IN ({$placeholders})", $ids)
            ->orderByDesc('Id')
            ->get();

        $map = [];
        foreach ($cats as $c) {
            $key = trim((string) ($c->OrdenTejido ?? ''));
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $c;
            }
        }

        return $map;
    }

    /**
     * Mapea un registro ReqProgramaTejido al array asociativo esperado por la vista.
     * Tolerancia, RazSN, TipoRizo, TipoPlano y Observaciones vienen de CatCodificados por OrdenTejido.
     *
     * @param  array<string, CatCodificados>  $catCodPorOrden
     * @return array<string, mixed>
     */
    private function mapearProgramaTejidoAItem(ReqProgramaTejido $r, array $catCodPorOrden = []): array
    {
        $noOrden = trim((string) ($r->NoProduccion ?? ''));
        $cat = $catCodPorOrden[$noOrden] ?? null;

        $item = [];
        $mapeoEspecial = [
            'FechaCompromiso' => 'EntregaCte',
            'Tolerancia' => null,
            'RazSN' => null,
            'TipoRizo' => null,
            'TipoPlano' => null,
            'PesoMin' => 'PesoMuesMin',
            'PesoMax' => 'PesoMuesMax',
            'MuestraMin' => null,
            'MuestraMax' => null,
            'ProdAcumMesAnt' => null,
            'ProdAcumMes' => null,
            'DiasPorEjecutar' => null,
        ];

        $concatCalibreFibra = [
            'PasadasComb1' => fn () => $this->concatCalibreFibra($r->CalibreComb1, $r->FibraComb1),
            'PasadasComb2' => fn () => $this->concatCalibreFibra($r->CalibreComb2, $r->FibraComb2),
            'PasadasComb3' => fn () => $this->concatCalibreFibra($r->CalibreComb3, $r->FibraComb3),
            'PasadasComb4' => fn () => $this->concatCalibreFibra($r->CalibreComb4, $r->FibraComb4),
        ];

        $deCat = [
            'FechaCambio' => fn () => $cat?->FechaTejido ? $this->formatDateAlineacion($cat->FechaTejido, 'd M Y') : '',
            'Tolerancia' => fn () => $cat?->Tolerancia,
            'RazSN' => fn () => $cat?->Razurada,
            'TipoRizo' => fn () => $cat?->TipoRizo,
            'TipoPlano' => fn () => $cat?->DobladilloId,
            'Observaciones' => fn () => $cat?->Obs5,
        ];

        foreach ($this->columnas as $key) {
            if (isset($concatCalibreFibra[$key])) {
                $item[$key] = $concatCalibreFibra[$key]();
                continue;
            }
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
        // FechaTejido en Y-m-d para cálculo de Días de prod. en el front (catcodificados)
        $item['FechaTejido'] = $cat?->FechaTejido ? Carbon::parse($cat->FechaTejido)->format('Y-m-d') : '';
        return $item;
    }

    /**
     * Concatena Calibre/Fibra para Cenefa Trama (ReqProgramaTejido).
     */
    private function concatCalibreFibra($calibre, $fibra): string
    {
        $c = trim((string) ($calibre ?? ''));
        $f = trim((string) ($fibra ?? ''));
        if ($c === '' && $f === '') {
            return '';
        }
        if ($c === '' || $f === '') {
            return $c . $f;
        }

        return $c . '/' . $f;
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
