<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use App\Models\Ventas\TwHistPedidosModel;
use App\Services\Ventas\PedidosHistoricosPayloadBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pedidos Históricos (Compara): líneas de TwHistoricosPedidos por año de creación, con la jerarquía
 * Empresa > Tipo > Cliente > OC > artículo. Corre el SQL real sobre sqlite con la base `dbo` adjunta.
 */
class VentasPedidosHistoricosTest extends TestCase
{
    private const CONEXION = 'sqlsrv_Reportes_Towell';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config()->set('database.connections.'.self::CONEXION, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge(self::CONEXION);
        DB::connection(self::CONEXION)->statement("ATTACH DATABASE ':memory:' AS dbo");

        Schema::connection(self::CONEXION)->create((new TwHistPedidosModel)->getTable(), function (Blueprint $table): void {
            foreach (['TEXTIL', 'TIPOPEDIDO', 'CUSTNAME', 'PURCHASEORDERID', 'ITEMID', 'NAME', 'ITEMNAME', 'INVENTSIZEID',
                'INVENTCOLORID', 'INVENTCOLORTXT', 'ITEMBARCODE', 'FECHACREADO', 'FECHAFACTURA'] as $columna) {
                $table->string($columna)->nullable();
            }
            foreach (['YearCreado', 'MonthCreado', 'ANIO'] as $columna) {
                $table->integer($columna)->nullable();
            }
            foreach (['PRICE', 'QTY', 'ENTREGADOQTY', 'PENDIENTEQTY', 'AMOUNT'] as $columna) {
                $table->decimal($columna, 18, 4)->default(0);
            }
        });

        // Dos líneas facturadas en fechas distintas del mismo artículo y OC, y una OC de 2025.
        $this->insertar(['FECHAFACTURA' => '2026-10-05 00:00:00.000', 'QTY' => 2000, 'ENTREGADOQTY' => 2000, 'AMOUNT' => 21700]);
        $this->insertar(['FECHAFACTURA' => '2026-10-07 00:00:00.000', 'QTY' => 1000, 'ENTREGADOQTY' => 1000, 'AMOUNT' => 10850]);
        $this->insertar([
            'YearCreado' => 2025, 'MonthCreado' => 11, 'ANIO' => 2026, 'PURCHASEORDERID' => 'HZC-276', 'NAME' => '',
            'ITEMNAME' => 'TOALLA ALBERCA', 'FECHACREADO' => '2025-11-21 00:00:00.000', 'FECHAFACTURA' => null,
            'QTY' => 3200, 'PENDIENTEQTY' => 3200, 'AMOUNT' => 862400,
        ]);
    }

    public function test_el_payload_trae_solo_el_anio_de_creacion_pedido_y_sus_columnas(): void
    {
        $json = $this->payload('2026');

        $this->assertSame(PedidosHistoricosPayloadBuilder::VERSION, $json['v']);
        $this->assertSame(PedidosHistoricosPayloadBuilder::SF, $json['sf']);
        $this->assertSame(['precio', 'cantidad', 'entregado', 'pendiente', 'importe'], $json['nf']);
        $this->assertCount(2, $json['rows']);

        $fila = $this->decodificar($json, $json['rows'][0]);
        $this->assertSame('TOWEL', $fila['empresa']);
        $this->assertSame('CE HT', $fila['tipo']);
        $this->assertSame('GRAN DESIGN FACTORY S DE RL DE CV', $fila['cliente']);
        $this->assertSame('6100157353.', $fila['oc']);
        $this->assertSame('TOALLA FACIAL AM RESORTS VI', $fila['nombre']);
        $this->assertSame(['FAC', '4014', 'BLANCO JOSEFINA', '6577'], [$fila['talla'], $fila['colorId'], $fila['color'], $fila['codigo']]);
        $this->assertSame('01/10/2026', $fila['creado']);
        $this->assertSame('05/10/2026', $fila['facturado']);
        $this->assertEquals([10.85, 2000, 2000, 0, 21700], array_slice($json['rows'][0], count($json['sf'])));
    }

