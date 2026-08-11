<?php

namespace Tests\Feature;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/** Editar TotalPedido debe reflejarse en CatCodificados.Pedido (subir y bajar). */
class ObserverPedidoSyncTest extends TestCase
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
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });

        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->integer('TelarId')->nullable();
            $table->float('Pedido')->nullable();
            $table->float('Saldos')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->time('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });
    }

    public function test_pedido_sube_y_baja_en_cat_codificados(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '99100', 'NoTelarId' => '10', 'TotalPedido' => 1000,
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '99100', 'TelarId' => 10, 'Pedido' => 1000,
        ]);

        $programa = ReqProgramaTejido::on('sqlsrv')->findOrFail($id);
        $programa->TotalPedido = 2500;
        $programa->save();

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99100')->first();
        $this->assertSame(2500.0, (float) $cat->Pedido);

        $programa->TotalPedido = 800;
        $programa->save();

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99100')->first();
        $this->assertSame(800.0, (float) $cat->Pedido);
    }
}
