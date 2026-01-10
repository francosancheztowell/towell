<?php

namespace App\Imports;

use App\Models\catcodificados\CatCodificados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;

class CatCodificadosImport implements
    ToCollection,
    WithStartRow,
    WithChunkReading,
    WithBatchInserts,
    SkipsEmptyRows,
    WithEvents,
    ShouldQueue
{
    private int $rowCount = 0;
    private int $createdCount = 0;
    private array $errors = [];
    private ?string $importId = null;
    private ?int $totalRows = null;
    private array $columnMapping = [];

    // Campos numericos (INT)
    private const INT_FIELDS = [
        'Id' => true,
        'TelarId' => true,
        'Peine' => true,
        'Ancho' => true,
        'Largo' => true,
        'NoTiras' => true,
        'Repeticiones' => true,
        'MinutosCambio' => true,
    ];

    // Campos numericos (FLOAT)
    private const FLOAT_FIELDS = [
        'NoMarbete' => true,
        'Pedido' => true,
        'Produccion' => true,
        'Saldos' => true,
        'MtsRollo' => true,
        'PzasRollo' => true,
        'TotalRollos' => true,
        'TotalPzas' => true,
        'Densidad' => true,
    ];

    // Campos de fecha (DATE)
    private const DATE_FIELDS = [
        'FechaTejido' => true,
        'FechaCumplimiento' => true,
        'FechaCompromiso' => true,
        'FechaCreacion' => true,
        'FechaModificacion' => true,
    ];

    // Campos de hora (TIME)
    private const TIME_FIELDS = [
        'HrInicio' => true,
        'HrTermino' => true,
        'HoraCreacion' => true,
        'HoraModificacion' => true,
    ];

    // Campos booleanos (BIT)
    private const BOOL_FIELDS = [
        'CreaProd' => true,
        'ActualizaLmat' => true,
    ];

    // Campos que deben mantenerse como string
    private const FORCE_STRING_FIELDS = [
        'ItemId' => true,
        'InventSizeId' => true,
        'ClaveModelo' => true,
        'Clave' => true,
        'CodigoDibujo' => true,
        'FlogsId' => true,
        'OrdenTejido' => true,
        'NoOrden' => true,
        'BomId' => true,
        'BomName' => true,
        'HiloAX' => true,
    ];

    public function __construct(?string $importId = null, ?int $totalRows = null)
    {
        $this->importId = $importId ?? (string) Str::uuid();
        $this->totalRows = $totalRows;
        $this->columnMapping = CatCodificados::COLUMNS;

        Cache::put($this->cacheKey(), [
            'status'         => 'pending',
            'total_rows'     => $this->totalRows,
            'processed_rows' => 0,
            'created'        => 0,
            'errors'         => [],
            'error_count'    => 0,
        ], 3600);
    }

    public function startRow(): int
    {
        return 2; // Saltar encabezado
    }

    // Chunk pequeno = jobs rapidos y estables
    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    private function cacheKey(): string
    {
        return 'excel_import_progress:' . $this->importId;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                // Sin eventos Eloquent
                CatCodificados::unsetEventDispatcher();

                if (function_exists('ini_set')) {
                    @ini_set('memory_limit', '1024M');
                    @ini_set('max_execution_time', '0');
                }

                // No log de queries (evita reventar memoria)
                DB::connection()->disableQueryLog();

                // Evita mensajes "X rows affected"
                DB::unprepared('SET NOCOUNT ON;');
            },

            AfterImport::class => function () {
                DB::unprepared('SET NOCOUNT OFF;');

                $state = Cache::get($this->cacheKey(), []);
                $state['status']         = 'done';
                $state['created']        = $this->createdCount;
                $state['processed_rows'] = $this->rowCount;

                if (count($this->errors) > 0) {
                    $state['has_errors']  = true;
                    $state['errors']      = array_slice($this->errors, 0, 20);
                    $state['error_count'] = count($this->errors);
                }

                Cache::put($this->cacheKey(), $state, 3600);

                // Invalidar cache de getAllFast despues de importar
                \App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController::clearCache();
            },
        ];
    }

    public function collection(Collection $rows)
    {
        $batchData = [];
        $processed = 0;

        foreach ($rows as $row) {
            $this->rowCount++;

            $rowArr = $row->toArray();
            $data   = [];

            foreach ($this->columnMapping as $i => $field) {
                $value = $rowArr[$i] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $data[$field] = $this->cleanValue($value, $field);
            }

            // Fila realmente vacia / sin clave base
            if (
                empty($data['OrdenTejido']) &&
                empty($data['Clave']) &&
                empty($data['Nombre'])
            ) {
                continue;
            }

            $batchData[] = $data;
            $processed++;
        }

        if (!empty($batchData)) {
            try {
                DB::table((new CatCodificados())->getTable())->insert($batchData);
                $this->createdCount += count($batchData);
            } catch (\Throwable $e) {
                Log::error('Batch insert error CatCodificados: ' . $e->getMessage());
            }

            $this->updateCache($processed);
        }
    }

    private function cleanValue($val, string $field)
    {
        if ($val === null || $val === '') {
            return null;
        }

        if (isset(self::DATE_FIELDS[$field])) {
            return $this->parseDate($val);
        }

        if (isset(self::TIME_FIELDS[$field])) {
            return $this->parseTime($val);
        }

        if (isset(self::BOOL_FIELDS[$field])) {
            return $this->parseBool($val);
        }

        if (isset(self::INT_FIELDS[$field])) {
            return is_numeric($val) ? (int) $val : null;
        }

        if (isset(self::FLOAT_FIELDS[$field])) {
            return is_numeric($val) ? (float) $val : null;
        }

        if (isset(self::FORCE_STRING_FIELDS[$field])) {
            return trim((string) $val);
        }

        return is_string($val) ? trim($val) : $val;
    }

    private function parseDate($val): ?string
    {
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d');
        }

        if (is_numeric($val)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($val)) {
            $trimmed = trim($val);
            if ($trimmed === '') {
                return null;
            }
            try {
                $date = new \DateTime($trimmed);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function parseTime($val): ?string
    {
        if ($val instanceof \DateTimeInterface) {
            return $val->format('H:i:s');
        }

        if (is_numeric($val)) {
            try {
                $time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                return $time->format('H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($val)) {
            $trimmed = trim($val);
            if ($trimmed === '') {
                return null;
            }
            return $trimmed;
        }

        return null;
    }

    private function parseBool($val): ?int
    {
        if (is_bool($val)) {
            return $val ? 1 : 0;
        }

        if (is_numeric($val)) {
            return ((int) $val) ? 1 : 0;
        }

        if (is_string($val)) {
            $normalized = strtolower(trim($val));
            if (in_array($normalized, ['1', 'true', 'si', 'yes'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return 0;
            }
        }

        return null;
    }

    private function updateCache(int $processed): void
    {
        $key   = $this->cacheKey();
        $cache = Cache::get($key, []);

        $cache['processed_rows'] = ($cache['processed_rows'] ?? 0) + $processed;
        $cache['created']        = ($cache['created'] ?? 0) + $processed;
        $cache['status']         = 'processing';

        Cache::put($key, $cache, 3600);
    }
}