    public function test_sin_name_usa_itemname_y_sin_factura_deja_la_fecha_vacia(): void
    {
        $json = $this->payload('2025');

        $this->assertCount(1, $json['rows']);
        $fila = $this->decodificar($json, $json['rows'][0]);
        $this->assertSame('TOALLA ALBERCA', $fila['nombre']);
        $this->assertSame('', $fila['facturado']);
        $this->assertSame('11', $fila['mes']);
    }

    public function test_endpoint_de_anios_y_cache_por_anio(): void
    {
        $usuario = $this->usuarioConAcceso();

        $this->actingAs($usuario)->getJson(route('ventas.datos.pedidos.anios'))
            ->assertOk()
            ->assertExactJson(['anios' => ['2026', '2025']]);

        $this->payload('2026');
        $this->assertTrue(Cache::has('ventas:pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION.':2026'));
        $this->assertFalse(Cache::has('ventas:pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION.':2025'));
    }

    public function test_rechaza_anios_sin_pedidos(): void
    {
        $usuario = $this->usuarioConAcceso();

        $this->actingAs($usuario)->get(route('ventas.datos.pedidos', ['anio' => '1999']))->assertStatus(422);
        $this->actingAs($usuario)->get(route('ventas.datos.pedidos'))->assertStatus(422);
        $this->assertFalse(Cache::has('ventas:pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION.':1999'));
    }

    public function test_sin_acceso_no_hay_datos(): void
    {
        $usuario = $this->usuarioConAcceso(false);

        $this->actingAs($usuario)->getJson(route('ventas.datos.pedidos.anios'))->assertForbidden();
        $this->actingAs($usuario)->get(route('ventas.datos.pedidos', ['anio' => '2026']))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(string $anio): array
    {
        $respuesta = $this->actingAs($this->usuarioConAcceso())->get(route('ventas.datos.pedidos', ['anio' => $anio]));
        $respuesta->assertOk()->assertHeader('Content-Encoding', 'gzip');

        return json_decode((string) gzdecode((string) $respuesta->getContent()), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<int|float>  $row
     * @return array<string, string>
     */
    private function decodificar(array $json, array $row): array
    {
        $fila = [];
        foreach ($json['sf'] as $posicion => $campo) {
            $fila[$campo] = $json['dict'][$row[$posicion]];
        }

        return $fila;
    }

    /** @param array<string, mixed> $cambios */
    private function insertar(array $cambios): void
    {
        DB::connection(self::CONEXION)->table((new TwHistPedidosModel)->getTable())->insert(array_merge([
            'YearCreado' => 2026, 'MonthCreado' => 10, 'ANIO' => 1999, 'TEXTIL' => 'TOWEL', 'TIPOPEDIDO' => 'CE HT',
            'CUSTNAME' => 'GRAN DESIGN FACTORY S DE RL DE CV', 'PURCHASEORDERID' => '6100157353.', 'ITEMID' => '6577',
            'NAME' => 'TOALLA FACIAL AM RESORTS VI', 'ITEMNAME' => 'AM RESORTS VI', 'INVENTSIZEID' => 'FAC',
            'INVENTCOLORID' => '4014', 'INVENTCOLORTXT' => 'BLANCO JOSEFINA', 'ITEMBARCODE' => '6577', 'PRICE' => 10.85,
            'FECHACREADO' => '2026-10-01 00:00:00.000', 'ENTREGADOQTY' => 0, 'PENDIENTEQTY' => 0,
        ], $cambios));
    }

    private function usuarioConAcceso(bool $acceso = true): Usuario
    {
        $modulo = (string) config('ventas.permission_module');
        $usuario = new Usuario(['nombre' => 'Ventas Pedidos']);
        $usuario->idusuario = $acceso ? 999211 : 999212;

        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => 77, 'modulo' => $modulo],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            77 => (object) ['acceso' => (int) $acceso, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]));

        return $usuario;
    }
}
