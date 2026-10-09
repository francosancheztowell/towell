<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use App\Services\Trazabilidad\TrazabilidadResumenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Pedido, facturado y pendiente vienen juntos de AX. Sin AX, el pedido cae a lo programado de la
 * tarjeta "Programa tejido" y lo facturado queda sin dato (antes era un 0 fijo).
 */
final class TrazabilidadResumenPedidoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        Schema::connection('sqlsrv')->create('TrazaProduccion', function (Blueprint $table): void {
            $table->id('Id');
            foreach (['Flogs', 'Articulo', 'Tamano', 'Orden', 'NombreAlmacen'] as $columna) {
                $table->string($columna)->nullable();
            }
            $table->date('Fecha')->nullable();
            $table->float('Cantidad')->nullable();
            $table->float('Peso')->nullable();
        });
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
    }

    public function test_billing_trio_comes_from_ax(): void
    {
        $this->ax(['pedido' => 214976.0, 'facturado' => 203456.0, 'porEntregar' => 11520.0, 'cancelado' => 7817.0, 'lineasCanceladas' => 41]);

        $resumen = app(TrazabilidadResumenService::class)
            ->build(['flog' => 'F-1399'], ['flogs' => collect(['F-1399'])], [['programado' => 225339.0]]);

        $this->assertSame(214976.0, $resumen['pedido']);
        $this->assertSame(203456.0, $resumen['facturado']);
        $this->assertSame(11520.0, $resumen['pendienteFacturacion']);
        $this->assertSame(7817.0, $resumen['cancelado']);
        $this->assertSame(41, $resumen['lineasCanceladas']);
    }

    public function test_without_ax_pedido_falls_back_to_the_program_table_and_billing_is_unknown(): void
    {
        $this->ax(null);

        $tabla = [
            ['programado' => 1000.0, 'fuente' => 'codificados'],
            ['programado' => 250.5, 'fuente' => 'codificados'],
        ];

        $resumen = app(TrazabilidadResumenService::class)->build(['flog' => 'F-1399'], [], $tabla);

        $this->assertSame(1250.5, $resumen['pedido']);
        $this->assertNull($resumen['facturado']);
        $this->assertNull($resumen['pendienteFacturacion']);
    }

    public function test_without_orders_the_pedido_stays_empty(): void
    {
        $this->ax(null);
        $resumen = app(TrazabilidadResumenService::class)->build(['flog' => 'F-VACIO'], [], []);

        $this->assertNull($resumen['pedido']);
        $this->assertNull($resumen['pendienteFacturacion']);
    }

    /** @param  array{pedido: float, facturado: float, porEntregar: float, cancelado: float, lineasCanceladas: int}|null  $facturacion */
    private function ax(?array $facturacion): void
    {
        $this->mock(TrazabilidadFlogsService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('facturacion')->andReturn($facturacion));
    }
}
