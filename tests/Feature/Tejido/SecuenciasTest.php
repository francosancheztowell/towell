<?php

namespace Tests\Feature\Tejido;

use App\Models\Inventario\InvSecuenciaCorteEf;
use App\Models\Inventario\InvSecuenciaMarcas;
use App\Models\Inventario\InvSecuenciaTelares;
use App\Models\Inventario\InvSecuenciaTrama;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/** Las 4 secuencias de Tejido (19-02 p1.1): vista común, SEC-07 y orden en un UPDATE por bloque. */
class SecuenciasTest extends TestCase
{
    use ModuloTejido;

    /** @return array<string, array{string, class-string, string, string, string, array<string, mixed>}> */
    public static function variantes(): array
    {
        return [
            'inv-telas' => ['/tejido/secuencia-inv-telas', InvSecuenciaTelares::class, 'Id', 'Secuencia', 'Secuencia Inv Telas', ['NoTelar' => 205, 'TipoTelar' => 'ITEMA', 'Secuencia' => 9, 'Observaciones' => null]],
            'inv-trama' => ['/tejido/secuencia-inv-trama', InvSecuenciaTrama::class, 'Id', 'Secuencia', 'Secuencia Inv Trama', ['NoTelar' => 205, 'TipoTelar' => 'ITEMA', 'Secuencia' => 9]],
            'corte-eficiencia' => ['/tejido/configurar/secuenciacortedeeficiencia', InvSecuenciaCorteEf::class, 'NoTelarId', 'Orden', 'Secuencia Corte de Eficiencia', ['NoTelarId' => 205, 'SalonTejidoId' => 'ITEMA', 'Orden' => null]],
            'marcas-finales' => ['/tejido/configurar/secuenciamarcasfinales', InvSecuenciaMarcas::class, 'NoTelarId', 'Orden', 'Secuencia Marcas Finales', ['NoTelarId' => 205, 'SalonTejidoId' => 'ITEMA', 'Orden' => null]],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaTejido(InvSecuenciaTelares::class, ['Created_At', 'Updated_At']);
        $this->tablaTejido(InvSecuenciaTrama::class);
        $this->tablaTejido(InvSecuenciaCorteEf::class);
        $this->tablaTejido(InvSecuenciaMarcas::class);
    }

    /** @param class-string $modelo */
    private function sembrar(string $modelo, string $llave, string $campo, int $n): void
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) {
            $filas[] = $llave === 'Id'
                ? ['Id' => $i, 'NoTelar' => 200 + $i, 'TipoTelar' => 'JACQUARD', $campo => $i]
                : ['NoTelarId' => 200 + $i, 'SalonTejidoId' => "Jacq'uard <b>", $campo => $i];
        }
        DB::connection('sqlsrv')->table((new $modelo)->getTable())->insert($filas);
    }

    /** idrol de cada variante en routes/modules/tejido.php (module.permission) + el nombre que usa userCan(). */
    private const IDROL = ['Secuencia Inv Telas' => 29, 'Secuencia Corte de Eficiencia' => 30, 'Secuencia Inv Trama' => 31, 'Secuencia Marcas Finales' => 32];

    private function permisos(string $modulo): array
    {
        $todas = ['acceso', 'crear', 'modificar', 'eliminar'];

        return [self::IDROL[$modulo] => $todas, $modulo => $todas];
    }

    #[DataProvider('variantes')]
    public function test_vista_comun_con_config_y_sin_js_inline(string $url, string $modelo, string $llave, string $campo, string $modulo, mixed ...$resto): void
    {
        $this->sembrar($modelo, $llave, $campo, 2);
        $html = $this->actingAs($this->usuarioCon($this->permisos($modulo)))->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('id="pagina-secuencia"', $html);
        $this->assertStringContainsString('data-accion="crear"', $html);
        $this->assertStringContainsString('data-accion="editar"', $html);
        $this->assertStringContainsString($llave === 'Id' ? 'data-id="1"' : 'data-id="201"', $html);
        $this->assertStringNotContainsString('onclick="agregar', $html);
        $this->assertStringNotContainsString('ondragstart', $html);
        // Datos con comilla y < escapados dentro del atributo data-valores='…'.
        if ($llave === 'NoTelarId') {
            $this->assertStringContainsString('Jacq\\u0027uard \\u003Cb\\u003E', $html);
        }
    }

    #[DataProvider('variantes')]
    public function test_sin_permiso_no_hay_acciones(string $url, string $modelo, string $llave, string $campo, string $modulo, mixed ...$resto): void
    {
        $html = $this->actingAs($this->usuarioCon([$modulo => ['acceso']]))->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('data-accion="crear"', $html);
        $this->assertStringNotContainsString('data-accion="eliminar"', $html);
        $this->assertStringContainsString('No tiene permiso para crear', $html);
    }

    #[DataProvider('variantes')]
    public function test_crear_actualizar_y_eliminar(string $url, string $modelo, string $llave, string $campo, string $modulo, array $payload): void
    {
        $this->sembrar($modelo, $llave, $campo, 2);
        $u = $this->usuarioCon($this->permisos($modulo));
        $tabla = (new $modelo)->getTable();

        $this->actingAs($u)->postJson($url, $payload)->assertOk()->assertJson(['success' => true]);
        $nuevo = DB::connection('sqlsrv')->table($tabla)->where($llave === 'Id' ? 'NoTelar' : 'NoTelarId', 205)->first();
        $this->assertNotNull($nuevo);
        $id = $nuevo->{$llave};

        $cambio = array_merge($payload, [$campo => 7]);
        $this->actingAs($u)->putJson($url.'/'.$id, $cambio)->assertOk()->assertJson(['success' => true]);
        $this->assertEquals(7, DB::connection('sqlsrv')->table($tabla)->where($llave, $id)->value($campo));

        $this->actingAs($u)->deleteJson($url.'/'.$id)->assertOk()->assertJson(['success' => true]);
        $this->assertNull(DB::connection('sqlsrv')->table($tabla)->where($llave, $id)->first());
    }

    #[DataProvider('variantes')]
    public function test_errores_sin_detalle_interno(string $url, string $modelo, string $llave, string $campo, string $modulo, array $payload): void
    {
        $u = $this->usuarioCon($this->permisos($modulo));

        // Antes: 500 con "No query results for model [...]". Ahora 404 limpio.
        $this->actingAs($u)->deleteJson($url.'/999')->assertNotFound()->assertJson(['success' => false, 'message' => 'Registro no encontrado']);
        $this->actingAs($u)->putJson($url.'/999', $payload)->assertNotFound();

        // Una falla de BD no devuelve el SQL ni el nombre de la tabla.
        $tabla = (new $modelo)->getTable();
        DB::connection('sqlsrv')->statement('DROP TABLE '.(str_starts_with($tabla, 'dbo.') ? 'dbo."'.substr($tabla, 4).'"' : '"'.$tabla.'"'));
        $r = $this->actingAs($u)->postJson($url, $payload)->assertStatus(500);
        $this->assertSame('Error al crear el registro', $r->json('message'));
        $this->assertArrayHasKey('trace_id', $r->json());
        $this->assertStringNotContainsString(substr($tabla, 4), $r->getContent());
    }

    #[DataProvider('variantes')]
    public function test_orden_en_una_sola_sentencia(string $url, string $modelo, string $llave, string $campo, string $modulo, mixed ...$resto): void
    {
        $this->sembrar($modelo, $llave, $campo, 30);
        $u = $this->usuarioCon($this->permisos($modulo));
        $orden = [];
        for ($i = 1; $i <= 30; $i++) {
            $orden[] = [$llave => $llave === 'Id' ? $i : 200 + $i, $campo => 31 - $i];
        }

        // Antes: un UPDATE por fila (el foreach que había en updateOrden).
        $antes = $this->contarQueries(function () use ($modelo, $llave, $campo, $orden) {
            foreach ($orden as $item) {
                $modelo::where($llave, $item[$llave])->update([$campo => $item[$campo]]);
            }
        });
        $this->assertSame(30, $antes);

        $despues = $this->contarQueries(fn () => $this->actingAs($u)->postJson($url.'/orden', ['orden' => $orden])->assertOk());
        // 1 UPDATE ... CASE (+ las lecturas de permisos del middleware).
        $updates = $this->contarUpdates(fn () => $this->actingAs($u)->postJson($url.'/orden', ['orden' => $orden])->assertOk());
        $this->assertSame(1, $updates);
        $this->assertLessThan($antes, $despues);

        $tabla = (new $modelo)->getTable();
        $this->assertEquals(30, DB::connection('sqlsrv')->table($tabla)->where($llave, $llave === 'Id' ? 1 : 201)->value($campo));
        $this->assertEquals(1, DB::connection('sqlsrv')->table($tabla)->where($llave, $llave === 'Id' ? 30 : 230)->value($campo));
    }

    public function test_orden_con_llave_repetida_gana_la_ultima(): void
    {
        $this->sembrar(InvSecuenciaCorteEf::class, 'NoTelarId', 'Orden', 2);
        $u = $this->usuarioCon($this->permisos('Secuencia Corte de Eficiencia'));
        $orden = [['NoTelarId' => 201, 'Orden' => 1], ['NoTelarId' => 202, 'Orden' => 2], ['NoTelarId' => 201, 'Orden' => 5]];

        $this->actingAs($u)->postJson('/tejido/configurar/secuenciacortedeeficiencia/orden', ['orden' => $orden])->assertOk();

        // Como el UPDATE por fila de antes: el último valor de la llave repetida.
        $this->assertEquals(5, DB::connection('sqlsrv')->table('dbo.InvSecuenciaCorteEf')->where('NoTelarId', 201)->value('Orden'));
    }

    private function contarUpdates(callable $fn): int
    {
        $db = DB::connection('sqlsrv');
        $db->flushQueryLog();
        $db->enableQueryLog();
        $fn();
        $n = count(array_filter($db->getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'update')));
        $db->disableQueryLog();

        return $n;
    }
}
