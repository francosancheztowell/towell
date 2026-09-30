<?php

namespace Tests\Feature\Tejido;

use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Tejido\TejProduccionReenconado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/**
 * Producción Reenconado Cabezuela (19-02): vista sin JS inline, contrato JSON que consume
 * resources/js/modulos/tejido/reenconado/index.ts y errores sin detalle interno (SEC-07).
 */
class ReenconadoTest extends TestCase
{
    use ModuloTejido;

    private const URL = '/tejido/produccion-reenconado';

    private const MODULO = 'Producción Reenconado Cabezuela';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaTejido(TejProduccionReenconado::class);
        $this->tablaTejido(SSYSFoliosSecuencia::class);

        // SSYSFoliosSecuencia::nextFolio() lee los nombres de columna de INFORMATION_SCHEMA.
        $db = DB::connection('sqlsrv');
        $db->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $db->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $c) {
            $db->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_SCHEMA' => 'dbo', 'TABLE_NAME' => 'SSYSFoliosSecuencias', 'COLUMN_NAME' => $c]);
        }
        $db->table('dbo.SSYSFoliosSecuencias')->insert(['modulo' => 'Reenconado', 'prefijo' => 'RE', 'consecutivo' => 5]);

        // Catálogos de TI_PRO en otro sqlite en memoria.
        config()->set('database.connections.sqlsrv_ti', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlsrv_ti');
        $ti = DB::connection('sqlsrv_ti');
        $ti->statement('CREATE TABLE InventTable (ItemId TEXT, ItemGroupId TEXT, DATAAREAID TEXT)');
        $ti->statement('CREATE TABLE ConfigTable (ItemId TEXT, ConfigId TEXT, DATAAREAID TEXT)');
        $ti->statement('CREATE TABLE InventColor (ItemId TEXT, InventColorId TEXT, Name TEXT, DATAAREAID TEXT)');
        $ti->table('InventTable')->insert([
            ['ItemId' => '20/1', 'ItemGroupId' => 'HILO DIREC', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '20/1', 'ItemGroupId' => 'HILO DIREC', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '30/1', 'ItemGroupId' => 'OTRO', 'DATAAREAID' => 'PRO'],
        ]);
        $ti->table('ConfigTable')->insert(['ItemId' => '20/1', 'ConfigId' => 'ALG', 'DATAAREAID' => 'PRO']);
        $ti->table('InventColor')->insert(['ItemId' => '20/1', 'InventColorId' => 'C1', 'Name' => 'Blanco', 'DATAAREAID' => 'PRO']);
    }

    private function usuario(array $acciones = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'])
    {
        return $this->usuarioCon([27 => $acciones, self::MODULO => $acciones], 'Tejido');
    }

    /** @return array<string, mixed> */
    private function registro(array $cambios = []): array
    {
        return array_merge([
            'Folio' => 'TEMP-1', 'Date' => '2026-09-29', 'Turno' => '1', 'numero_empleado' => '100',
            'nombreEmpl' => 'Usuario prueba', 'Calibre' => '20/1', 'FibraTrama' => 'ALG', 'CodColor' => 'C1',
            'Color' => 'Blanco', 'Cantidad' => '10', 'Cabezuela' => '1.5', 'Conos' => '4', 'Horas' => '2',
            'Eficiencia' => '0.54', 'Obs' => "It's <b>ok</b>",
        ], $cambios);
    }

    private function sembrar(): void
    {
        DB::connection('sqlsrv')->table('dbo.TejProduccionReenconado')->insert([
            'Folio' => 'RE0001', 'Date' => '2026-09-28', 'Turno' => 2, 'numero_empleado' => '100',
            'nombreEmpl' => 'Usuario prueba', 'Calibre' => '20/1', 'Cantidad' => 10, 'Conos' => 3,
            'Horas' => 2, 'Eficiencia' => 0.54, 'Obs' => '<img src=x onerror=alert(1)>',
        ]);
    }

    public function test_vista_sin_js_inline_con_config_en_atributo(): void
    {
        $this->sembrar();
        $html = $this->actingAs($this->usuario())->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('id="pagina-reenconado"', $html);
        $this->assertStringContainsString('data-accion="guardar"', $html);
        $this->assertStringContainsString('data-folio="RE0001"', $html);
        // Rutas resueltas en PHP (con marcador para el folio) y el usuario del filtro por defecto.
        $this->assertStringContainsString('produccion-reenconado\/__F__', $html);
        $this->assertStringContainsString('produccion-reenconado\/generar-folio', $html);
        $this->assertStringContainsString('"usuario":"Usuario prueba"', $html);
        // Nada del script de antes.
        $this->assertStringNotContainsString('axios.', $html);
        $this->assertStringNotContainsString('rowHtml', $html);
        $this->assertStringNotContainsString('data-close="1"', $html);
        // Obs con HTML se pinta escapado.
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_catalogos_de_calibre_fibra_y_color(): void
    {
        $u = $this->usuario();

        $this->actingAs($u)->getJson(self::URL.'/calibres')->assertOk()
            ->assertExactJson(['success' => true, 'data' => [['ItemId' => '20/1']]]);
        $this->actingAs($u)->getJson(self::URL.'/fibras?itemId=20/1')->assertOk()
            ->assertJson(['success' => true, 'data' => [['ConfigId' => 'ALG']]]);
        $this->actingAs($u)->getJson(self::URL.'/colores?itemId=20/1')->assertOk()
            ->assertJson(['success' => true, 'data' => [['InventColorId' => 'C1', 'Name' => 'Blanco']]]);
        $this->actingAs($u)->getJson(self::URL.'/fibras')->assertStatus(400)->assertJson(['message' => 'ItemId requerido']);
    }

    public function test_catalogos_fallan_sin_detalle_interno(): void
    {
        $u = $this->usuario();
        foreach (['InventTable', 'ConfigTable', 'InventColor'] as $t) {
            DB::connection('sqlsrv_ti')->statement('DROP TABLE '.$t);
        }

        foreach (['calibres' => 'No se pudieron cargar los calibres', 'fibras?itemId=1' => 'No se pudieron cargar las fibras', 'colores?itemId=1' => 'No se pudieron cargar los colores'] as $ruta => $mensaje) {
            $r = $this->actingAs($u)->getJson(self::URL.'/'.$ruta)->assertStatus(500);
            $this->assertSame($mensaje, $r->json('message'));
            $this->assertArrayHasKey('trace_id', $r->json());
            $this->assertStringNotContainsString('no such table', $r->getContent());
        }
    }

    public function test_generar_folio_muestra_el_folio_con_el_que_se_guarda(): void
    {
        $u = $this->usuario();

        $folio = $this->actingAs($u)->postJson(self::URL.'/generar-folio')->assertOk()
            ->assertJson(['success' => true, 'usuario' => 'Usuario prueba', 'numero_empleado' => '100'])
            ->json('folio');
        // Antes: 'RE0005' (el último usado) y luego se guardaba como RE0006.
        $this->assertSame('RE0006', $folio);
        // Abrir el modal no consume la secuencia.
        $this->assertSame(5, (int) DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->value('consecutivo'));

        $guardado = $this->actingAs($u)->postJson(self::URL, ['modal' => 1, 'record' => $this->registro()])->assertOk()->json('data.Folio');
        $this->assertSame($folio, $guardado);
    }

    public function test_crear_actualizar_y_eliminar(): void
    {
        $u = $this->usuario();

        $r = $this->actingAs($u)->postJson(self::URL, ['modal' => 1, 'record' => $this->registro()])->assertOk();
        $r->assertJson(['success' => true, 'data' => ['Folio' => 'RE0006', 'Date' => '2026-09-29', 'nombreEmpl' => 'Usuario prueba', 'Obs' => "It's <b>ok</b>"]]);
        // Eficiencia = Cantidad / round(Horas × 9.3, 2) — la misma fórmula que el modal (logica.ts).
        $this->assertEqualsWithDelta(0.54, $r->json('data.Eficiencia'), 0.001);
        $this->assertEqualsWithDelta(18.6, $r->json('data.capacidad'), 0.001);
        $this->assertSame('Creado', $r->json('data.status'));

        $this->actingAs($u)->putJson(self::URL.'/RE0006', ['record' => $this->registro(['Cantidad' => '20', 'Conos' => '8'])])->assertOk()
            ->assertJson(['success' => true, 'data' => ['Folio' => 'RE0006', 'Date' => '2026-09-29', 'Conos' => 8]]);
        $this->assertEquals(20, DB::connection('sqlsrv')->table('dbo.TejProduccionReenconado')->where('Folio', 'RE0006')->value('Cantidad'));

        $this->actingAs($u)->deleteJson(self::URL.'/RE0006')->assertOk()->assertExactJson(['success' => true]);
        $this->assertNull(DB::connection('sqlsrv')->table('dbo.TejProduccionReenconado')->where('Folio', 'RE0006')->first());
    }

    public function test_validacion_devuelve_errores_por_campo(): void
    {
        $u = $this->usuario();

        $this->actingAs($u)->postJson(self::URL, ['modal' => 1, 'record' => $this->registro(['Cantidad' => null])])
            ->assertStatus(422)->assertJson(['success' => false])->assertJsonStructure(['errors' => ['Cantidad']]);
        $this->actingAs($u)->putJson(self::URL.'/RE0006', ['record' => $this->registro(['Turno' => 9])])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['Turno']]);
    }

    public function test_errores_sin_detalle_interno(): void
    {
        $u = $this->usuario();

        // Antes: 500 con "No query results for model [App\Models\Tejido\TejProduccionReenconado]".
        $this->actingAs($u)->putJson(self::URL.'/NOEXISTE', ['record' => $this->registro()])
            ->assertNotFound()->assertJson(['success' => false, 'message' => 'Registro no encontrado']);
        $this->actingAs($u)->deleteJson(self::URL.'/NOEXISTE')
            ->assertNotFound()->assertJson(['success' => false, 'message' => 'Registro no encontrado']);

        // Sin secuencia: antes "Error generando folio: No existe configuración de folio para modulo='Reenconado'".
        DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->delete();
        $r = $this->actingAs($u)->postJson(self::URL, ['modal' => 1, 'record' => $this->registro()])->assertStatus(500);
        $this->assertSame('Error generando folio', $r->json('message'));
        $this->assertArrayHasKey('trace_id', $r->json());
        $this->assertStringNotContainsString('modulo=', $r->getContent());

        // Falla de BD al guardar: sin SQL ni nombre de tabla.
        DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->insert(['modulo' => 'Reenconado', 'prefijo' => 'RE', 'consecutivo' => 5]);
        DB::connection('sqlsrv')->statement('DROP TABLE dbo."TejProduccionReenconado"');
        $r = $this->actingAs($u)->postJson(self::URL, ['modal' => 1, 'record' => $this->registro()])->assertStatus(500);
        $this->assertSame('No se pudo guardar el registro', $r->json('message'));
        $this->assertStringNotContainsString('TejProduccionReenconado', $r->getContent());

        $r = $this->actingAs($u)->deleteJson(self::URL.'/RE0001')->assertStatus(500);
        $this->assertSame('No se pudo eliminar el registro', $r->json('message'));
        $this->assertStringNotContainsString('TejProduccionReenconado', $r->getContent());
    }

    public function test_guardado_por_formulario_redirige_sin_detalle_interno(): void
    {
        $u = $this->usuario();
        DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->delete();

        $this->actingAs($u)->from(self::URL)->post(self::URL, ['modal' => 1, 'record' => $this->registro()])
            ->assertRedirect(self::URL)
            ->assertSessionHasErrors('folio');
        $mensaje = session('errors')->first('folio');
        $this->assertStringStartsWith('Error generando folio (ref: ', $mensaje);
        $this->assertStringNotContainsString('modulo=', $mensaje);
    }

    /**
     * Hueco de 20-03-MAPA-AUTHZ: POST /produccion/reenconado-cabezuela (legacy, sin llamadores)
     * queda en auditar. Se verifica que valida igual que la nueva: misma acción store y mismo
     * middleware de permiso.
     */
    public function test_ruta_legacy_usa_la_misma_accion_y_permiso_que_la_nueva(): void
    {
        $nueva = Route::getRoutes()->getByName('tejido.produccion.reenconado.store');
        $legacy = Route::getRoutes()->getByName('produccion.reenconado_cabezuela.store');
        $this->assertNotNull($nueva);
        $this->assertNotNull($legacy);
        $this->assertSame($nueva->getActionName(), $legacy->getActionName());
        $permiso = fn ($r) => array_values(array_filter($r->gatherMiddleware(), fn ($m) => str_starts_with((string) $m, 'module.permission')));
        $this->assertSame(['module.permission:crear,27,auditar'], $permiso($nueva));
        $this->assertSame($permiso($nueva), $permiso($legacy));

        // Y por la ruta legacy la validación del alta es la misma (422 con los mismos campos).
        $this->actingAs($this->usuario())->postJson('/produccion/reenconado-cabezuela', ['modal' => 1, 'record' => $this->registro(['Cantidad' => null])])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['Cantidad']]);
    }
}
