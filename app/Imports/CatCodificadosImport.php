<?php

namespace App\Imports;

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

    // Campos numéricos (INT)
    private const INT_LOOKUP = [
        'TelarId' => true, 'Peine' => true, 'Ancho' => true,
        'Largo' => true, 'VelocMinima' => true, 'Cuenta' => true,
        'Cuenta_2' => true, 'TIRAS' => true,
    ];

    // Campos numéricos (FLOAT)
    private const FLOAT_LOOKUP = [
        'Rizo' => true, 'Hilo_2' => true, 'Pie' => true, 'Hilo_3' => true,
        'NoMarbete' => true, 'Hilo_5' => true, 'Hilo_6' => true,
        'Hilo_7' => true, 'Hilo_8' => true, 'Hilo_9' => true,
    ];

    // Campos de fecha (DATE)
    private const DATE_LOOKUP = [
        'FechaOrden' => true,
        'FechaCumplimiento' => true,
        'FechaCompromiso' => true,
    ];

    // Mapping columnas Excel → campos DB
    private const FIELD_MAPPING = [
        0 => 'NumOrden', 1 => 'FechaOrden', 2 => 'FechaCumplimiento',
        3 => 'Departamento', 4 => 'TelarId', 5 => 'Prioridad',
        6 => 'Modelo', 7 => 'ClaveModelo', 8 => 'Tamano',
        9 => 'InventSizeId', 10 => 'Tolerancia', 11 => 'CodigoDibujo',
        12 => 'FechaCompromiso', 13 => 'FlogsId', 14 => 'Clave',
        15 => 'Cantidad', 16 => 'Peine', 17 => 'Ancho', 18 => 'Largo',
        19 => 'P_crudo', 20 => 'Luchaje', 21 => 'Tra', 22 => 'Hilo',
        23 => 'CodColorTrama', 24 => 'NombreColorTrama', 25 => 'OBS_Trama',
        26 => 'Tipoplano', 27 => 'Medplano', 28 => 'TipoRizo',
        29 => 'AlturaRizo', 30 => 'OBS', 31 => 'VelocMinima',
        32 => 'Rizo', 33 => 'Hilo_2', 34 => 'Cuenta', 35 => 'OBS_2',
        36 => 'Pie', 37 => 'Hilo_3', 38 => 'Cuenta_2', 39 => 'OBS_3',
        40 => 'C1', 41 => 'OBS_11', 42 => 'C2', 43 => 'OBS_12',
        44 => 'C3', 45 => 'OBS_13', 46 => 'C4', 47 => 'OBS_14',
        48 => 'MedCenefa', 49 => 'MedInicioRizoCenefa', 50 => 'Razurada',
        51 => 'TIRAS', 52 => 'RepeticionesP/corte', 53 => 'NoMarbete',
        54 => 'CambioRepaso', 55 => 'Vendedor', 56 => 'NoOrden',
        57 => 'Observaciones', 58 => 'TramaAnchoPeine', 59 => 'LogLuchaTotal',
        60 => 'C1TramaFondo', 61 => 'Hilo_4', 62 => 'OBS_4', 63 => 'PASADAS',
        64 => 'C1_2', 65 => 'Hilo_5', 66 => 'OBS_5', 67 => 'CodColor',
        68 => 'NombreColor', 69 => 'PASADAS_2', 70 => 'C2_2',
        71 => 'Hilo_6', 72 => 'OBS_6', 73 => 'CodColor_2',
        74 => 'NombreColor_2', 75 => 'PASADAS_3', 76 => 'C3_2',
        77 => 'Hilo_7', 78 => 'OBS_7', 79 => 'CodColor_3',
        80 => 'NombreColor_3', 81 => 'PASADAS_4', 82 => 'C4_2',
        83 => 'Hilo_8', 84 => 'OBS_8', 85 => 'Cod Color_4',
        86 => 'NombreColor_4', 87 => 'PASADAS_5', 88 => 'C5',
        89 => 'Hilo_9', 90 => 'OBS_9', 91 => 'Cod Color_5',
        92 => 'NombreColor_5', 93 => 'PASADAS_6', 94 => 'TOTAL',
        95 => 'RespInicio', 96 => 'HrInicio', 97 => 'HrTermino',
        98 => 'MinutosCambio', 99 => 'PesoMuestra', 100 => 'RegAlinacion',
        101 => 'estecamponotienenombre1', 102 => 'OBSParaPro',
        103 => 'CantidadProducir_2', 104 => 'Tejidas', 105 => 'pzaXrollo',
    ];

    public function __construct(?string $importId = null, ?int $totalRows = null)
    {
        $this->importId = $importId ?? (string) Str::uuid();
        $this->totalRows = $totalRows;

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

    // Chunk pequeño = jobs rápidos y estables
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
                \App\Models\catcodificados\CatCodificados::unsetEventDispatcher();

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

                // Invalidar cache de getAllFast después de importar
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

            foreach (self::FIELD_MAPPING as $i => $field) {
                $value = $rowArr[$i] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $data[$field] = $this->cleanValue($value, $field);
            }

            // Fila realmente vacía / sin clave base
            if (
                empty($data['NumOrden']) &&
                empty($data['Clave']) &&
                empty($data['Modelo'])
            ) {
                continue;
            }

            $batchData[] = $data;
            $processed++;
        }

        if (!empty($batchData)) {
            try {
                // Si necesitas ignorar duplicados, cámbialo a ->insertOrIgnore($batchData)
                DB::table('CatCodificados')->insert($batchData);
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

        // Manejar campos de fecha
        if (isset(self::DATE_LOOKUP[$field])) {
            // Si es un objeto DateTime de PhpSpreadsheet
            if ($val instanceof \DateTime) {
                return $val->format('Y-m-d');
            }
            // Si es un número (fecha serial de Excel)
            if (is_numeric($val)) {
                try {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    return null;
                }
            }
            // Si es una cadena, intentar convertirla
            if (is_string($val)) {
                $trimmed = trim($val);
                if (empty($trimmed)) {
                    return null;
                }
                try {
                    $date = new \DateTime($trimmed);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Si no se puede convertir, devolver null
                    return null;
                }
            }
            return null;
        }

        if (isset(self::INT_LOOKUP[$field])) {
            return is_numeric($val) ? (int) $val : null;
        }

        if (isset(self::FLOAT_LOOKUP[$field])) {
            return is_numeric($val) ? (float) $val : null;
        }

        return is_string($val) ? trim($val) : $val;
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
