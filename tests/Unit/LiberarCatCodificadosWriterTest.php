<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarCatCodificadosWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Escritura de CatCodificados extraída de LiberarOrdenesController.
 * Misma entrada → mismos side effects. CreaProd = 1 al liberar es intencional.
 */
class LiberarCatCodificadosWriterTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarCatCodificadosWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');
        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->string('HiloAX')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->string('CambioRepaso')->nullable();
            $table->float('Densidad')->nullable();
            $table->string('Obs5')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('CustName')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->boolean('AsignarFlogs')->default(true);
            $table->float('PesoMuestra')->nullable();
            $table->integer('OrdPrincipal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->integer('OrdCompartidaLider')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->string('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });

        $schema->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->integer('OrdPrincipal')->nullable();
        });

        $this->writer = new LiberarCatCodificadosWriter;
    }

    protected function tearDown(): void
    {
        foreach (['ReqModelosCodificados', 'CatCodificados'] as $tabla) {
            Schema::connection('sqlsrv')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    private function programa(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->Id = $attrs['Id'] ?? 10;
        $r->NoProduccion = $attrs['NoProduccion'] ?? '77001';
        $r->NoTelarId = $attrs['NoTelarId'] ?? '201';
        $r->ItemId = $attrs['ItemId'] ?? 'IT100';
        $r->InventSizeId = $attrs['InventSizeId'] ?? 'STD';
        $r->SalonTejidoId = $attrs['SalonTejidoId'] ?? 'JACQUARD';
        $r->TamanoClave = $attrs['TamanoClave'] ?? 'MB-ARIA';
        $r->BomId = $attrs['BomId'] ?? 'BOM-01';
        $r->BomName = $attrs['BomName'] ?? 'LISTA 01';
        $r->HiloAX = $attrs['HiloAX'] ?? 'NE 20';
        $r->MtsRollo = $attrs['MtsRollo'] ?? 42.6;
        $r->PzasRollo = $attrs['PzasRollo'] ?? 90;
        $r->TotalRollos = $attrs['TotalRollos'] ?? 144;
        $r->TotalPzas = $attrs['TotalPzas'] ?? 12960;
        $r->Repeticiones = $attrs['Repeticiones'] ?? 30;
        $r->NoTiras = $attrs['NoTiras'] ?? 3;
        $r->SaldoMarbete = $attrs['SaldoMarbete'] ?? 144;
        $r->CombinaTram = $attrs['CombinaTram'] ?? 'MIX';
        $r->CambioHilo = $attrs['CambioHilo'] ?? 'NO';
        $r->Densidad = $attrs['Densidad'] ?? 0.1234;
        $r->Observaciones = $attrs['Observaciones'] ?? 'obs';
        $r->ActualizaLmat = $attrs['ActualizaLmat'] ?? 0;
        $r->FlogsId = $attrs['FlogsId'] ?? null;
        $r->NombreProyecto = $attrs['NombreProyecto'] ?? null;
        $r->CategoriaCalidad = $attrs['CategoriaCalidad'] ?? 'A';
        $r->CustName = $attrs['CustName'] ?? 'CLIENTE X';
        $r->PesoMuestra = $attrs['PesoMuestra'] ?? 12.5;
        $r->OrdPrincipal = $attrs['OrdPrincipal'] ?? 55;
        $r->OrdCompartida = $attrs['OrdCompartida'] ?? null;
        $r->OrdCompartidaLider = $attrs['OrdCompartidaLider'] ?? null;

        return $r;
    }

    private function sembrarCat(array $attrs = []): int
    {
        return (int) DB::connection('sqlsrv')->table('CatCodificados')->insertGetId(array_merge([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CreaProd' => 0,
            'CodigoDibujo' => 'OLD-DIB',
            'AsignarFlogs' => 1,
        ], $attrs));
    }

    public function test_actualizar_fuerza_crea_prod_a_uno_aunque_la_fila_ya_exista(): void
    {
        $this->sembrarCat(['CreaProd' => 0]);

        $ok = $this->writer->actualizar($this->programa());

        $this->assertTrue($ok);
        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertSame(1, (int) $cat->CreaProd);
    }

    public function test_actualizar_sincroniza_payload_de_liberacion(): void
    {
        $this->sembrarCat();

        $this->writer->actualizar($this->programa());

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertSame('BOM-01', $cat->BomId);
        $this->assertSame('LISTA 01', $cat->BomName);
        $this->assertSame('NE 20', $cat->HiloAX);
        $this->assertEqualsWithDelta(42.6, (float) $cat->MtsRollo, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $cat->PzasRollo, 0.001);
        $this->assertEqualsWithDelta(144.0, (float) $cat->TotalRollos, 0.001);
        $this->assertEqualsWithDelta(12960.0, (float) $cat->TotalPzas, 0.001);
        $this->assertSame(30, (int) $cat->Repeticiones);
        $this->assertSame(3, (int) $cat->NoTiras);
        $this->assertEqualsWithDelta(144.0, (float) $cat->NoMarbete, 0.001);
        $this->assertSame('MIX', $cat->CombinaTram);
        $this->assertSame('NO', $cat->CambioRepaso);
        $this->assertEqualsWithDelta(0.1234, (float) $cat->Densidad, 0.0001);
        $this->assertSame('obs', $cat->Obs5);
        $this->assertSame(1, (int) $cat->CreaProd);
        $this->assertSame('CLIENTE X', $cat->CustName);
        $this->assertSame('A', $cat->CategoriaCalidad);
        $this->assertEqualsWithDelta(12.5, (float) $cat->PesoMuestra, 0.001);
        $this->assertSame(55, (int) $cat->OrdPrincipal);
        $this->assertSame('Sistema', $cat->UsuarioModifica);
    }

    public function test_actualizar_ceil_de_total_rollos_y_no_toca_asignar_flogs_si_es_null(): void
    {
        $this->sembrarCat(['AsignarFlogs' => 0]);

        $this->writer->actualizar($this->programa(['TotalRollos' => 143.2]), null, null);

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertEqualsWithDelta(144.0, (float) $cat->TotalRollos, 0.001);
        $this->assertSame(0, (int) $cat->AsignarFlogs);
    }

    public function test_actualizar_escribe_asignar_flogs_solo_cuando_viene_decision(): void
    {
        $this->sembrarCat(['AsignarFlogs' => 1]);

        $this->writer->actualizar($this->programa(), null, false);

        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('AsignarFlogs'));
    }

    public function test_actualizar_no_pisa_codigo_dibujo_si_no_hay_explicito_ni_catalogo(): void
    {
        $this->sembrarCat([
            'CodigoDibujo' => 'KEEP-ME',
            'ItemId' => 'OTRO',
            'Departamento' => 'SMIT',
        ]);

        $this->writer->actualizar($this->programa([
            'ItemId' => 'SIN-MATCH',
            'InventSizeId' => 'XX',
            'SalonTejidoId' => 'NADA',
        ]));

        $this->assertSame(
            'KEEP-ME',
            DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('CodigoDibujo')
        );
    }

    public function test_actualizar_usa_codigo_dibujo_explicito_de_pantalla(): void
    {
        $this->sembrarCat(['CodigoDibujo' => 'OLD-DIB']);

        $this->writer->actualizar($this->programa(), 'DIB-PANTALLA');

        $this->assertSame(
            'DIB-PANTALLA',
            DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('CodigoDibujo')
        );
    }

    public function test_actualizar_sin_no_produccion_o_sin_fila_devuelve_false(): void
    {
        $this->assertFalse($this->writer->actualizar($this->programa(['NoProduccion' => ''])));
        $this->assertFalse($this->writer->actualizar($this->programa(['NoProduccion' => '99999'])));
        $this->assertSame(0, DB::connection('sqlsrv')->table('CatCodificados')->count());
    }

    public function test_actualizar_campo_mapea_saldo_marbete_a_no_marbete(): void
    {
        $this->sembrarCat(['NoMarbete' => 1]);

        $this->writer->actualizarCampo($this->programa(), 'SaldoMarbete', 287.4);

        $this->assertEqualsWithDelta(
            287.0,
            (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('NoMarbete'),
            0.001
        );
    }

    public function test_actualizar_campo_ceil_total_rollos_y_no_op_sin_folio(): void
    {
        $this->sembrarCat(['TotalRollos' => 10]);

        $this->writer->actualizarCampo($this->programa(), 'TotalRollos', 12.2);
        $this->assertEqualsWithDelta(
            13.0,
            (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('TotalRollos'),
            0.001
        );

        $this->writer->actualizarCampo($this->programa(['NoProduccion' => '']), 'TotalRollos', 99);
        $this->assertEqualsWithDelta(
            13.0,
            (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('TotalRollos'),
            0.001
        );
    }

    public function test_actualizar_req_modelos_por_orden_tejido(): void
    {
        DB::connection('sqlsrv')->table('ReqModelosCodificados')->insert([
            'OrdenTejido' => '77001',
            'TamanoClave' => 'MB-ARIA',
            'PesoMuestra' => 1,
            'OrdPrincipal' => 1,
        ]);

        $this->writer->actualizarReqModelos($this->programa([
            'PesoMuestra' => 18.25,
            'OrdPrincipal' => 77,
        ]));

        $modelo = DB::connection('sqlsrv')->table('ReqModelosCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertEqualsWithDelta(18.25, (float) $modelo->PesoMuestra, 0.001);
        $this->assertSame(77, (int) $modelo->OrdPrincipal);
    }

    public function test_resolver_codigo_dibujo_prioriza_item_mas_departamento(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['ItemId' => 'IT100', 'InventSizeId' => 'STD', 'Departamento' => 'JACQUARD', 'CodigoDibujo' => 'POR-SALON'],
            ['ItemId' => 'IT100', 'InventSizeId' => 'STD', 'Departamento' => 'SMIT', 'CodigoDibujo' => 'OTRO-SALON'],
            ['ItemId' => 'IT100', 'InventSizeId' => 'FEL', 'Departamento' => 'JACQUARD', 'CodigoDibujo' => ''],
        ]);

        $this->assertSame(
            'POR-SALON',
            $this->writer->resolverCodigoDibujo('IT100', 'FEL', 'JACQUARD')
        );
        $this->assertSame(
            'OTRO-SALON',
            $this->writer->resolverCodigoDibujo('IT100', 'STD', 'SMIT')
        );
        $this->assertNull($this->writer->resolverCodigoDibujo('', '', ''));
    }
}
