<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Trazabilidad\TrazabilidadProgramaLookupService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

final class TrazabilidadProgramaLookupServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

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

        // El modelo no declara $connection, asi que la tabla, los inserts y el listener que
        // cuenta consultas tienen que vivir todos en la conexion por defecto.
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
    }

    public function test_it_queries_each_normalized_order_only_once_per_request(): void
    {
        DB::table('ReqProgramaTejido')->insert([
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
        DB::listen(function ($query) use (&$queries): void {
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
