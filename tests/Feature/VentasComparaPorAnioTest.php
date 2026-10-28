<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use App\Models\Ventas\TwHistPedidosModel;
use App\Models\Ventas\TwHistPronosModel;
use App\Models\Ventas\TwHistVtasModel;
use App\Repositories\Ventas\PedidosHistoricosRepository;
use App\Repositories\Ventas\PvVsOcReportRepository;
use App\Repositories\Ventas\VentasHistoricasRepository;
use App\Services\Ventas\PedidosHistoricosPayloadBuilder;
use App\Services\Ventas\VentasDatosCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Compara se pide por año (el más reciente primero) y la lista de años por separado.
 * Corre el SQL real sobre sqlite: la base `dbo` se adjunta en memoria para que `dbo.TwHistorico…` resuelva.
 */
class VentasComparaPorAnioTest extends TestCase
{
    private const CONEXION = 'sqlsrv_Reportes_Towell';

    private const PEDIDOS = 'pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config()->set('database.connections.'.self::CONEXION, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge(self::CONEXION);
        DB::connection(self::CONEXION)->statement("ATTACH DATABASE ':memory:' AS dbo");

        foreach (array_values(PvVsOcReportRepository::SERIES) as $modelo) {
            $this->crearTabla((new $modelo)->getTable());
        }

