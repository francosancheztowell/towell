<?php

namespace Tests\Unit\Calidad;

use App\Imports\ReqModelosCodificadosImport;
use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
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
            'errors' => 0,
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
        $this->assertSame([2, 1, 1, 0], [$progreso['processed_rows'], $progreso['created'], $progreso['updated'], $progreso['errors']]);
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
        $this->assertSame([3, 1, 0, 2], [$progreso['processed_rows'], $progreso['created'], $progreso['updated'], $progreso['errors']]);
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
        $this->assertSame(1, $this->progreso('imp-7')['errors']);
    }
}
