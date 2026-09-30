<?php

namespace Tests\Unit\Calidad;

use App\Imports\ReqModelosCodificadosImport;
use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * CAL-04: el import de modelos codificados (en cola, ShouldQueue) no tenia ningun test.
 * Encabezados en las filas 1 y 2 (compuestos "fila1|fila2"), datos desde la 3; upsert por
 * (TamanoClave, OrdenTejido) y progreso en cache (excel_import_progress:<id>).
 */
class ReqModelosCodificadosImportTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const ENCABEZADOS = ['Clave mod.', 'Orden', 'Fecha  Orden', 'Modelo', 'Tra', 'Peine', 'Pasadas TOTAL', 'Cantidad a Producir'];

    /** @var array<int, string> */
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        config()->set('queue.default', 'sync');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporales as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<int, mixed>>  $datos
     */
    private function filas(array $datos): Collection
    {
        $filas = [collect(self::ENCABEZADOS), collect(array_fill(0, count(self::ENCABEZADOS), null))];
        foreach ($datos as $fila) {
            $filas[] = collect($fila);
        }

        return collect($filas);
    }

    /**
     * @return array<string, mixed>
     */
    private function progreso(string $id): array
    {
        return Cache::get('excel_import_progress:'.$id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function errores(ReqModelosCodificadosImport $import): array
    {
        return (fn () => $this->errors)->call($import);
    }

    public function test_el_constructor_deja_el_progreso_en_pending(): void
    {
        new ReqModelosCodificadosImport('imp-1', 10);

        $this->assertSame([
            'status' => 'pending',
            'total_rows' => 10,
            'processed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
            'total_errors' => 0,
            'has_errors' => false,
        ], $this->progreso('imp-1'));
    }

    public function test_con_menos_de_tres_filas_registra_error_y_no_escribe(): void
    {
        $import = new ReqModelosCodificadosImport('imp-2');

        $import->collection(collect([collect(self::ENCABEZADOS), collect()]));

        $this->assertSame(0, ReqModelosCodificados::count());
        $this->assertSame('Se requieren al menos 3 filas (2 encabezados + datos).', $this->errores($import)[0]['error']);
    }

    public function test_fila_valida_crea_el_registro_con_conversiones(): void
    {
        $import = new ReqModelosCodificadosImport('imp-3', 1);

        $import->collection($this->filas([
            ['A123', '5001', 45200, 'TOALLA 50X90', '5/3', '72', '1200', '1500'],
        ]));

        $modelo = ReqModelosCodificados::firstOrFail();
        $this->assertSame('A123', $modelo->TamanoClave);
        $this->assertSame('5001', $modelo->OrdenTejido);
        $this->assertSame('TOALLA 50X90', $modelo->Nombre);
        // Fraccion: (5 * 10) / 3 redondeado a 1 decimal.
        $this->assertSame(16.7, $modelo->CalibreTrama);
        $this->assertSame(72, $modelo->Peine);
        $this->assertSame(1200.0, $modelo->Total);
        $this->assertSame('1500', $modelo->Pedido);
        $this->assertSame(
            Carbon::instance(ExcelDate::excelToDateTimeObject(45200))->format('Y-m-d'),
            $modelo->FechaTejido->format('Y-m-d')
        );

        $this->assertSame([], $this->errores($import));
        $progreso = $this->progreso('imp-3');
        $this->assertSame(1, $progreso['processed_rows']);
        $this->assertSame(1, $progreso['created']);
        $this->assertSame(0, $progreso['updated']);
        $this->assertSame('done', $progreso['status'], 'processed_rows alcanzo total_rows');
    }

    public function test_misma_clave_y_orden_actualiza_en_vez_de_duplicar(): void
    {
        ReqModelosCodificados::create(['TamanoClave' => 'A123', 'OrdenTejido' => '5001', 'Peine' => 10]);
        $import = new ReqModelosCodificadosImport('imp-4', 2);

        $import->collection($this->filas([
            ['A123', '5001', null, 'TOALLA 50X90', null, '80', null, null],
            ['B456', '5002', null, 'TOALLA 40X70', null, '60', null, null],
        ]));

        $this->assertSame(2, ReqModelosCodificados::count());
        $this->assertSame(80, ReqModelosCodificados::where('TamanoClave', 'A123')->value('Peine'));
        $progreso = $this->progreso('imp-4');
        $this->assertSame([2, 1, 1, 0], [$progreso['processed_rows'], $progreso['created'], $progreso['updated'], $progreso['total_errors']]);
    }

    public function test_claves_vacias_rechazan_la_fila_y_cuentan_como_error(): void
    {
        $import = new ReqModelosCodificadosImport('imp-5', 3);

        $import->collection($this->filas([
            [null, '5001', null, 'TOALLA 1', null, null, null, null],
            ['A123', '', null, 'TOALLA 2', null, null, null, null],
            ['C789', '5003', null, 'TOALLA 3', null, null, null, null],
        ]));

        $this->assertSame(['C789'], ReqModelosCodificados::pluck('TamanoClave')->all());
        $this->assertSame([
            ['fila' => 3, 'error' => 'TamanoClave no puede estar vacío', 'datos' => []],
            ['fila' => 4, 'error' => 'OrdenTejido no puede estar vacío', 'datos' => []],
        ], $this->errores($import));
        $progreso = $this->progreso('imp-5');
        $this->assertTrue($progreso['has_errors']);
        $this->assertSame([3, 4], array_column($progreso['errors'], 'fila'));
        $this->assertSame([3, 1, 0, 2], [$progreso['processed_rows'], $progreso['created'], $progreso['updated'], $progreso['total_errors']]);
    }

    public function test_formulas_textos_de_relleno_y_texto_sin_digitos_se_descartan(): void
    {
        $import = new ReqModelosCodificadosImport('imp-6');

        $import->collection($this->filas([
            ['A1', '6001', null, '=CONCATENAR(A1,B1)', 'N/A', null, null, null],
            ['A2', '6002', null, 'SI(H3>0)', '7/3*', null, null, null],
            // Sin ningun digito y mas de 2 caracteres: se trata como descripcion, no como valor.
            ['A3', '6003', null, 'TOALLA', null, null, null, 'ABIERTO'],
        ]));

        $this->assertSame(3, ReqModelosCodificados::count());
        foreach (ReqModelosCodificados::orderBy('TamanoClave')->get() as $modelo) {
            $this->assertNull($modelo->Nombre, $modelo->TamanoClave);
            $this->assertNull($modelo->CalibreTrama, $modelo->TamanoClave);
            $this->assertNull($modelo->Pedido, $modelo->TamanoClave);
        }
    }

    public function test_un_fallo_al_guardar_se_registra_con_la_fila_y_sigue(): void
    {
        $import = new ReqModelosCodificadosImport('imp-7', 2);
        ReqModelosCodificados::saving(function (ReqModelosCodificados $modelo) {
            if ($modelo->TamanoClave === 'MAL1') {
                throw new \RuntimeException('violacion de columna');
            }
        });

        $import->collection($this->filas([
            ['MAL1', '7001', null, null, null, null, null, null],
            ['BIEN2', '7002', null, null, null, null, null, null],
        ]));

        $this->assertSame(['BIEN2'], ReqModelosCodificados::pluck('TamanoClave')->all());
        $errores = $this->errores($import);
        $this->assertCount(1, $errores);
        $this->assertSame(3, $errores[0]['fila']);
        $this->assertSame('violacion de columna', $errores[0]['error']);
        $this->assertSame('MAL1', $errores[0]['datos'][0]);
        $this->assertSame(1, $this->progreso('imp-7')['total_errors']);
        $this->assertSame('violacion de columna', $this->progreso('imp-7')['errors'][0]['error']);
    }

    public function test_fechas_de_texto(): void
    {
        $import = new ReqModelosCodificadosImport('imp-8');

        $import->collection($this->filas([
            // Con '/' no llega a D(): cleanExcelFormula descarta todo lo que sea solo digitos
            // y '/' sin ser una fraccion simple. Las fechas reales del Excel llegan como serial.
            ['F1', '8001', '15/03/2025', null, null, null, null, null],
            ['F2', '8002', '15-03-25', null, null, null, null, null],
            ['F3', '8003', '2025-03-15', null, null, null, null, null],
            ['F4', '8004', '31-02-2025', null, null, null, null, null],
            ['F5', '8005', '15-03-2025', null, null, null, null, null],
        ]));

        $fechas = ReqModelosCodificados::orderBy('TamanoClave')->get()
            ->mapWithKeys(fn (ReqModelosCodificados $m) => [$m->TamanoClave => $m->FechaTejido?->format('Y-m-d')])
            ->all();
        $this->assertSame(['F1' => null, 'F2' => '2025-03-15', 'F3' => '2025-03-15', 'F4' => null, 'F5' => '2025-03-15'], $fechas);
    }

    public function test_import_en_cola_deja_totales_y_errores_al_terminar(): void
    {
        // Antes AfterImport corria en otra copia del import (cada job de la cola deserializa
        // la suya) y pisaba created/updated con 0 y no dejaba ningun error en cache.
        ReqModelosCodificados::create(['TamanoClave' => 'B456', 'OrdenTejido' => '5002']);

        Excel::queueImport(new ReqModelosCodificadosImport('imp-9', 3), $this->xlsx([
            ['A123', '5001', null, 'TOALLA 50X90', '5/3', '72', '1200', '1500'],
            ['B456', '5002', null, 'TOALLA 40X70', null, '60', null, null],
            [null, '5003', null, 'TOALLA 30X50', null, null, null, null],
        ]));

        $this->assertSame(['A123', 'B456'], ReqModelosCodificados::orderBy('TamanoClave')->pluck('TamanoClave')->all());
        $progreso = $this->progreso('imp-9');
        $this->assertSame('done', $progreso['status']);
        $this->assertSame([3, 1, 1, 1], [$progreso['processed_rows'], $progreso['created'], $progreso['updated'], $progreso['total_errors']]);
        $this->assertTrue($progreso['has_errors']);
        $this->assertSame('TamanoClave no puede estar vacío', $progreso['errors'][0]['error']);
    }

    public function test_los_chunks_siguientes_usan_los_encabezados_del_primero(): void
    {
        // Chunks de 3 filas: el primero trae los 2 encabezados y 1 dato; los siguientes son
        // solo datos. Antes cada chunk tomaba sus 2 primeras filas como encabezado y las perdia.
        $import = new ReqModelosCodificadosImportChunk3('imp-10', 7);
        $datos = [];
        for ($n = 1; $n <= 7; $n++) {
            $datos[] = ["M{$n}", (string) (9000 + $n), null, "TOALLA {$n}", null, (string) (10 * $n), null, null];
        }

        Excel::queueImport($import, $this->xlsx($datos));

        $this->assertSame(
            ['M1' => 10, 'M2' => 20, 'M3' => 30, 'M4' => 40, 'M5' => 50, 'M6' => 60, 'M7' => 70],
            ReqModelosCodificados::orderBy('TamanoClave')->pluck('Peine', 'TamanoClave')->all()
        );
        $progreso = $this->progreso('imp-10');
        $this->assertSame([7, 7, 0], [$progreso['processed_rows'], $progreso['created'], $progreso['total_errors']]);
        $this->assertSame('done', $progreso['status']);
    }

    /**
     * Xlsx con los encabezados de la plantilla. La fila 2 no puede ir vacia: SkipsEmptyRows la
     * saltaria y la primera fila de datos pasaria a ser encabezado; 'Tra' va como subencabezado.
     *
     * @param  array<int, array<int, mixed>>  $datos
     */
    private function xlsx(array $datos): UploadedFile
    {
        $encabezados = self::ENCABEZADOS;
        $subencabezados = array_fill(0, count($encabezados), null);
        [$encabezados[4], $subencabezados[4]] = [null, 'Tra'];

        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray([$encabezados, $subencabezados, ...$datos], null, 'A1', true);
        $ruta = tempnam(sys_get_temp_dir(), 'reqmod').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->temporales[] = $ruta;

        return new UploadedFile($ruta, 'modelos.xlsx', null, null, true);
    }
}

/** Misma clase con chunks de 3 filas (la cola serializa el import: no puede ser anonima). */
class ReqModelosCodificadosImportChunk3 extends ReqModelosCodificadosImport
{
    public function chunkSize(): int
    {
        return 3;
    }
}