        // 2026: una venta real y un plan del mismo combo; 2025: un pedido; 2024 solo existe en pedidos.
        // Los pedidos traen un ANIO distinto a propósito: Compara los ubica por YearCreado/MonthCreado.
        $this->insertar(TwHistPronosModel::class, '2026', 10);
        $this->insertar(TwHistVtasModel::class, '2026', 4);
        $this->insertar(TwHistPedidosModel::class, '2025', 7);
        $this->insertar(TwHistPedidosModel::class, '2024', 1);
    }

    public function test_combinado_con_anio_trae_solo_ese_anio(): void
    {
        $filas = app(PvVsOcReportRepository::class)->combinado('2026')->all();

        $this->assertCount(1, $filas);
        $this->assertSame('2026', trim((string) $filas[0]->ANIO));
        $this->assertEquals(10, $filas[0]->P_QTY);
        $this->assertEquals(4, $filas[0]->R_QTY);
        $this->assertEquals(0, $filas[0]->O_QTY);
    }

    public function test_el_pedido_se_ubica_por_su_fecha_de_creacion(): void
    {
        $filas = app(PvVsOcReportRepository::class)->combinado('2025')->all();

        $this->assertCount(1, $filas);
        $this->assertSame('2025', trim((string) $filas[0]->ANIO));
        $this->assertSame('3', trim((string) $filas[0]->MES));
        $this->assertEquals(7, $filas[0]->O_QTY);
        $this->assertSame([], app(PvVsOcReportRepository::class)->combinado('1999')->all());
    }

    public function test_combinado_sin_anio_trae_todos(): void
    {
        $this->assertCount(3, array_unique(array_map(
            static fn (object $fila): string => (string) $fila->ANIO,
            app(PvVsOcReportRepository::class)->combinado()->all(),
        )));
    }

    public function test_anios_del_mas_reciente_al_mas_antiguo_sin_repetir(): void
    {
        $this->assertSame(['2026', '2025', '2024'], app(PvVsOcReportRepository::class)->anios());
    }

    public function test_endpoint_compara_filtra_por_anio_y_cachea_por_separado(): void
    {
        $usuario = $this->usuarioConAcceso();

        $payload = $this->actingAs($usuario)->get(route('ventas.datos.compara', ['anio' => '2025']));
        $payload->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $json = json_decode((string) gzdecode($payload->getContent()), true);

        $this->assertSame(9, $json['v']);
        $this->assertCount(1, $json['rows']);
        $this->assertContains('2025', $json['dict']);
        $this->assertNotContains('2026', $json['dict']);

        $this->assertTrue(Cache::has('ventas:compara:v9:0:2025'));
        $this->assertFalse(Cache::has('ventas:compara:v9:0:2026'));
    }

    public function test_la_llave_de_cache_lleva_la_version_publicada_de_los_datos(): void
    {
        Cache::forever('ventas:datos:version', '1760890000');

        $this->actingAs($this->usuarioConAcceso())->get(route('ventas.datos.compara', ['anio' => '2025', 'v' => '1760890000']))->assertOk();

        $this->assertTrue(Cache::has('ventas:compara:v9:1760890000:2025'));
        $this->assertFalse(Cache::has('ventas:compara:v9:0:2025'));
    }

    public function test_con_version_publicada_la_cache_no_vence_por_tiempo(): void
    {
        Cache::forever('ventas:datos:version', '1760890000');
        $cache = app(VentasDatosCache::class);
        $this->assertSame(['2026', '2025', '2024'], $cache->comparaAnios());

        $this->insertar(TwHistVtasModel::class, '2023', 1);
        $this->travel(3)->days();

        $this->assertSame(['2026', '2025', '2024'], $cache->comparaAnios());
    }

    public function test_sin_version_publicada_la_cache_sigue_venciendo_por_tiempo(): void
    {
        $cache = app(VentasDatosCache::class);
        $this->assertSame(['2026', '2025', '2024'], $cache->comparaAnios());

        $this->insertar(TwHistVtasModel::class, '2023', 1);
        $this->travel(2)->days();

        $this->assertSame(['2026', '2025', '2024', '2023'], $cache->comparaAnios());
    }

    public function test_olvidar_borra_solo_las_llaves_de_esa_version(): void
    {
        $cache = app(VentasDatosCache::class);
        foreach (['100', '200'] as $version) {
            $cache->comparaAnios($version);
            $cache->compara('2025', $version);
            Cache::forever($cache->clave('pedidos', 'anios', $version), ['2026']);
            Cache::forever($cache->clave('pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION, '2026', $version), 'gz');
            Cache::forever($cache->clave('historico', 'payload', $version), ['rows' => []]);
        }

        $cache->olvidar('100');

        $claves = fn (string $version): array => [
            $cache->clave('compara:v9', 'anios', $version),
            $cache->clave('compara:v9', '2025', $version),
            $cache->clave('pedidos', 'anios', $version),
            $cache->clave('pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION, '2026', $version),
            $cache->clave('historico', 'payload', $version),
        ];
        foreach ($claves('100') as $clave) {
            $this->assertFalse(Cache::has($clave), $clave);
        }
        foreach ($claves('200') as $clave) {
            $this->assertTrue(Cache::has($clave), $clave);
        }
    }

    public function test_tras_una_carga_reconstruye_los_anios_recientes_y_copia_los_demas(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $cache = app(VentasDatosCache::class);
        $cache->calentar('100');

        $this->insertar(TwHistVtasModel::class, '2026', 5);
        $this->insertar(TwHistPedidosModel::class, '2025', 3);
        $this->travel(1)->hours();
        $cache->calentar('200', '100');

        $this->assertNotSame($this->guardado('compara:v9', '2026', '100'), $this->guardado('compara:v9', '2026', '200'), '2026 está en los últimos meses');
        $this->assertNotNull($this->guardado('compara:v9', '2025', '200'));
        $this->assertSame($this->guardado('compara:v9', '2025', '100'), $this->guardado('compara:v9', '2025', '200'), '2025 se copia');
        $this->assertSame($this->guardado(self::PEDIDOS, '2025', '100'), $this->guardado(self::PEDIDOS, '2025', '200'));
    }

    public function test_de_enero_a_marzo_tambien_reconstruye_el_anio_anterior(): void
    {
        $this->travelTo('2026-02-10 10:00:00');
        $cache = app(VentasDatosCache::class);
        $cache->calentar('100');

        $this->insertar(TwHistPedidosModel::class, '2025', 3);
        $this->insertar(TwHistPedidosModel::class, '2024', 3);
        $this->travel(1)->hours();
        $cache->calentar('200', '100');

        $this->assertNotSame($this->guardado(self::PEDIDOS, '2025', '100'), $this->guardado(self::PEDIDOS, '2025', '200'), 'diciembre de 2025 entra en los últimos meses');
        $this->assertSame($this->guardado(self::PEDIDOS, '2024', '100'), $this->guardado(self::PEDIDOS, '2024', '200'), '2024 se copia');
    }

    public function test_una_vez_al_dia_reconstruye_todo_por_si_una_carga_toco_un_mes_viejo(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $cache = app(VentasDatosCache::class);
        $cache->calentar('100');

        $this->insertar(TwHistPedidosModel::class, '2025', 3);
        $this->travel(25)->hours();
        $cache->calentar('200', '100');

        $this->assertNotSame($this->guardado('compara:v9', '2025', '100'), $this->guardado('compara:v9', '2025', '200'));
        $this->assertNotSame($this->guardado(self::PEDIDOS, '2025', '100'), $this->guardado(self::PEDIDOS, '2025', '200'));
    }

    public function test_el_boton_no_hace_el_completo_diario_aunque_ya_toque(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $cache = app(VentasDatosCache::class);
        $cache->calentar('100');

        $this->insertar(TwHistPedidosModel::class, '2025', 3);
        $this->travel(25)->hours();
        $cache->calentar('200', '100', completoSiVencio: false);

        $this->assertSame($this->guardado(self::PEDIDOS, '2025', '100'), $this->guardado(self::PEDIDOS, '2025', '200'));
    }

    public function test_un_anio_que_la_version_anterior_no_tenia_se_construye(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $cache = app(VentasDatosCache::class);
        $cache->calentar('100');

        $this->insertar(TwHistPedidosModel::class, '2023', 2);
        $this->travel(1)->hours();
        $cache->calentar('200', '100');

        $this->assertNotNull($this->guardado('compara:v9', '2023', '200'));
        $this->assertNotNull($this->guardado(self::PEDIDOS, '2023', '200'));
    }

    public function test_endpoint_compara_rechaza_un_anio_invalido(): void
    {
        $this->actingAs($this->usuarioConAcceso())
            ->get(route('ventas.datos.compara', ['anio' => '20x5']))
            ->assertStatus(422);
    }

    public function test_endpoint_compara_solo_acepta_anios_con_datos_y_no_los_cachea(): void
    {
        $usuario = $this->usuarioConAcceso();

        $this->actingAs($usuario)->get(route('ventas.datos.compara', ['anio' => '1999']))->assertStatus(422);
        $this->actingAs($usuario)->get(route('ventas.datos.compara'))->assertStatus(422);

        $this->assertFalse(Cache::has('ventas:compara:v9:0:1999'));
        $this->assertFalse(Cache::has('ventas:compara:v9:0:'));
    }

    public function test_endpoint_de_anios(): void
    {
        $this->actingAs($this->usuarioConAcceso())
            ->getJson(route('ventas.datos.compara.anios'))
            ->assertOk()
            ->assertExactJson(['anios' => ['2026', '2025', '2024']]);
    }

    public function test_sin_acceso_no_hay_datos(): void
    {
        $usuario = $this->usuarioConAcceso(false);

        $this->actingAs($usuario)->getJson(route('ventas.datos.compara.anios'))->assertForbidden();
        $this->actingAs($usuario)->getJson(route('ventas.datos.compara', ['anio' => '2025']))->assertForbidden();
    }

    /** Lo guardado en caché para una sección, año y versión, sin construirlo si falta. */
    private function guardado(string $seccion, string $anio, string $version): mixed
    {
        return Cache::get(app(VentasDatosCache::class)->clave($seccion, $anio, $version));
    }

    private function crearTabla(string $tabla): void
    {
        // Columnas de Compara, Pedidos Históricos y Ventas históricas: calentar() construye las tres.
        $pedidos = [TwHistPedidosModel::COLUMNA_ANIO, TwHistPedidosModel::COLUMNA_MES, TwHistPedidosModel::COLUMNA_SEMANA];
        $dimensiones = array_diff(array_unique([
            ...PvVsOcReportRepository::DIMENSIONES,
            ...PedidosHistoricosRepository::DIMENSIONES,
            ...VentasHistoricasRepository::DIMENSIONES,
        ]), $pedidos);

        Schema::connection(self::CONEXION)->create($tabla, function (Blueprint $table) use ($tabla, $dimensiones, $pedidos): void {
            foreach ($dimensiones as $dimension) {
                $table->string($dimension)->nullable();
            }
            foreach ([...PvVsOcReportRepository::MEDIDAS, 'ENTREGADOQTY', 'PENDIENTEQTY'] as $medida) {
                $table->decimal($medida, 18, 2)->default(0);
            }
            if ($tabla === (new TwHistPedidosModel)->getTable()) {
                foreach ($pedidos as $columna) {
                    $table->string($columna)->nullable();
                }
            }
        });
    }

    /** @param class-string $modelo */
    private function insertar(string $modelo, string $anio, int $cantidad): void
    {
        $fila = array_fill_keys(PvVsOcReportRepository::DIMENSIONES, 'X');
        $fila['ANIO'] = $anio;
        $fila['MES'] = '3';
        $fila['QTY'] = $cantidad;
        if ($modelo === TwHistPedidosModel::class) {
            $fila['ANIO'] = '1999';
            $fila['MES'] = '12';
            $fila[TwHistPedidosModel::COLUMNA_ANIO] = $anio;
            $fila[TwHistPedidosModel::COLUMNA_MES] = '3';
            $fila[TwHistPedidosModel::COLUMNA_SEMANA] = '10';
        }

        DB::connection(self::CONEXION)->table((new $modelo)->getTable())->insert($fila);
    }

    private function usuarioConAcceso(bool $acceso = true): Usuario
    {
        $modulo = (string) config('ventas.permission_module');
        $usuario = new Usuario(['nombre' => 'Ventas Compara']);
        $usuario->idusuario = $acceso ? 999201 : 999202;

        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => 77, 'modulo' => $modulo],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            77 => (object) ['acceso' => (int) $acceso, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]));

        return $usuario;
    }
}
