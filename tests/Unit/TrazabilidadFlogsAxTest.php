<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadFlogsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TrazabilidadFlogsService contra un AX simulado: sqlite como conexión sqlsrv_ti,
 * con un esquema adjunto llamado 'dbo' para que 'dbo.TwFlogsTable' resuelva.
 */
class TrazabilidadFlogsAxTest extends TestCase
{
    private const TABLAS = [
        'TwFlogsTable' => ['IDFLOG', 'TIPOPEDIDO', 'NAMEPROYECT', 'EMPRESA', 'TRANSDATE', 'CUSTACCOUNT', 'CUSTNAME', 'NUMPROVEEDOR', 'ESTADOFLOG'],
        'TwFlogsCustomer' => ['IDFLOG', 'CUSTACCOUNT', 'CUSTNAME', 'NUMPROVEEDOR', 'CAGENTE', 'NAGENTE', 'PRUEBALABID', 'PRUEBASLAB', 'PRUEBASLABTXT', 'TIPOCLIENTEID', 'CATEGORIACALIDAD', 'PROCESOCATMEX', 'TWSUAVIZANTE', 'SUAVISANTEEXPTXT', 'AVISOESPECIALTXT', 'INFOIMPORTANTE', 'STREET'],
        'TwFlogsEtiquetasLinea' => ['IDFLOG', 'LINENUM', 'ITEMID', 'NAME', 'COMENTARIOS', 'IMAGENETIQUETA', 'NUEVOITEM'],
        'TwBomEmpaque' => ['IDFLOG', 'RECID', 'IDEMPAQUE', 'OTROEMPAQUE', 'FILEOTROEMPAQUE', 'PZAS'],
        'TwFlogsItemLine' => ['IDFLOG', 'LINENUM', 'ESTADOLINEA', 'FECHACANCELACION', 'ITEMID', 'ITEMNAME', 'TIPOHILOID', 'INVENTSIZEID', 'INVENTCOLORID', 'COLORNAME', 'RASURADOCRUDO', 'TIPODOBLADILLO', 'TIPOCOSTURA', 'TIPOCORTEBATAID', 'VALORAGREGADO', 'PUNTADASBORDADO', 'INFOADICIONAL', 'ANCHO', 'LARGO', 'PESOACABADO', 'DENSIDAD', 'INVENTQTY', 'FACTURADO', 'PORENTREGAR', 'SALESUNIT', 'PURCHBARCODE', 'DUN14', 'RETAILLINK', 'NOMBREETIQUETA', 'CREATEDDATE', 'SIMULACIONVTAS', 'SIMULACIONDISENO', 'SALESPRICE'],
    ];

