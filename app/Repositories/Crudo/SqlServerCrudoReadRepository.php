<?php

declare(strict_types=1);

namespace App\Repositories\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class SqlServerCrudoReadRepository implements CrudoReadRepository
{
    public function headersForDate(DateTimeImmutable $date): array
    {
        $from = $date->setTime(0, 0);
        $to = $from->add(new DateInterval('P1D'));

        return $this->source()
            ->table($this->table('headers'))
            ->where('DATAAREAID', $this->dataAreaId())
            ->where('TRANSDATE', '>=', $from->format('Y-m-d H:i:s'))
            ->where('TRANSDATE', '<', $to->format('Y-m-d H:i:s'))
            ->orderBy('TELAR')
            ->orderBy('RECID')
            ->get([
                'RECID',
                'PRODID',
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
            ])
            ->all();
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
        $catalog = $this->catalog()
            ->table($this->table('machines'))
            ->whereIn('SalonTejidoId', config('crudo.catalog_salons', []))
            ->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->get([
                'SalonTejidoId',
                'NoTelarId',
                'Nombre',
                'Grupo',
            ]);

        $telars = $catalog
            ->pluck('NoTelarId')
            ->map(static fn (mixed $telar): string => trim((string) $telar))
            ->filter()
            ->values()
            ->all();

        $sequences = $telars === []
            ? collect()
            : $this->catalog()
                ->table($this->table('sequence'))
                ->whereIn('NoTelar', $telars)
                ->get(['NoTelar', 'TipoTelar', 'Secuencia'])
                ->keyBy(static fn (object $row): string => trim((string) $row->NoTelar));

        return $catalog
            ->map(static function (object $row) use ($sequences): array {
                $telar = trim((string) $row->NoTelarId);
                $sequence = $sequences->get($telar);

                return [
                    'telar' => $telar,
                    'name' => trim((string) ($row->Nombre ?? '')) ?: 'Telar '.$telar,
                    'salon' => trim((string) ($row->SalonTejidoId ?? '')),
                    'group' => trim((string) ($row->Grupo ?? '')),
                    'sequence' => $sequence !== null ? (int) $sequence->Secuencia : null,
                ];
            })
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
