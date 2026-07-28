<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadProgramaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TrazabilidadProgramaLookupServiceTest extends TestCase
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

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('StdDia')->nullable();
            $table->float('ProdKgDia')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->default(false);
        });
    }

    public function test_it_queries_each_normalized_order_only_once_per_request(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            [
                'NoProduccion' => 'z125691',
                'NoTelarId' => '301',
                'TotalPedido' => 100,
                'EnProceso' => true,
            ],
            [
                'NoProduccion' => '36564',
                'NoTelarId' => '302',
                'TotalPedido' => 200,
                'EnProceso' => true,
            ],
        ]);

        $queries = 0;
        DB::connection('sqlsrv')->listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'ReqProgramaTejido')) {
                $queries++;
            }
        });

        $lookup = app(TrazabilidadProgramaLookupService::class);
        $first = $lookup->forOrders(collect(['z125691', 'Z125691', '36564']));
        $second = $lookup->forOrders(collect(['Z125691', '36564']));

        $this->assertSame(1, $queries);
        $this->assertSame(['z125691', 'Z125691', 36564], $first->keys()->all());
        $this->assertSame('301', $first['Z125691']->NoTelarId);
        $this->assertSame('301', $second['Z125691']->NoTelarId);
        $this->assertSame('302', $second['36564']->NoTelarId);
    }
}