    /** @var list<string> */
    private array $consultas = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv_ti', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlsrv_ti');
        $conn = DB::connection('sqlsrv_ti');
        $conn->statement("ATTACH DATABASE ':memory:' AS dbo");

        foreach (self::TABLAS as $tabla => $columnas) {
            $conn->statement('CREATE TABLE dbo."'.$tabla.'" ('.implode(', ', array_map(fn ($c) => '"'.$c.'"', $columnas)).')');
        }

        Cache::flush();
        $conn->listen(fn ($q) => $this->consultas[] = $q->sql);
    }

    private function sembrar(bool $conCliente = true): void
    {
        $conn = DB::connection('sqlsrv_ti');
        $conn->table('dbo.TwFlogsTable')->insert(['IDFLOG' => 'FL-1', 'TIPOPEDIDO' => 2, 'NAMEPROYECT' => 'Proyecto', 'EMPRESA' => 0, 'CUSTACCOUNT' => 'T-ACC', 'CUSTNAME' => 'Tabla SA', 'NUMPROVEEDOR' => 'T-PROV']);
        if ($conCliente) {
            $conn->table('dbo.TwFlogsCustomer')->insert(['IDFLOG' => 'FL-1', 'CUSTACCOUNT' => 'C-ACC', 'CUSTNAME' => 'Cliente SA', 'CAGENTE' => 'AG1', 'NAGENTE' => 'Agente Uno', 'PRUEBASLAB' => 1, 'PRUEBASLABTXT' => 'Encogimiento', 'PROCESOCATMEX' => 1, 'TWSUAVIZANTE' => 3]);
        }
        $conn->table('dbo.TwFlogsEtiquetasLinea')->insert(['IDFLOG' => 'FL-1', 'LINENUM' => 1, 'ITEMID' => 'ET-1', 'NAME' => 'Etiqueta']);
        $conn->table('dbo.TwBomEmpaque')->insert(['IDFLOG' => 'FL-1', 'RECID' => 5, 'IDEMPAQUE' => 'EMP-1']);
        $conn->table('dbo.TwFlogsItemLine')->insert([
            ['IDFLOG' => 'FL-1', 'LINENUM' => 2, 'ITEMID' => 'IT-2', 'FACTURADO' => 10, 'ESTADOLINEA' => 1],
            ['IDFLOG' => 'FL-1', 'LINENUM' => 1, 'ITEMID' => 'IT-1', 'FACTURADO' => 5, 'ESTADOLINEA' => 0],
        ]);
        $this->consultas = [];
    }

    public function test_consulta_columnas_explicitas_y_arma_el_detalle(): void
    {
        $this->sembrar();

        $r = app(TrazabilidadFlogsService::class)->build('FL-1');

        $this->assertSame('ok', $r['estado']);
        $this->assertCount(4, $this->consultas, 'Tabla+Customer van en una sola consulta.');
        foreach ($this->consultas as $sql) {
            $this->assertStringNotContainsString('*', $sql);
        }
        $this->assertSame('C-ACC', $r['general']['custAccount']);
        $this->assertSame('C-ACC Cliente SA', $r['general']['cliente']);
        $this->assertSame('T-PROV', $r['general']['numProveedor']);
        $this->assertSame('AG1 — Agente Uno', $r['general']['agente']);
        $this->assertSame('Sí — Encogimiento', $r['general']['pruebasLab']);
        $this->assertSame('Ninguno', $r['general']['twSuavizante']);
        $this->assertSame('Stock', $r['general']['tipoPedido']);
        $this->assertSame(['IT-1', 'IT-2'], array_column($r['lineas'], 'itemId'));
        $this->assertSame('Facturado', $r['lineas'][1]['estadoLinea']);
        $this->assertSame('ET-1', $r['etiquetas'][0]['itemId']);
        $this->assertSame('EMP-1', $r['empaques'][0]['idEmpaque']);
    }

    public function test_sin_cliente_usa_los_datos_de_la_tabla(): void
    {
        $this->sembrar(conCliente: false);

        $g = app(TrazabilidadFlogsService::class)->build('FL-1')['general'];

        $this->assertSame('T-ACC Tabla SA', $g['cliente']);
        $this->assertSame('—', $g['agente']);
        $this->assertSame('—', $g['pruebasLab']);
        $this->assertSame('No', $g['procesoCatMex']);
    }

    public function test_el_resultado_ok_se_cachea_y_la_segunda_llamada_no_consulta_ax(): void
    {
        $this->sembrar();
        $servicio = app(TrazabilidadFlogsService::class);

        $primera = $servicio->build('FL-1');
        $this->consultas = [];
        $segunda = $servicio->build('FL-1');

        $this->assertSame([], $this->consultas);
        $this->assertSame($primera, $segunda);

        $servicio->olvidar('FL-1');
        $servicio->build('FL-1');
        $this->assertNotEmpty($this->consultas);
    }

    public function test_not_found_no_se_cachea(): void
    {
        $servicio = app(TrazabilidadFlogsService::class);

        $this->assertSame('not_found', $servicio->build('FL-1')['estado']);

        $this->sembrar();
        $this->assertSame('ok', $servicio->build('FL-1')['estado']);
    }

    public function test_facturacion_neta_por_producto_y_cancelada_solo_cuenta_lo_facturado(): void
    {
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsItemLine')->insert([
            // Mismo producto: la línea 1 se facturó de más (-400) y cubre a la 2 (+1000).
            ['IDFLOG' => 'FL-1', 'LINENUM' => 1, 'ESTADOLINEA' => 1, 'ITEMID' => 'A', 'INVENTSIZEID' => 'MB', 'INVENTCOLORID' => 'C1', 'INVENTQTY' => 1000, 'FACTURADO' => 1400, 'PORENTREGAR' => -400],
            ['IDFLOG' => 'FL-1', 'LINENUM' => 2, 'ESTADOLINEA' => 0, 'ITEMID' => 'A', 'INVENTSIZEID' => 'MB', 'INVENTCOLORID' => 'C1', 'INVENTQTY' => 1000, 'FACTURADO' => 0, 'PORENTREGAR' => 1000],
            // Otro color: su excedente no tapa lo que falta del primero.
            ['IDFLOG' => 'FL-1', 'LINENUM' => 3, 'ESTADOLINEA' => 1, 'ITEMID' => 'A', 'INVENTSIZEID' => 'MB', 'INVENTCOLORID' => 'C2', 'INVENTQTY' => 500, 'FACTURADO' => 700, 'PORENTREGAR' => -200],
            // Cancelada: solo cuenta lo facturado y no deja nada por entregar.
            ['IDFLOG' => 'FL-1', 'LINENUM' => 4, 'ESTADOLINEA' => 2, 'ITEMID' => 'A', 'INVENTSIZEID' => 'MB', 'INVENTCOLORID' => 'C3', 'INVENTQTY' => 900, 'FACTURADO' => 100, 'PORENTREGAR' => 800],
            // Otro tamaño: el filtro lo deja fuera.
            ['IDFLOG' => 'FL-1', 'LINENUM' => 5, 'ESTADOLINEA' => 0, 'ITEMID' => 'A', 'INVENTSIZEID' => 'CH', 'INVENTCOLORID' => 'C1', 'INVENTQTY' => 50, 'FACTURADO' => 0, 'PORENTREGAR' => 50],
        ]);

        $servicio = app(TrazabilidadFlogsService::class);

        $this->assertSame(
            // Cancelado: 900 pedidas - 100 facturadas de la línea cancelada.
            ['pedido' => 2600.0, 'facturado' => 2200.0, 'porEntregar' => 600.0, 'cancelado' => 800.0, 'lineasCanceladas' => 1],
            $servicio->facturacion(['FL-1'], '', 'MB'),
        );
        $this->assertNull($servicio->facturacion(['SIN-LINEAS']));
        $this->assertNull($servicio->facturacion([]));
    }
}
