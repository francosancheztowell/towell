<?php

declare(strict_types=1);

namespace App\Repositories\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SqlServerCrudoReadRepository implements CrudoReadRepository
{
    public function aggregateHeadersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $start = $from->setTime(0, 0);
        $end = $to->setTime(0, 0)->add(new DateInterval('P1D'));

        return $this->source()
            ->table($this->table('headers'))
            ->where('DATAAREAID', $this->dataAreaId())
            ->where('TRANSDATE', '>=', $start->format('Y-m-d H:i:s'))
            ->where('TRANSDATE', '<', $end->format('Y-m-d H:i:s'))
            ->groupBy('TELAR')
            ->orderBy('TELAR')
            ->selectRaw('
                TELAR,
                COUNT(*) AS captureCount,
                SUM(COALESCE(PIEZASTOTAL, 0)) AS pieces,
                SUM(COALESCE(SEGUNDASTOTAL, 0)) AS seconds,
                SUM(COALESCE(PESO, 0)) AS kilos
            ')
            ->get()
            ->all();
    }

    public function headersForTelarInRange(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->backfillMissingWeavingOrders(
            $this->queryHeaders($from, $to)
                ->where('TELAR', $telar)
                ->get()
                ->all(),
        );
    }

    /**
     * TI puede guardar la captura reciente con ORDENTEJIDO vacío aunque el
     * mismo PRODID ya haya sido relacionado antes. Se resuelve en una sola
     * consulta por lote y se conserva siempre el valor de la captura actual.
     *
     * @param  list<object>  $headers
     * @return list<object>
     */
    private function backfillMissingWeavingOrders(array $headers): array
    {
        $missingProdIds = [];

        foreach ($headers as $header) {
            $prodId = trim((string) ($header->PRODID ?? ''));
            $weavingOrder = trim((string) ($header->ORDENTEJIDO ?? ''));

            if ($prodId !== '' && $weavingOrder === '') {
                $missingProdIds[$prodId] = true;
            }
        }

        if ($missingProdIds === []) {
            return $headers;
        }

        $rankedOrders = $this->source()
            ->table($this->table('headers').' as history')
            ->where('history.DATAAREAID', $this->dataAreaId())
            ->whereIn('history.PRODID', array_keys($missingProdIds))
            ->whereNotNull('history.ORDENTEJIDO')
            // SQL Server ignora espacios finales al comparar cadenas, así que
            // '<> ""' ya descarta vacíos y valores solo de espacios sin
            // necesidad de LTRIM/RTRIM (no sargable).
            ->where('history.ORDENTEJIDO', '<>', '')
            ->select([
                'history.PRODID',
                'history.ORDENTEJIDO',
            ])
            ->selectRaw('
                ROW_NUMBER() OVER (
                    PARTITION BY history.PRODID
                    ORDER BY history.TRANSDATE DESC, history.RECID DESC
                ) AS rowNumber
            ');

        $ordersByProdId = $this->source()
            ->query()
            ->fromSub($rankedOrders, 'ranked_orders')
            ->where('rowNumber', 1)
            ->get(['PRODID', 'ORDENTEJIDO'])
            ->mapWithKeys(static fn (object $row): array => [
                trim((string) $row->PRODID) => trim((string) $row->ORDENTEJIDO),
            ])
            ->all();

        foreach ($headers as $header) {
            if (trim((string) ($header->ORDENTEJIDO ?? '')) !== '') {
                continue;
            }

            $prodId = trim((string) ($header->PRODID ?? ''));
            if (isset($ordersByProdId[$prodId]) && $ordersByProdId[$prodId] !== '') {
                $header->ORDENTEJIDO = $ordersByProdId[$prodId];
            }
        }

        return $headers;
    }

    private function queryHeaders(DateTimeImmutable $from, DateTimeImmutable $to): Builder
    {
        $start = $from->setTime(0, 0);
        $end = $to->setTime(0, 0)->add(new DateInterval('P1D'));

        return $this->source()
            ->table($this->table('headers'))
            ->where('DATAAREAID', $this->dataAreaId())
            ->where('TRANSDATE', '>=', $start->format('Y-m-d H:i:s'))
            ->where('TRANSDATE', '<', $end->format('Y-m-d H:i:s'))
            ->orderBy('TELAR')
            ->orderBy('RECID')
            ->select([
                'RECID',
                'PRODID',
                'PURCHBARCODE',
                'ORDENTEJIDO',
                'ORDENURDIDO',
                'TRANSDATE',
                'TELAR',
                'PESO',
                'PIEZAST1',
                'PIEZAST2',
                'PIEZAST3',
                'PIEZAST4',
                'PIEZASTOTAL',
                'SEGUNDASTOTAL',
                'EMPLID',
                'NAMEEMPLE',
                'OBSERVACIONES',
                'MODIFIEDDATE',
                'MODIFIEDTIME',
            ]);
    }

    public function defectsForHeaders(array $headerRecIds): array
    {
        $recIds = array_values(array_unique(array_filter(
            $headerRecIds,
            static fn (int|string $value): bool => trim((string) $value) !== '',
        )));

        if ($recIds === []) {
            return [];
        }

        $rows = [];
        $chunkSize = (int) config('crudo.line_query_chunk_size', 700);

        foreach (array_chunk($recIds, $chunkSize) as $chunk) {
            $chunkRows = $this->source()
                ->table($this->table('lines'))
                ->where('DATAAREAID', $this->dataAreaId())
                ->whereIn('REFRECID', $chunk)
                ->orderBy('REFRECID')
                ->orderBy('RECID')
                ->get([
                    'RECID',
                    'REFRECID',
                    'TURNO',
                    'CODDEFECTOID',
                    'CANTIDAD',
                    'DESCRIP',
                ])
                ->all();

            array_push($rows, ...$chunkRows);
        }

        return $rows;
    }

    /**
     * Una sola consulta agregada para todo el tablero: las líneas de defecto se
     * unen a los encabezados del rango por REFRECID y se suman en SQL Server, de
     * modo que viajen unas centenas de filas y no el detalle completo.
     */
    public function defectTotalsForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $start = $from->setTime(0, 0);
        $end = $to->setTime(0, 0)->add(new DateInterval('P1D'));

        return $this->source()
            ->table($this->table('lines').' as l')
            ->join($this->table('headers').' as h', 'h.RECID', '=', 'l.REFRECID')
            ->where('l.DATAAREAID', $this->dataAreaId())
            ->where('h.DATAAREAID', $this->dataAreaId())
            ->where('h.TRANSDATE', '>=', $start->format('Y-m-d H:i:s'))
            ->where('h.TRANSDATE', '<', $end->format('Y-m-d H:i:s'))
            ->groupBy('h.TELAR', 'l.CODDEFECTOID', 'l.DESCRIP')
            ->selectRaw('
                h.TELAR AS TELAR,
                l.CODDEFECTOID AS code,
                l.DESCRIP AS description,
                SUM(COALESCE(l.CANTIDAD, 0)) AS quantity
            ')
            ->get()
            ->all();
    }

    public function supplierLotsByWarpingOrder(array $warpingOrders): array
    {
        $folios = array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $warpingOrders),
            static fn (string $value): bool => $value !== '',
        )));

        if ($folios === []) {
            return [];
        }

        return $this->catalog()
            ->table($this->table('warping_programs'))
            ->whereIn('Folio', $folios)
            ->whereNotNull('LoteProveedor')
            ->get(['Folio', 'LoteProveedor'])
            ->mapWithKeys(static fn (object $row): array => [
                trim((string) $row->Folio) => trim((string) $row->LoteProveedor),
            ])
            ->filter(static fn (string $lot): bool => $lot !== '')
            ->all();
    }

    public function machines(): array
    {
        return $this->fetchMachines();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchMachines(): array
    {
        // Un solo LEFT JOIN en vez de 2 queries. Si InvSecuenciaTelares llegara a tener
        // más de una fila por telar (fan-out), nos quedamos con la primera y no
        // duplicamos el telar en el catálogo.
        $rows = $this->catalog()
            ->table($this->table('machines').' as m')
            ->leftJoin($this->table('sequence').' as s', 's.NoTelar', '=', 'm.NoTelarId')
            ->whereIn('m.SalonTejidoId', config('crudo.catalog_salons', []))
            ->orderBy('m.SalonTejidoId')
            ->orderBy('m.NoTelarId')
            ->get([
                'm.SalonTejidoId',
                'm.NoTelarId',
                'm.Nombre',
                'm.Grupo',
                's.Secuencia',
            ]);

        $machines = [];
        foreach ($rows as $row) {
            $telar = trim((string) $row->NoTelarId);
            if ($telar === '' || isset($machines[$telar])) {
                continue;
            }

            $machines[$telar] = [
                'telar' => $telar,
                'name' => trim((string) ($row->Nombre ?? '')) ?: 'Telar '.$telar,
                'salon' => trim((string) ($row->SalonTejidoId ?? '')),
                'group' => trim((string) ($row->Grupo ?? '')),
                'sequence' => $row->Secuencia !== null ? (int) $row->Secuencia : null,
            ];
        }

        return array_values($machines);
    }

    public function activeParos(array $telares = []): array
    {
        return $this->catalog()
            ->table($this->table('paros'))
            ->where('Estatus', 'Activo')
            ->when($telares !== [], fn ($query) => $query->whereIn('MaquinaId', $telares))
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            ->get([
                'MaquinaId',
                'Falla',
                'Descripcion',
                'TipoFallaId',
                'NomEmpl',
                'Fecha',
                'Hora',
                'Depto',
            ])
            ->all();
    }

    public function activePrograms(array $telares): array
    {
        if ($telares === []) {
            return [];
        }

        return $this->catalog()
            ->table($this->table('programs'))
            ->where('EnProceso', 1)
            ->whereIn('NoTelarId', $telares)
            ->orderByDesc('FechaInicio')
            ->orderByDesc('Id')
            ->get([
                'NoTelarId',
                'NoProduccion',
                'TamanoClave',
                'ItemId',
                'InventSizeId',
                'FlogsId',
                'NombreProducto',
                'ProdKgDia',
                'PesoCrudo',
                'SaldoMarbete',
                'TotalRollos',
                'TotalPedido',
                'SaldoPedido',
            ])
            ->all();
    }

    public function efficiencyLinesForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->catalog()
            ->table($this->table('efficiency_lines'))
            // ponytail: se arrastra una ventana hacia atrás para que un telar sin
            // captura del día conserve su último dato; el servicio se queda con
            // la fila más reciente por telar y marca si no es del periodo.
            ->whereBetween('Date', [
                $from->modify('-'.max(0, (int) config('crudo.efficiency_lookback_days', 30)).' days')->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->orderBy('Date')
            ->orderBy('Turno')
            ->get([
                'NoTelarId',
                'Date',
                'Turno',
                'RpmStd',
                'RpmR1',
                'EficienciaR1',
                'ObsR1',
                'RpmR2',
                'EficienciaR2',
                'ObsR2',
                'RpmR3',
                'EficienciaR3',
                'ObsR3',
            ])
            ->all();
    }

    private function source(): ConnectionInterface
    {
        return DB::connection((string) config('crudo.connections.source', 'sqlsrv_ti'));
    }

    private function catalog(): ConnectionInterface
    {
        return DB::connection((string) config('crudo.connections.catalog', 'sqlsrv'));
    }

    private function table(string $key): string
    {
        return (string) config("crudo.tables.{$key}");
    }

    private function dataAreaId(): string
    {
        return (string) config('crudo.data_area_id', 'pro');
    }
}
