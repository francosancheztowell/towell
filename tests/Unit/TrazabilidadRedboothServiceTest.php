<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadRedboothService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TrazabilidadRedboothServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.sqlsrv' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        Schema::connection('sqlsrv')->create('TrazaProduccion', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('Flogs', 100)->nullable();
            $table->string('Orden', 60)->nullable();
        });
        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('NoProduccion', 60)->nullable();
            $table->string('FlogsId', 100)->nullable();
            $table->integer('IdRedbooth')->nullable();
            $table->string('NombreRedbooth', 255)->nullable();
        });
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('OrdenTejido', 60)->nullable();
            $table->string('FlogsId', 100)->nullable();
            $table->integer('IdRedbooth')->nullable();
            $table->string('NombreRedbooth', 255)->nullable();
        });
    }

    public function test_it_finds_flog_orders_and_their_existing_redbooth_links(): void
    {
        DB::connection('sqlsrv')->table('TrazaProduccion')->insert([
            ['Flogs' => 'FLOG-100', 'Orden' => '36737'],
            ['Flogs' => 'FLOG-100', 'Orden' => '36738'],
            ['Flogs' => 'FLOG-100', 'Orden' => 'SIN-REGISTRO'],
            ['Flogs' => 'OTRO-FLOG', 'Orden' => '99999'],
        ]);
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            [
                'NoProduccion' => '36737',
                'FlogsId' => 'FLOG-100',
                'IdRedbooth' => 62542504,
                'NombreRedbooth' => '1.ALPURA MB',
            ],
            [
                'NoProduccion' => '36738',
                'FlogsId' => 'FLOG-100',
                'IdRedbooth' => null,
                'NombreRedbooth' => null,
            ],
            [
                'NoProduccion' => 'HISTORICA',
                'FlogsId' => 'FLOG-100',
                'IdRedbooth' => 99999,
                'NombreRedbooth' => 'No pertenece a la trazabilidad actual',
            ],
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            [
                'OrdenTejido' => '36737',
                'FlogsId' => 'FLOG-100',
                'IdRedbooth' => 62542504,
                'NombreRedbooth' => '1.ALPURA MB',
            ],
        ]);

        $resultado = app(TrazabilidadRedboothService::class)->resolver('FLOG-100');

        $this->assertSame('FLOG-100', $resultado['flog']);
        $this->assertSame(['36737', '36738'], array_column($resultado['ordenes'], 'orden'));
        $this->assertSame(62542504, $resultado['ordenes'][0]['vinculos'][0]['idRedbooth']);
        $this->assertSame('1.ALPURA MB', $resultado['ordenes'][0]['vinculos'][0]['nombreRedbooth']);
        $this->assertSame([], $resultado['ordenes'][1]['vinculos']);
        $this->assertSame('programa', $resultado['ordenes'][1]['source']);
    }

    public function test_it_also_finds_an_order_linked_directly_by_flogs_id(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '40001',
            'FlogsId' => 'FLOG-DIRECTO',
            'IdRedbooth' => 70001,
            'NombreRedbooth' => 'Tarea directa',
        ]);

        $resultado = app(TrazabilidadRedboothService::class)->resolver('FLOG-DIRECTO');

        $this->assertSame('40001', $resultado['ordenes'][0]['orden']);
        $this->assertSame('catcodificados', $resultado['ordenes'][0]['source']);
        $this->assertSame(70001, $resultado['ordenes'][0]['vinculos'][0]['idRedbooth']);
    }
}
