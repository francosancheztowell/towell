<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
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

        // El observer y el controlador cachean Schema::getColumnListing en propiedades
        // ESTÁTICAS: dentro de una corrida completa, otro test deja cacheadas las columnas de
        // 'CatCodificados' de SU esquema y aquí el sync se filtraba a cero. Aislado pasaba,
        // en suite no. Se limpian antes de crear las tablas de esta prueba.
        foreach ([ReqProgramaTejidoObserver::class, LiberarOrdenesController::class] as $clase) {
            $prop = new \ReflectionProperty($clase, 'columnListingCache');
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }

        $schema = Schema::connection('sqlsrv');

        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NombreProducto')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->float('PesoRollo')->nullable();
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
            $table->string('TelarId')->nullable();
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

    /**
     * Una orden puede estar repartida en dos telares y cada fila de CatCodificados tiene sus
     * propias métricas. El observer hacía UPDATE por OrdenTejido sin filtrar telar, así que
     * guardar un telar le escribía sus datos al otro (13 órdenes así en producción).
     */
    public function test_sincronizacion_no_pisa_la_fila_de_otro_telar(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '99010',
            'NoTelarId' => '201',
            'Produccion' => 100,
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '99010', 'TelarId' => '201', 'Produccion' => 100],
            ['OrdenTejido' => '99010', 'TelarId' => '202', 'Produccion' => 777],
        ]);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->Produccion = 350;
        $programa->save();

        $suyo = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '99010')->where('TelarId', '201')->first();
        $ajeno = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '99010')->where('TelarId', '202')->first();

        $this->assertSame(350.0, (float) $suyo->Produccion);
        $this->assertSame(777.0, (float) $ajeno->Produccion, 'El telar 202 no debe cambiar');
    }

    /** Sin telar en el registro se conserva el update masivo, que es el caso de Balanceo. */
    public function test_sin_telar_sigue_actualizando_todas_las_filas_de_la_orden(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '99011',
            'Produccion' => 100,
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '99011', 'TelarId' => '201', 'Produccion' => 100],
            ['OrdenTejido' => '99011', 'TelarId' => '202', 'Produccion' => 100],
        ]);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->Produccion = 350;
        $programa->save();

        $filas = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99011')->get();
        $this->assertCount(2, $filas);
        foreach ($filas as $fila) {
            $this->assertSame(350.0, (float) $fila->Produccion);
        }
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

    /**
     * SaldoMarbete / NoMarbete los mantiene el proceso externo (TotalRollos − ProduccionMarbetes).
     * El observer (y el cron que pasa por él cada 30 min) NO debe tocarlos: los pisaba con el total.
     */
    public function test_observer_no_toca_saldo_marbete_ni_no_marbete(): void
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
            'SaldoMarbete' => 1,           // pendiente que dejó el proceso externo
            'NoMarbete' => 1,
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '99004',
            'ProduccionMarbetes' => 60,
            'NoMarbete' => 1,
        ]);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->TotalPedido = 8000;
        $programa->save();

        $programa->refresh();
        $this->assertSame(100.0, (float) $programa->TotalRollos); // ceil(8000/80): esto sí se recalcula
        $this->assertSame(1.0, (float) $programa->NoMarbete);
        $this->assertSame(1.0, (float) $programa->SaldoMarbete);

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99004')->first();
        $this->assertSame(100.0, (float) $cat->TotalRollos);
        $this->assertSame(1.0, (float) $cat->NoMarbete);
        $this->assertSame(60.0, (float) $cat->ProduccionMarbetes);
    }

    /**
     * Karl Mayer (telares 401-402) no se rige por las reglas de felpa: ni el peso fijo de
     * 90 kg ni el ×2 en marbetes / ÷2 en piezas por rollo. El MISMO artículo felpa da
     * distinto según el telar, así que la prueba corre los dos y compara.
     */
    public function test_karl_mayer_ignora_las_reglas_de_felpa(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'FEL', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $articulo = [
            'NombreProducto' => 'FELPA ARIA',   // felpa nominal: en Jacquard fuerza 90 kg
            'InventSizeId' => 'FEL',            // tamaño FEL: en Jacquard dispara ×2 / ÷2
            'PesoCrudo' => 614,
            'NoTiras' => 2,
            'LargoCrudo' => 102,
        ];

        $idJac = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId(
            $articulo + ['NoProduccion' => '99020', 'NoTelarId' => '201']
        );
        $idKm = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId(
            $articulo + ['NoProduccion' => '99021', 'NoTelarId' => '401']
        );

        foreach ([$idJac, $idKm] as $id) {
            /** @var ReqProgramaTejido $programa */
            $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
            $programa->TotalPedido = 6106;
            $programa->save();
        }

        $jac = ReqProgramaTejido::on('sqlsrv')->findOrFail($idJac);
        // Peso 90 (felpa) → TRUNC((90/614)/2×1000) = 73 ; PzasRollo 73×2 = 146, ÷2 = 73
        // TotalRollos = ceil(6106/73) = 84
        $this->assertSame(73, (int) $jac->Repeticiones);
        $this->assertSame(73.0, (float) $jac->PzasRollo);
        $this->assertSame(84.0, (float) $jac->TotalRollos);

        $km = ReqProgramaTejido::on('sqlsrv')->findOrFail($idKm);
        // Peso estándar KM 27.5 → TRUNC((27.5/614)/2×1000) = 22 ; PzasRollo 22×2 = 44, sin ÷2
        // TotalRollos = ceil(6106/44) = 139
        $this->assertSame(22, (int) $km->Repeticiones);
        $this->assertSame(44.0, (float) $km->PzasRollo);
        $this->assertSame(139.0, (float) $km->TotalRollos);
    }

    /**
     * 27.5 kg es solo el estándar de Karl Mayer: el peso capturado en la grilla se guarda en
     * PesoRollo y tiene que ganar, igual que en cualquier otro salón.
     */
    public function test_karl_mayer_respeta_el_peso_de_rollo_capturado(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'STD',
            'NoProduccion' => '99022',
            'NoTelarId' => '401',
            'PesoCrudo' => 614,
            'NoTiras' => 2,
            'LargoCrudo' => 102,
            'PesoRollo' => 50.0,   // el usuario capturó otro peso al liberar
        ]);

        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->TotalPedido = 6106;
        $programa->save();

        $programa->refresh();
        // Con 50 kg: TRUNC((50/614)/2×1000) = 40, no los 22 del estándar 27.5.
        $this->assertSame(40, (int) $programa->Repeticiones);
        $this->assertSame(80.0, (float) $programa->PzasRollo);
    }
}
