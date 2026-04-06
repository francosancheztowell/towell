<?php

namespace Tests\Feature;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Sistema\Usuario;
use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use App\Services\Planeacion\CatCodificados\Excel\CatCodificadosExcelHeaderMapper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CatCodificadosExcelImportTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', 'sqlite');
        config()->set('queue.connections.database.table', 'jobs');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('CatCodificados', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->integer('OrdPrincipal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->integer('OrdCompartidaLider')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('Saldos')->nullable();
            $table->date('FechaTejido')->nullable();
            $table->date('FechaCumplimiento')->nullable();
            $table->string('Departamento')->nullable();
            $table->integer('TelarId')->nullable();
            $table->string('Prioridad')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('ClaveModelo')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->date('FechaCompromiso')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->string('Clave')->nullable();
            $table->float('Cantidad')->nullable();
            $table->integer('Peine')->nullable();
            $table->integer('Ancho')->nullable();
            $table->integer('Largo')->nullable();
            $table->string('FibraId')->nullable();
            $table->string('ColorTrama')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->string('CambioRepaso')->nullable();
            $table->string('Vendedor')->nullable();
            $table->string('NoOrden')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('Obs5')->nullable();
            $table->float('Total')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->float('Tejidas')->nullable();
            $table->integer('pzaXrollo')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_import_route_updates_existing_rows_creates_new_rows_and_exposes_progress(): void
    {
        CatCodificados::create([
            'OrdenTejido' => 'OT-001',
            'Nombre' => 'Anterior',
            'NoOrden' => 'LOCAL-001',
            'Produccion' => 10,
        ]);

        $file = $this->buildWorkbook([
            $this->makeDataRow([
                0 => 'OT-001',
                1 => '2026-04-05',
                2 => '2026-04-10',
                3 => 'Jacquard',
                4 => 8,
                6 => 'Modelo actualizado',
                7 => 'KM-100',
                8 => 'AX-100',
                9 => 4942.0,
                13 => 'FLOG-1',
                15 => 'CL-100',
                16 => 320,
                24 => 'AZUL',
                50 => 4,
                51 => 9,
                52 => 12,
                53 => 'Repaso A',
                54 => 'Ventas',
                55 => 'NO-DEBE-USARSE',
                56 => 'Observacion actualizada',
                83 => 48,
                88 => 1.75,
                93 => 22,
                94 => 5,
            ]),
            $this->makeDataRow([
                0 => 'OT-002',
                1 => '2026-04-06',
                4 => 12,
                6 => 'Modelo nuevo',
                7 => 'KM-200',
                8 => 'AX-200',
                16 => 150,
                24 => 'VERDE',
            ]),
        ]);

        $response = $this->actingAs($this->usuario())
            ->post(route('planeacion.codificacion.excel'), [
                'archivo_excel' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.completed', true);
        $response->assertJsonPath('data.queued', false);
        $response->assertJsonPath('data.summary.created', 1);
        $response->assertJsonPath('data.summary.updated', 1);

        $importId = $response->json('data.import_id');
        $this->assertNotEmpty($importId);

        $this->assertDatabaseHas('CatCodificados', [
            'OrdenTejido' => 'OT-001',
            'Nombre' => 'Modelo actualizado',
            'NoOrden' => 'LOCAL-001',
            'CategoriaCalidad' => 'NO-DEBE-USARSE',
        ]);

        $this->assertDatabaseHas('CatCodificados', [
            'OrdenTejido' => 'OT-002',
            'Nombre' => 'Modelo nuevo',
        ]);

        $progress = $this->actingAs($this->usuario())
            ->getJson(route('planeacion.codificacion.excel.progress', ['id' => $importId]));

        $progress->assertOk();
        $progress->assertJsonPath('success', true);
        $progress->assertJsonPath('data.status', 'done');
        $progress->assertJsonPath('data.created', 1);
        $progress->assertJsonPath('data.updated', 1);
        $progress->assertJsonPath('data.error_count', 0);
    }

    public function test_import_route_rejects_invalid_headers_before_queueing(): void
    {
        $file = $this->buildWorkbook([], function (array $headers): array {
            $headers[0] = 'Encabezado incorrecto';

            return $headers;
        });

        $response = $this->actingAs($this->usuario())
            ->post(route('planeacion.codificacion.excel'), [
                'archivo_excel' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('errors.headers.0.column', 1);
        $response->assertJsonPath('errors.headers.0.column_letter', 'A');
    }

    public function test_import_route_counts_only_meaningful_rows_for_progress(): void
    {
        $file = $this->buildWorkbook(
            [
                $this->makeDataRow([
                    0 => 'OT-003',
                    4 => 9,
                    6 => 'Modelo unico',
                ]),
            ],
            null,
            function (Worksheet $sheet): void {
                $sheet->setCellValue('A30', '   ');
                $sheet->setCellValue('B30', ' ');
            }
        );

        $response = $this->actingAs($this->usuario())
            ->post(route('planeacion.codificacion.excel'), [
                'archivo_excel' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.total_rows', 1);
        $response->assertJsonPath('data.completed', true);
        $response->assertJsonPath('data.queued', false);
        $response->assertJsonPath('data.summary.created', 1);

        $importId = $response->json('data.import_id');

        $progress = $this->actingAs($this->usuario())
            ->getJson(route('planeacion.codificacion.excel.progress', ['id' => $importId]));

        $progress->assertOk();
        $progress->assertJsonPath('data.processed_rows', 1);
        $progress->assertJsonPath('data.created', 1);
        $progress->assertJsonPath('data.error_count', 0);
    }

    public function test_cancel_import_marks_state_and_deletes_pending_jobs(): void
    {
        $importId = 'cancel-test-import';

        Cache::put(CatCodificacionController::progressCacheKey($importId), [
            'status' => 'processing',
            'total_rows' => 50,
            'processed_rows' => 10,
            'created' => 3,
            'updated' => 2,
            'errors' => [],
            'error_count' => 0,
            'cancelled' => false,
        ], 3600);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'QueuedCatCodificadosImport', 'import_id' => $importId], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $response = $this->actingAs($this->usuario())
            ->post(route('planeacion.codificacion.excel.cancel', ['id' => $importId]), [], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.deleted_jobs', 1);

        $this->assertTrue(Cache::get(CatCodificacionController::cancellationCacheKey($importId)));
        $this->assertSame('cancelled', Cache::get(CatCodificacionController::progressCacheKey($importId))['status']);
        $this->assertDatabaseCount('jobs', 0);
    }

    private function usuario(): Usuario
    {
        $usuario = new Usuario([
            'idusuario' => 1,
            'nombre' => 'Planeacion',
            'contrasenia' => 'x',
            'numero_empleado' => '1',
            'area' => 'Planeacion',
        ]);
        $usuario->idusuario = 1;

        return $usuario;
    }

    /**
     * @param  array<int, array<int, mixed>>  $dataRows
     * @param  (callable(array<int, string>): array<int, string>)|null  $mutateHeaders
     * @param  (callable(Worksheet): void)|null  $mutateSheet
     */
    private function buildWorkbook(array $dataRows, ?callable $mutateHeaders = null, ?callable $mutateSheet = null): UploadedFile
    {
        $headerMapper = new CatCodificadosExcelHeaderMapper();
        $headers = $headerMapper->expectedHeaders();
        $headers = $mutateHeaders ? $mutateHeaders($headers) : $headers;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowNumber);
            $rowNumber++;
        }

        if ($mutateSheet !== null) {
            $mutateSheet($sheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'catcodificados_');
        if ($path === false) {
            throw new \RuntimeException('No fue posible crear un archivo temporal.');
        }

        $xlsxPath = $path . '.xlsx';
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        @unlink($path);
        $this->tempFiles[] = $xlsxPath;

        return new UploadedFile(
            $xlsxPath,
            'catcodificados.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * @param  array<int, mixed>  $valuesByIndex
     * @return array<int, mixed>
     */
    private function makeDataRow(array $valuesByIndex): array
    {
        $row = array_fill(0, 95, null);

        foreach ($valuesByIndex as $index => $value) {
            $row[$index] = $value;
        }

        return $row;
    }
}
