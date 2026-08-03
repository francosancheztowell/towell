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
    public function headersForDate(DateTimeImmutable $date): array
    {
        return $this->headersForRange($date, $date);
    }

    public function headersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->queryHeaders($from, $to)->get()->all();
    }

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
        return $this->queryHeaders($from, $to)
            ->where('TELAR', $telar)
            ->get()
            ->all();
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

    public function machines(): array
    {
        $cacheSeconds = max(0, (int) config('crudo.catalog_cache_seconds', 300));

        if ($cacheSeconds > 0) {
            return cache()->remember(
                'crudo.machines.catalog',
                $cacheSeconds,
                fn (): array => $this->fetchMachines(),
            );
        }

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

    public function activeParos(): array
    {
        return $this->catalog()
            ->table($this->table('paros'))
            ->where('Estatus', 'Activo')
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            ->get([
                'MaquinaId',
                'Falla',
                'Descripcion',
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
            ->table((string) config('planeacion.programa_tejido_table', 'ReqProgramaTejido'))
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
