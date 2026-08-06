<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Contracts\Crudo\CrudoFlogProvider;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CrudoFlogService implements CrudoFlogProvider
{
    /** @var list<int> */
    private const ACTIVE_FLOG_STATES = [3, 4, 5, 21];

    public function __construct(
        private TrazabilidadFlogsService $flogService,
    ) {}

    public function find(?array $program, array $purchBarcodes = []): array
    {
        $program ??= [];
        $flogId = $this->text($program['flogId'] ?? null);
        $itemId = $this->text($program['itemId'] ?? null);
        $sizeId = $this->text($program['inventSizeId'] ?? null);
        $barcodes = $this->normalizeBarcodes($purchBarcodes);

        if ($flogId === '' && $barcodes === [] && ($itemId === '' || $sizeId === '')) {
            return $this->emptyResult('idle');
        }

        $cacheSeconds = max(0, (int) config('crudo.flog_cache_seconds', 300));
        $cacheKey = 'crudo:flog:v2:'.sha1(json_encode([
            $flogId,
            $itemId,
            $sizeId,
            $barcodes,
        ], JSON_THROW_ON_ERROR));

        if ($cacheSeconds > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $this->resolveImageUrls($cached);
            }
        }

        $result = $this->findUncached($flogId, $itemId, $sizeId, $barcodes);
        if ($cacheSeconds > 0 && in_array($result['status'], ['ok', 'not_found'], true)) {
            Cache::put($cacheKey, $result, now()->addSeconds($cacheSeconds));
        }

        return $this->resolveImageUrls($result);
    }

    /**
     * @param  list<string>  $barcodes
     * @return array<string, mixed>
     */
    private function findUncached(string $flogId, string $itemId, string $sizeId, array $barcodes): array
    {

        try {
            if ($flogId !== '') {
                $rows = $this->rowsForFlog($flogId);
                $source = 'program_flog';
            } elseif ($barcodes !== []) {
                $rows = $this->rowsForBarcodes($barcodes);
                $source = 'purch_barcode';
            } else {
                $rows = $this->rowsForItemAndSize($itemId, $sizeId);
                $source = 'item_size';
            }
        } catch (Throwable $exception) {
            Log::error('No se pudo consultar el Flog asociado al detalle de Crudo.', [
                'flog' => $flogId,
                'item_id' => $itemId,
                'invent_size_id' => $sizeId,
                'purch_barcodes' => $barcodes,
                'connection' => (string) config('crudo.connections.source', 'sqlsrv_ti'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->emptyResult('error');
        }

        if ($rows->isEmpty()) {
            return $this->emptyResult('not_found');
        }

        $resolvedFlogId = $flogId !== ''
            ? $flogId
            : $this->newestFlogId($rows);
        $flogRows = $rows
            ->filter(fn (object $row): bool => $this->text($row->flogId ?? null) === $resolvedFlogId)
            ->values();
        $line = $this->bestLine($flogRows, $itemId, $sizeId, $barcodes);
        $header = $flogRows->first();

        if (! $header) {
            return $this->emptyResult('not_found');
        }

        $clientAccount = $this->text($header->clientAccount ?? null);
        $clientName = $this->text($header->clientName ?? null);

        return [
            'status' => 'ok',
            'source' => $source,
            'flog' => $resolvedFlogId,
            'client' => trim($clientAccount.' '.$clientName),
            'clientAccount' => $clientAccount,
            'clientName' => $clientName,
            'itemId' => $this->text($line?->itemId ?? null),
            'inventSizeId' => $this->text($line?->inventSizeId ?? null),
            '_simulationSalesPath' => $this->text($line?->simulationSales ?? null),
            '_simulationDesignPath' => $this->text($line?->simulationDesign ?? null),
            'lineMatched' => $line !== null,
        ];
    }

    /** @return Collection<int, object> */
    private function rowsForFlog(string $flogId): Collection
    {
        // Comparación directa (SARGable): SQL Server ignora espacios finales en
        // columnas CHAR/NCHAR, mismo criterio que TrazabilidadFlogsService.
        return $this->baseQuery()
            ->where('ft.IDFLOG', $flogId)
            ->orderBy('fil.LINENUM')
            ->get();
    }

    /**
     * @param  list<string>  $barcodes
     * @return Collection<int, object>
     */
    private function rowsForBarcodes(array $barcodes): Collection
    {
        return $this->baseQuery()
            ->whereIn('fil.PURCHBARCODE', $barcodes)
            ->orderByDesc('ft.IDFLOG')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, object> */
    private function rowsForItemAndSize(string $itemId, string $sizeId): Collection
    {
        return $this->baseQuery()
            ->where('fil.ITEMID', $itemId)
            ->where('fil.INVENTSIZEID', $sizeId)
            ->whereIn('ft.ESTADOFLOG', self::ACTIVE_FLOG_STATES)
            ->orderByDesc('ft.IDFLOG')
            ->limit(50)
            ->get();
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return $this->source()
            ->table($this->table('flogs').' as ft')
            ->leftJoin($this->table('flog_lines').' as fil', 'fil.IDFLOG', '=', 'ft.IDFLOG')
            ->select([
                'ft.IDFLOG as flogId',
                'ft.CUSTACCOUNT as clientAccount',
                'ft.CUSTNAME as clientName',
                'fil.LINENUM as lineNumber',
                'fil.ITEMID as itemId',
                'fil.INVENTSIZEID as inventSizeId',
                'fil.PURCHBARCODE as purchBarcode',
                'fil.SIMULACIONVTAS as simulationSales',
                'fil.SIMULACIONDISENO as simulationDesign',
            ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $barcodes
     */
    private function bestLine(Collection $rows, string $itemId, string $sizeId, array $barcodes): ?object
    {
        $lines = $rows->filter(fn (object $row): bool => $this->text($row->lineNumber ?? null) !== '');
        if ($lines->isEmpty()) {
            return null;
        }

        if ($itemId !== '' && $sizeId !== '') {
            $match = $lines->first(fn (object $row): bool => $this->same($row->itemId ?? null, $itemId)
                && $this->same($row->inventSizeId ?? null, $sizeId)
            );
            if ($match) {
                return $match;
            }
        }

        if ($barcodes !== []) {
            $match = $lines->first(fn (object $row): bool => in_array(
                $this->text($row->purchBarcode ?? null),
                $barcodes,
                true,
            ));
            if ($match) {
                return $match;
            }
        }

        if ($itemId !== '') {
            $matchingItem = $lines->filter(fn (object $row): bool => $this->same($row->itemId ?? null, $itemId));
            if ($matchingItem->count() === 1) {
                return $matchingItem->first();
            }
        }

        return $lines->count() === 1 ? $lines->first() : null;
    }

    /** @param Collection<int, object> $rows */
    private function newestFlogId(Collection $rows): string
    {
        return $rows
            ->map(fn (object $row): string => $this->text($row->flogId ?? null))
            ->filter()
            ->unique()
            ->sortByDesc(function (string $id): array {
                preg_match('/(\d+)$/', $id, $matches);

                return [(int) ($matches[1] ?? 0), $id];
            })
            ->first() ?? '';
    }

    /**
     * @param  list<string>  $barcodes
     * @return list<string>
     */
    private function normalizeBarcodes(array $barcodes): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $barcode): string => mb_substr($this->text($barcode), 0, 80),
            $barcodes,
        ))));
    }

    /** @return array<string, mixed> */
    private function emptyResult(string $status): array
    {
        return [
            'status' => $status,
            'source' => null,
            'flog' => '',
            'client' => '',
            'clientAccount' => '',
            'clientName' => '',
            'itemId' => '',
            'inventSizeId' => '',
            '_simulationSalesPath' => '',
            '_simulationDesignPath' => '',
            'lineMatched' => false,
        ];
    }

    /**
     * Las URLs se construyen después de leer el caché para que siempre utilicen
     * el host y esquema de la petición actual, no los del proceso que creó el caché.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function resolveImageUrls(array $result): array
    {
        $salesPath = $this->text($result['_simulationSalesPath'] ?? null);
        $designPath = $this->text($result['_simulationDesignPath'] ?? null);

        unset($result['_simulationSalesPath'], $result['_simulationDesignPath']);

        $result['simulationSalesUrl'] = $this->crudoImageUrl($salesPath);
        $result['simulationDesignUrl'] = $this->crudoImageUrl($designPath);

        return $result;
    }

    /**
     * Reapunta la simulación a la ruta propia de Crudo: la de Trazabilidad exige
     * su permiso y devuelve la página HTML de 404 cuando el archivo no está.
     */
    private function crudoImageUrl(string $path): ?string
    {
        $url = $this->flogService->resolverUrlImagen($path);
        if ($url === null) {
            return null;
        }

        // Una simulación guardada ya como URL absoluta no pasa por el proxy UNC
        // y se sirve tal cual; solo se reescribe el proxy de Trazabilidad.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $file = trim((string) ($query['file'] ?? ''));

        return $file !== '' ? route('crudo.flog-imagen', ['file' => $file]) : $url;
    }

    private function source(): ConnectionInterface
    {
        return DB::connection((string) config('crudo.connections.source', 'sqlsrv_ti'));
    }

    private function table(string $key): string
    {
        return (string) config("crudo.tables.{$key}");
    }

    private function same(mixed $left, string $right): bool
    {
        return strcasecmp($this->text($left), $right) === 0;
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
