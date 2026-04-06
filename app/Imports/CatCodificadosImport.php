<?php

namespace App\Imports;

use App\Exceptions\ImportCancelledException;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Services\Planeacion\CatCodificados\Excel\CatCodificadosExcelRowMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use Throwable;

class CatCodificadosImport implements ToCollection, WithStartRow, WithChunkReading, SkipsEmptyRows, WithEvents
{
    private int $rowCount = 0;

    private int $processedCount = 0;

    private int $createdCount = 0;

    private int $updatedCount = 0;

    /**
     * @var array<int, array{fila: int, error: string}>
     */
    private array $errors = [];

    /**
     * @param  array<int, string>  $columnMap
     */
    public function __construct(
        private readonly ?string $importId = null,
        private readonly ?int $totalRows = null,
        private readonly array $columnMap = [],
    ) {
        Cache::put($this->cacheKey(), [
            'status' => 'pending',
            'total_rows' => $this->totalRows,
            'processed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
            'error_count' => 0,
            'cancelled' => false,
        ], 3600);
    }

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @return array<class-string, callable(): void>
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (): void {
                CatCodificados::unsetEventDispatcher();

                if (function_exists('ini_set')) {
                    @ini_set('memory_limit', '1024M');
                    @ini_set('max_execution_time', '0');
                }

                $connection = DB::connection((new CatCodificados())->getConnectionName());
                $connection->disableQueryLog();

                if ($connection->getDriverName() === 'sqlsrv') {
                    $connection->unprepared('SET NOCOUNT ON;');
                }
            },
            AfterImport::class => function (): void {
                $connection = DB::connection((new CatCodificados())->getConnectionName());

                if ($connection->getDriverName() === 'sqlsrv') {
                    $connection->unprepared('SET NOCOUNT OFF;');
                }

                $state = Cache::get($this->cacheKey(), []);
                $cachedErrors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
                $allErrors = $this->errors !== []
                    ? $this->errors
                    : $cachedErrors;

                Cache::put($this->cacheKey(), [
                    'status' => $this->isCancelled() ? 'cancelled' : 'done',
                    'total_rows' => $this->totalRows,
                    'processed_rows' => max((int) ($state['processed_rows'] ?? 0), $this->processedCount),
                    'created' => max((int) ($state['created'] ?? 0), $this->createdCount),
                    'updated' => max((int) ($state['updated'] ?? 0), $this->updatedCount),
                    'errors' => array_slice($allErrors, 0, 20),
                    'error_count' => max((int) ($state['error_count'] ?? 0), count($allErrors)),
                    'has_errors' => $allErrors !== [],
                    'cancelled' => $this->isCancelled(),
                ], 3600);

                CatCodificacionController::clearCache();
            },
        ];
    }

    public function collection(Collection $rows): void
    {
        $this->throwIfCancelled();

        $mapper = new CatCodificadosExcelRowMapper();
        $preparedRows = [];
        $processedRows = 0;

        foreach ($rows as $row) {
            $this->throwIfCancelled();

            $excelRow = $this->startRow() + $this->rowCount;
            $this->rowCount++;

            $rowValues = $row instanceof Collection
                ? $row->toArray()
                : (array) $row;

            $payload = $mapper->map($rowValues, $this->columnMap);
            if ($payload === []) {
                continue;
            }

            $processedRows++;
            $this->processedCount++;

            $ordenTejido = trim((string) ($payload['OrdenTejido'] ?? ''));
            if ($ordenTejido === '') {
                $this->pushError($excelRow, 'OrdenTejido no puede estar vacio.');
                continue;
            }

            $payload['OrdenTejido'] = $ordenTejido;
            $preparedRows[$this->lookupKey($ordenTejido)] = [
                'fila' => $excelRow,
                'ordenTejido' => $ordenTejido,
                'payload' => $payload,
            ];
        }

        $this->throwIfCancelled();

        [$created, $updated] = $this->persistPreparedRows($preparedRows);

        $this->createdCount += $created;
        $this->updatedCount += $updated;

        $this->updateCache($processedRows, $created, $updated);
    }

    private function cacheKey(): string
    {
        return 'excel_import_progress:' . ($this->importId ?? (string) Str::uuid());
    }

    /**
     * @param  array<string, array{fila: int, ordenTejido: string, payload: array<string, mixed>}>  $preparedRows
     * @return array{0: int, 1: int}
     */
    private function persistPreparedRows(array $preparedRows): array
    {
        if ($preparedRows === []) {
            return [0, 0];
        }

        $existingIds = $this->loadExistingIds(array_values(array_map(
            static fn (array $entry): string => $entry['ordenTejido'],
            $preparedRows
        )));
        $created = 0;
        $updated = 0;
        $table = (new CatCodificados())->getTable();
        $insertBatch = [];
        $insertMeta = [];

        foreach ($preparedRows as $entry) {
            try {
                $lookupKey = $this->lookupKey($entry['ordenTejido']);

                if (isset($existingIds[$lookupKey])) {
                    DB::table($table)
                        ->where('Id', $existingIds[$lookupKey])
                        ->update($entry['payload']);

                    $updated++;
                    continue;
                }

                $insertBatch[] = $entry['payload'];
                $insertMeta[] = $entry;
            } catch (\Throwable $e) {
                $this->pushError($entry['fila'], $e->getMessage());
            }
        }

        if ($insertBatch === []) {
            return [$created, $updated];
        }

        try {
            DB::table($table)->insert($insertBatch);
            $created += count($insertBatch);
        } catch (\Throwable $e) {
            Log::warning('CatCodificadosImport batch insert fallback', [
                'error' => $e->getMessage(),
                'rows' => count($insertBatch),
            ]);

            foreach ($insertMeta as $entry) {
                try {
                    DB::table($table)->insert($entry['payload']);
                    $created++;
                } catch (\Throwable $rowException) {
                    $this->pushError($entry['fila'], $rowException->getMessage());
                }
            }
        }

        return [$created, $updated];
    }

    /**
     * @param  array<int, string>  $ordenesTejido
     * @return array<string, int>
     */
    private function loadExistingIds(array $ordenesTejido): array
    {
        if ($ordenesTejido === []) {
            return [];
        }

        $existing = CatCodificados::query()
            ->whereIn('OrdenTejido', $ordenesTejido)
            ->orderByDesc('Id')
            ->get(['Id', 'OrdenTejido']);

        $resolved = [];

        foreach ($existing as $registro) {
            $ordenTejido = trim((string) $registro->OrdenTejido);
            $lookupKey = $this->lookupKey($ordenTejido);

            if ($ordenTejido === '' || isset($resolved[$lookupKey])) {
                continue;
            }

            $resolved[$lookupKey] = (int) $registro->Id;
        }

        return $resolved;
    }

    private function lookupKey(string $ordenTejido): string
    {
        return 'ord:' . $ordenTejido;
    }

    private function pushError(int $excelRow, string $message): void
    {
        $this->errors[] = [
            'fila' => $excelRow,
            'error' => mb_substr(trim($message), 0, 200),
        ];
    }

    private function updateCache(int $processedInc, int $createdInc, int $updatedInc): void
    {
        $key = $this->cacheKey();
        $state = Cache::get($key, [
            'status' => 'processing',
            'total_rows' => $this->totalRows,
            'processed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
            'error_count' => 0,
            'cancelled' => false,
        ]);

        $state['status'] = 'processing';
        $state['processed_rows'] = (int) ($state['processed_rows'] ?? 0) + $processedInc;
        $state['created'] = (int) ($state['created'] ?? 0) + $createdInc;
        $state['updated'] = (int) ($state['updated'] ?? 0) + $updatedInc;
        $state['errors'] = array_slice($this->errors, 0, 20);
        $state['error_count'] = count($this->errors);
        $state['has_errors'] = $this->errors !== [];
        $state['cancelled'] = false;

        Cache::put($key, $state, 3600);
    }

    public function failed(Throwable $e): void
    {
        if (!$e instanceof ImportCancelledException) {
            return;
        }

        $state = Cache::get($this->cacheKey(), []);
        Cache::put($this->cacheKey(), [
            'status' => 'cancelled',
            'total_rows' => $this->totalRows,
            'processed_rows' => max((int) ($state['processed_rows'] ?? 0), $this->processedCount),
            'created' => max((int) ($state['created'] ?? 0), $this->createdCount),
            'updated' => max((int) ($state['updated'] ?? 0), $this->updatedCount),
            'errors' => array_slice(is_array($state['errors'] ?? null) ? $state['errors'] : $this->errors, 0, 20),
            'error_count' => max((int) ($state['error_count'] ?? 0), count($this->errors)),
            'has_errors' => $this->errors !== [],
            'cancelled' => true,
        ], 3600);
    }

    protected function isCancelled(): bool
    {
        if ($this->importId === null) {
            return false;
        }

        return Cache::get(CatCodificacionController::cancellationCacheKey($this->importId), false) === true;
    }

    protected function throwIfCancelled(): void
    {
        if (!$this->isCancelled()) {
            return;
        }

        throw new ImportCancelledException('Importacion cancelada por el usuario.');
    }
}
