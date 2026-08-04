<?php

namespace Tests\Unit\Planeacion;

use App\Services\Planeacion\NoProduccionCierreAxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class NoProduccionCierreAxServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido', 20)->nullable()->index();
            $table->boolean('cierre_ax')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');

        parent::tearDown();
    }

    public function test_detecta_orden_cerrada_en_ax(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '36708',
            'cierre_ax' => 1,
        ]);

        $this->assertTrue(app(NoProduccionCierreAxService::class)->estaCerrada(' 36708 '));
    }

    public function test_no_bloquea_orden_abierta_nula_o_inexistente(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '36709', 'cierre_ax' => 0],
            ['OrdenTejido' => '36710', 'cierre_ax' => null],
        ]);

        $service = app(NoProduccionCierreAxService::class);

        $this->assertFalse($service->estaCerrada('36709'));
        $this->assertFalse($service->estaCerrada('36710'));
        $this->assertFalse($service->estaCerrada('99999'));
        $this->assertFalse($service->estaCerrada('  '));
        $this->assertFalse($service->estaCerrada(null));
    }

    public function test_bloquea_si_una_de_las_filas_de_la_orden_esta_cerrada(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => 'M2774', 'cierre_ax' => 0],
            ['OrdenTejido' => 'M2774', 'cierre_ax' => 1],
        ]);

        $this->assertTrue(app(NoProduccionCierreAxService::class)->estaCerrada('M2774'));
    }
}
