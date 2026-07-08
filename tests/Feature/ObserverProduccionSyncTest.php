<?php

namespace Tests\Feature;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Gap: al editar "Pedido" inline, UpdateHelpers::applyCantidad() a veces escribe en
 * Produccion (no en SaldoPedido) según cuál esté ya capturado. El observer no incluía
 * Produccion ni en el mapeo de sincronización a CatCodificados ni en los campos que
 * disparan el recálculo de marbetes (Repeticiones/PzasRollo/TotalRollos/TotalPzas).
 */
class ObserverProduccionSyncTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        $schema = Schema::connection('sqlsrv');

        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NombreProducto')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->float('NoTiras')->nullable();
            $table->float('LargoCrudo')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->integer('ProduccionMarbetes')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('SaldoMarbete')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->float('RollosProgramados')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });

        $schema->create('ReqPesosRollosTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('InventSizeId')->nullable();
            $table->float('PesoRollo')->nullable();
            $table->date('FechaModificacion')->nullable();
        });

        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->float('Produccion')->nullable();
            $table->integer('ProduccionMarbetes')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->time('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });
    }

    public function test_produccion_se_sincroniza_a_cat_codificados_al_editarla(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '99001',
            'Produccion' => 100,
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '99001', 'Produccion' => 100,
        ]);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->Produccion = 350;
        $programa->save();

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99001')->first();
        $this->assertSame(350.0, (float) $cat->Produccion);
    }

    public function test_editar_produccion_dispara_recalculo_de_marbetes(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'STD',
            'NoProduccion' => '99002',
            'PesoCrudo' => 614,
            'NoTiras' => 2,
            'LargoCrudo' => 102,
            // SaldoPedido/TotalPedido quedan null: el edit inline cayó en Produccion.
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => '99002']);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->Produccion = 6106;
        $programa->save();

        $programa->refresh();
        $this->assertSame(40, (int) $programa->Repeticiones);   // TRUNC((50/614)/2*1000)
        $this->assertSame(80.0, (float) $programa->PzasRollo);
        $this->assertSame(77.0, (float) $programa->TotalRollos); // ceil(6106/80)

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99002')->first();
        $this->assertSame(80.0, (float) $cat->PzasRollo);
        $this->assertSame(77.0, (float) $cat->TotalRollos);
    }

    public function test_total_rollos_se_basa_en_total_pedido_no_en_saldo(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'STD',
            'NoProduccion' => '99003',
            'PesoCrudo' => 614,
            'NoTiras' => 2,
            'LargoCrudo' => 102,
            'SaldoPedido' => 3000,   // ya se produjeron 5000; saldo pendiente 3000
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => '99003']);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->TotalPedido = 8000;   // se captura/cambia el pedido completo
        $programa->save();

        $programa->refresh();
        // ceil(8000/80)=100 (pedido), NO ceil(3000/80)=38 (saldo).
        $this->assertSame(100.0, (float) $programa->TotalRollos);
        $this->assertSame(8000.0, (float) $programa->TotalPzas);
    }

    public function test_no_marbete_es_total_rollos_menos_produccion_marbetes(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'STD',
            'NoProduccion' => '99004',
            'PesoCrudo' => 614,
            'NoTiras' => 2,
            'LargoCrudo' => 102,
            'ProduccionMarbetes' => 60,   // ya producidos 60 marbetes
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => '99004']);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->TotalPedido = 8000;   // se captura/cambia el pedido completo
        $programa->save();

        $programa->refresh();
        // TotalRollos = ceil(8000/80) = 100 ; NoMarbete = 100 - 60 = 40.
        $this->assertSame(100.0, (float) $programa->TotalRollos);
        $this->assertSame(40.0, (float) $programa->NoMarbete);

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99004')->first();
        $this->assertSame(100.0, (float) $cat->TotalRollos);
        $this->assertSame(40.0, (float) $cat->NoMarbete);
    }
}
