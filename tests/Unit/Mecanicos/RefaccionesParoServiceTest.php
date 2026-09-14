<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Services\Mecanicos\RefaccionesParoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefaccionesParoServiceTest extends TestCase
{
    private ?string $towSqlitePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = tempnam(sys_get_temp_dir(), 'towell_tow_tow_');
        if ($tmp === false) {
            $this->markTestSkipped('No se pudo crear archivo temporal para sqlsrv_tow_tow');
        }

        $this->towSqlitePath = $tmp;
        config()->set('database.connections.sqlsrv_tow_tow', [
            'driver' => 'sqlite',
            'database' => $this->towSqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_tow_tow');
        DB::connection('sqlsrv_tow_tow')->getPdo();

        $schema = Schema::connection('sqlsrv_tow_tow');
        $schema->create('TwRefacionesTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->date('date')->nullable();
            $table->string('Status')->nullable();
            $table->string('OrdenEasyMaint')->nullable();
        });
        $schema->create('TwRefaccionesLine', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio');
            $table->string('ItemID')->nullable();
            $table->string('ItemName')->nullable();
            $table->float('InventQty')->nullable();
            $table->float('CostAmount')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlsrv_tow_tow');
        if ($this->towSqlitePath !== null && is_file($this->towSqlitePath)) {
            @unlink($this->towSqlitePath);
        }

        parent::tearDown();
    }

    public function test_sin_folio_de_paro_no_consulta_tow_tow(): void
    {
        $this->insertarPartidasEjemplo();

        $resultado = (new RefaccionesParoService)->porFolioParo('   ');

        $this->assertSame(RefaccionesParoService::ESTADO_SIN_PARO, $resultado['estado']);
        $this->assertSame([], $resultado['filas']);
        $this->assertNotSame('', $resultado['mensaje']);
    }

    public function test_join_devuelve_solo_las_partidas_del_folio_de_paro(): void
    {
        $this->insertarPartidasEjemplo();

        $resultado = (new RefaccionesParoService)->porFolioParo('PF09844');

        $this->assertSame(RefaccionesParoService::ESTADO_OK, $resultado['estado']);
        $this->assertSame('PF09844', $resultado['folioParo']);
        $this->assertSame(
            ['R191372', 'R191378'],
            array_column($resultado['filas'], 'folio'),
        );
        $this->assertSame('U0830', $resultado['filas'][0]['articulo']);
        $this->assertSame('TORNILLO DE VARIAS MEDIDAS', $resultado['filas'][0]['nombre']);
        $this->assertSame('2', $resultado['filas'][0]['cantidad']);
        $this->assertSame('4.78', $resultado['filas'][0]['importe']);
        $this->assertSame('14/09/2026', $resultado['filas'][0]['fecha']);
        $this->assertSame('Registrado', $resultado['filas'][0]['status']);
        $this->assertSame('T0009', $resultado['filas'][1]['articulo']);
        $this->assertSame('4', $resultado['filas'][1]['cantidad']);
        $this->assertSame('2.87', $resultado['filas'][1]['importe']);
        $this->assertSame(6.0, $resultado['totalCantidad']);
        $this->assertEqualsWithDelta(7.65, $resultado['totalImporte'], 0.001);
    }

    public function test_trimea_el_folio_de_paro_guardado_en_easymaint(): void
    {
        DB::connection('sqlsrv_tow_tow')->table('TwRefacionesTable')->insert([
            'Folio' => 'R191400',
            'date' => '2026-09-14',
            'Status' => 'Registrado',
            'OrdenEasyMaint' => ' PF09844 ',
        ]);
        DB::connection('sqlsrv_tow_tow')->table('TwRefaccionesLine')->insert([
            'Folio' => 'R191400',
            'ItemID' => 'X1',
            'ItemName' => 'TUERCA',
            'InventQty' => 1,
            'CostAmount' => 1.10,
        ]);

        $resultado = (new RefaccionesParoService)->porFolioParo('PF09844');

        $this->assertSame(['R191400'], array_column($resultado['filas'], 'folio'));
    }

    public function test_paro_sin_partidas_queda_vacio(): void
    {
        $this->insertarPartidasEjemplo();

        $resultado = (new RefaccionesParoService)->porFolioParo('PF99999');

        $this->assertSame(RefaccionesParoService::ESTADO_VACIO, $resultado['estado']);
        $this->assertSame([], $resultado['filas']);
        $this->assertStringContainsString('PF99999', (string) $resultado['mensaje']);
    }

    public function test_un_error_de_conexion_no_revienta_y_deja_mensaje(): void
    {
        config()->set('database.connections.sqlsrv_tow_tow.database', sys_get_temp_dir().'/towell-inexistente/tow.sqlite');
        DB::purge('sqlsrv_tow_tow');
        Log::spy();

        $resultado = (new RefaccionesParoService)->porFolioParo('PF09844');

        $this->assertSame(RefaccionesParoService::ESTADO_ERROR, $resultado['estado']);
        $this->assertSame('PF09844', $resultado['folioParo']);
        $this->assertSame([], $resultado['filas']);
        $this->assertNotSame('', $resultado['mensaje']);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'No se pudieron consultar las refacciones en Tow_Tow.'
                && $context['folio_paro'] === 'PF09844');
    }

    public function test_la_parcial_declara_las_columnas_del_layout(): void
    {
        $vista = file_get_contents(resource_path('views/modulos/mecanicos/ordenes-trabajo/_refacciones.blade.php'));

        $this->assertIsString($vista);
        $this->assertStringContainsString('Artículo', $vista);
        $this->assertStringContainsString('Cantidad', $vista);
        $this->assertStringContainsString('Importe', $vista);
        $this->assertStringContainsString('EasyMaint', $vista);
    }

    public function test_captura_incluye_la_parcial_de_refacciones(): void
    {
        $vista = file_get_contents(resource_path('views/modulos/mecanicos/ordenes-trabajo/captura.blade.php'));

        $this->assertIsString($vista);
        $this->assertStringContainsString('modulos.mecanicos.ordenes-trabajo._refacciones', $vista);
    }

    private function insertarPartidasEjemplo(): void
    {
        DB::connection('sqlsrv_tow_tow')->table('TwRefacionesTable')->insert([
            [
                'Folio' => 'R191372',
                'date' => '2026-09-14',
                'Status' => 'Registrado',
                'OrdenEasyMaint' => 'PF09844',
            ],
            [
                'Folio' => 'R191378',
                'date' => '2026-09-14',
                'Status' => 'Registrado',
                'OrdenEasyMaint' => 'PF09844',
            ],
            [
                'Folio' => 'R199999',
                'date' => '2026-09-14',
                'Status' => 'Registrado',
                'OrdenEasyMaint' => 'PF00001',
            ],
        ]);
        DB::connection('sqlsrv_tow_tow')->table('TwRefaccionesLine')->insert([
            [
                'Folio' => 'R191372',
                'ItemID' => 'U0830',
                'ItemName' => 'TORNILLO DE VARIAS MEDIDAS',
                'InventQty' => 2,
                'CostAmount' => 4.78,
            ],
            [
                'Folio' => 'R191378',
                'ItemID' => 'T0009',
                'ItemName' => 'TORNILLO 4X10 C/ALLEN',
                'InventQty' => 4,
                'CostAmount' => 2.87,
            ],
            [
                'Folio' => 'R199999',
                'ItemID' => 'OTRO',
                'ItemName' => 'NO DEBE SALIR',
                'InventQty' => 9,
                'CostAmount' => 99.99,
            ],
        ]);
    }
}
