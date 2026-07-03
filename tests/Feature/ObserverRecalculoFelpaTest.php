<?php

namespace Tests\Feature;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * El observer debe detectar felpa igual que LiberarOrdenesController:
 *  - FELPA en TamanoClave/NombreProducto → peso rodillo 90 fijo + ajuste ÷2 en Pzas/Mts.
 *  - "FEL" en InventSizeId → ajuste ÷2, pero peso desde el maestro (InventSizeId → FEL → DEF → 41.5).
 * Caso real: orden 36686 (FELPA-WEEKEND) quedó con PzasRollo al doble porque el
 * observer no reconocía felpa por nombre.
 */
class ObserverRecalculoFelpaTest extends TestCase
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
            $table->integer('Repeticiones')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->time('HoraModificacion')->nullable();
        });
    }

    private function recalcular(array $atributos): ReqProgramaTejido
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId($atributos);
        /** @var ReqProgramaTejido $programa */
        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        (new ReqProgramaTejidoObserver())->recalcularFormulasProduccion($programa);

        return $programa->fresh();
    }

    public function test_felpa_por_nombre_usa_peso_90_y_divide_pzas_y_mts(): void
    {
        // Maestro con otro peso para el tamaño: NO debe usarse porque el producto es FELPA nominal.
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        // Datos de la orden real 36686 (FELPA-WEEKEND A SB).
        $programa = $this->recalcular([
            'NombreProducto' => 'FELPA-WEEKEND A SB',
            'InventSizeId' => 'STD',
            'NoProduccion' => '36686',
            'PesoCrudo' => 614, 'NoTiras' => 2, 'LargoCrudo' => 102, 'SaldoPedido' => 6106,
        ]);

        $this->assertSame(73, (int) $programa->Repeticiones);      // TRUNC((90/614)/2*1000)
        $this->assertSame(73.0, (float) $programa->PzasRollo);     // 146 ÷ 2
        $this->assertEqualsWithDelta(37.23, (float) $programa->MtsRollo, 0.01); // 74.46 ÷ 2
        $this->assertSame(84.0, (float) $programa->TotalRollos);   // ceil(6106/73)
        $this->assertSame(6132.0, (float) $programa->TotalPzas);
        $this->assertSame(84.0, (float) $programa->SaldoMarbete);
    }

    public function test_no_felpa_usa_peso_maestro_sin_ajuste(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 50.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $programa = $this->recalcular([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'STD',
            'PesoCrudo' => 614, 'NoTiras' => 2, 'LargoCrudo' => 102, 'SaldoPedido' => 6106,
        ]);

        $this->assertSame(40, (int) $programa->Repeticiones);      // TRUNC((50/614)/2*1000)
        $this->assertSame(80.0, (float) $programa->PzasRollo);     // sin ÷2
    }

    public function test_tamano_fel_usa_peso_maestro_fel_y_divide(): void
    {
        // Sin fila exacta para FEL80: debe caer al maestro "FEL" (no al 90 fijo de felpa nominal).
        DB::connection('sqlsrv')->table('ReqPesosRollosTejido')->insert([
            'InventSizeId' => 'FEL', 'PesoRollo' => 60.0, 'FechaModificacion' => '2026-01-01',
        ]);

        $programa = $this->recalcular([
            'NombreProducto' => 'MB-ARIA SC',
            'InventSizeId' => 'FEL80',
            'PesoCrudo' => 614, 'NoTiras' => 2, 'LargoCrudo' => 102, 'SaldoPedido' => 6106,
        ]);

        $this->assertSame(48, (int) $programa->Repeticiones);      // TRUNC((60/614)/2*1000)
        $this->assertSame(48.0, (float) $programa->PzasRollo);     // 96 ÷ 2 (ajuste FEL)
    }

    public function test_recalculo_propaga_a_cat_codificados(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '36686', 'PzasRollo' => 146.0, 'NoMarbete' => 68.0,
        ]);

        $this->recalcular([
            'NombreProducto' => 'FELPA-WEEKEND A SB',
            'InventSizeId' => 'STD',
            'NoProduccion' => '36686',
            'PesoCrudo' => 614, 'NoTiras' => 2, 'LargoCrudo' => 102, 'SaldoPedido' => 6106,
        ]);

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '36686')->first();
        $this->assertSame(73.0, (float) $cat->PzasRollo);
        $this->assertSame(84.0, (float) $cat->NoMarbete);
        $this->assertSame(84.0, (float) $cat->TotalRollos);
    }
}
