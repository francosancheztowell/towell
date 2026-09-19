<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarFlogSugeridoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Comportamiento del cluster de flogs extraído de LiberarOrdenesController.
 * Misma entrada → misma salida: cruce item+talla, desempate CE-100 > CE-99,
 * apply de cabecera/cliente AX y rechazo si AX no responde.
 */
class LiberarFlogSugeridoServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarFlogSugeridoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv_ti')->create('TwArticulosFelpas', function (Blueprint $table) {
            $table->string('ITEMID');
            $table->string('INVENTSIZEID')->nullable();
            $table->string('ITEMNAME')->nullable();
        });

        $this->attachDboFlogTables();

        $this->service = new LiberarFlogSugeridoService;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv_ti')->dropIfExists('TwArticulosFelpas');
        parent::tearDown();
    }

    private function attachDboFlogTables(): void
    {
        $conexion = DB::connection('sqlsrv_ti');
        if (! in_array('dbo', array_column($conexion->select('PRAGMA database_list'), 'name'), true)) {
            $conexion->statement("ATTACH DATABASE ':memory:' AS dbo");
        }

        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsTable" (
            "IDFLOG" TEXT, "NAMEPROYECT" TEXT, "CUSTNAME" TEXT, "ESTADOFLOG" INTEGER
        )');
        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsItemLine" (
            "IDFLOG" TEXT, "ITEMID" TEXT, "INVENTSIZEID" TEXT
        )');
        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsCustomer" (
            "IdFlog" TEXT, "CustName" TEXT, "CategoriaCalidad" TEXT
        )');
    }

    private function registro(string $itemId, string $inventSizeId = 'STD'): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->setAttribute('ItemId', $itemId);
        $r->setAttribute('InventSizeId', $inventSizeId);

        return $r;
    }

    private function sembrarFlog(
        string $idFlog,
        string $itemId,
        string $inventSizeId = 'STD',
        int $estado = 3,
        string $nombreProyecto = 'PROYECTO',
        ?string $custName = 'CLIENTE AX',
        ?string $categoria = 'A'
    ): void {
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsTable')->insert([
            'IDFLOG' => $idFlog,
            'NAMEPROYECT' => $nombreProyecto,
            'CUSTNAME' => $custName,
            'ESTADOFLOG' => $estado,
        ]);
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsItemLine')->insert([
            'IDFLOG' => $idFlog,
            'ITEMID' => $itemId,
            'INVENTSIZEID' => $inventSizeId,
        ]);
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsCustomer')->insert([
            'IdFlog' => $idFlog,
            'CustName' => $custName,
            'CategoriaCalidad' => $categoria,
        ]);
    }

    public function test_clave_item_talla_normaliza_mayusculas_y_espacios(): void
    {
        $this->assertSame('IT100|STD', LiberarFlogSugeridoService::claveItemTalla(' it100 ', ' std '));
    }

    public function test_numero_final_flog_desempata_por_digitos_finales(): void
    {
        $this->assertSame(100, LiberarFlogSugeridoService::numeroFinalFlog('CE-100'));
        $this->assertSame(99, LiberarFlogSugeridoService::numeroFinalFlog('CE-99'));
        $this->assertSame(0, LiberarFlogSugeridoService::numeroFinalFlog('SINNUMERO'));
    }

    public function test_catalogo_empata_por_item_y_talla_exactos_con_o_sin_sufijo(): void
    {
        DB::connection('sqlsrv_ti')->table('TwArticulosFelpas')->insert([
            ['ITEMID' => 'IT100-1', 'INVENTSIZEID' => 'STD', 'ITEMNAME' => 'CON SUFIJO'],
            ['ITEMID' => 'IT200', 'INVENTSIZEID' => 'STD', 'ITEMNAME' => 'SIN SUFIJO'],
        ]);

        $registros = collect([
            $this->registro('IT100', 'STD'),
            $this->registro('it100', 'std'),
            $this->registro('IT200', 'STD'),
            $this->registro('IT100', 'FEL'),
            $this->registro('IT999', 'STD'),
        ]);

        $claves = $this->service->clavesArticulosConFlog($registros);

        $decide = fn (ReqProgramaTejido $r) => isset($claves[
            LiberarFlogSugeridoService::claveItemTalla($r->ItemId, $r->InventSizeId)
        ]);

        $this->assertTrue($decide($registros[0]));
        $this->assertTrue($decide($registros[1]));
        $this->assertTrue($decide($registros[2]));
        $this->assertFalse($decide($registros[3]));
        $this->assertFalse($decide($registros[4]));
    }

    public function test_catalogo_vacio_si_el_lote_no_trae_items(): void
    {
        $this->assertSame([], $this->service->clavesArticulosConFlog(collect([
            $this->registro(''),
        ])));
    }

    public function test_lote_elige_el_flog_de_mayor_numero_final_y_omite_no_vigentes(): void
    {
        $this->sembrarFlog('CE-99', 'IT100', 'STD', 3, 'VIEJO');
        $this->sembrarFlog('CE-100', 'IT100', 'STD', 5, 'NUEVO');
        $this->sembrarFlog('CE-200', 'IT100', 'STD', 1, 'CERRADO');
        $this->sembrarFlog('CE-10', 'IT100', 'FEL', 4, 'OTRA TALLA');

        $sugeridos = $this->service->flogsSugeridosDelLote(collect([
            $this->registro('IT100', 'STD'),
            $this->registro('IT100', 'FEL'),
        ]));

        $this->assertSame('CE-100', $sugeridos['IT100|STD']);
        $this->assertSame('CE-10', $sugeridos['IT100|FEL']);
        $this->assertArrayNotHasKey('IT999|STD', $sugeridos);
    }

    public function test_lote_respeta_item_con_relleno_en_ax(): void
    {
        $this->sembrarFlog('CE-7', '  IT300  ', ' STD ');

        $sugeridos = $this->service->flogsSugeridosDelLote(collect([
            $this->registro('IT300', 'STD'),
        ]));

        $this->assertSame('CE-7', $sugeridos['IT300|STD']);
    }

    public function test_sugerir_elige_el_mas_reciente_y_devuelve_null_si_no_hay(): void
    {
        $this->sembrarFlog('CE-99', 'IT100', 'STD', 3, 'VIEJO');
        $this->sembrarFlog('CE-100', 'IT100', 'STD', 21, 'NUEVO');

        $this->assertSame(
            ['flogsId' => 'CE-100', 'nombreProyecto' => 'NUEVO'],
            $this->service->sugerir('IT100', 'STD')
        );
        $this->assertNull($this->service->sugerir('IT100', 'FEL'));
    }

    public function test_aplicar_datos_flog_vigente_arrastra_cabecera_y_cliente(): void
    {
        $this->sembrarFlog('CE-55', 'IT100', 'STD', 3, 'PROY AX', 'CLIENTE CAB', 'B');
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsCustomer')
            ->where('IdFlog', 'CE-55')
            ->update(['CustName' => 'CLIENTE FLOG', 'CategoriaCalidad' => 'PREMIUM']);

        $registro = $this->registro('IT100');
        $registro->CustName = 'VIEJO';
        $registro->NombreProyecto = 'VIEJO';

        $this->assertNull($this->service->aplicarDatosFlog($registro, 'CE-55'));
        $this->assertSame('CE-55', $registro->FlogsId);
        $this->assertSame('CE', $registro->TipoPedido);
        $this->assertSame('PROY AX', $registro->NombreProyecto);
        $this->assertSame('CLIENTE FLOG', $registro->CustName);
        $this->assertSame('PREMIUM', $registro->CategoriaCalidad);
    }

    public function test_aplicar_datos_flog_rechaza_inexistente_o_no_vigente(): void
    {
        $this->sembrarFlog('CE-1', 'IT100', 'STD', 1, 'CERRADO');

        $registro = $this->registro('IT100');
        $this->assertSame(
            'El flog "CE-1" no existe o no está vigente en AX.',
            $this->service->aplicarDatosFlog($registro, 'CE-1')
        );
        $this->assertSame(
            'El flog "CE-INVENTADO" no existe o no está vigente en AX.',
            $this->service->aplicarDatosFlog($registro, 'CE-INVENTADO')
        );
        $this->assertNull($registro->FlogsId);
    }
}
