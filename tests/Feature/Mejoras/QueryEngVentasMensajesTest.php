<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-ventas-traza-resto: alta y edición de Mensajes (Telegram) comparten
 * validar() y las 14 casillas salen de MensajesController::BANDERAS. Se fija la conducta vieja:
 * casilla que no llega = false (Activo incluido), "1"/"0" del formulario, UsuarioId vacío = null,
 * y la edición apaga las casillas que ya no vienen.
 */
final class QueryEngVentasMensajesTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    private const BANDERAS = [
        'Activo', 'Desarrolladores', 'DesarrolladoresPrue', 'NotificarAtadoJulio', 'CorteSEF', 'MarcasFinales', 'ReporteElectrico',
        'ReporteMecanico', 'ReporteTiempoMuerto', 'Atadores', 'InvTrama', 'UrdidoCalidad', 'Calidad', 'Andon',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $db = DB::connection('sqlsrv');
        $db->statement("ATTACH DATABASE ':memory:' AS dbo");
        $schema = Schema::connection('sqlsrv');

        $schema->create('dbo.SYSMensajes', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('DepartamentoId')->nullable();
            $t->integer('UsuarioId')->nullable();
            $t->string('Telefono')->nullable();
            $t->string('Token')->nullable();
            $t->string('Nombre')->nullable();
            $t->dateTime('FechaRegistro')->nullable();
            foreach (self::BANDERAS as $b) {
                $t->boolean($b)->nullable();
            }
        });
        $schema->create('dbo.SysDepartamentos', function (Blueprint $t) {
            $t->increments('id');
            $t->string('Depto')->nullable();
            $t->string('Descripcion')->nullable();
        });
        $schema->create('dbo.SYSUsuario', function (Blueprint $t) {
            $t->increments('idusuario');
            $t->string('nombre')->nullable();
            $t->string('numero_empleado')->nullable();
            $t->string('correo')->nullable();
            $t->string('area')->nullable();
            $t->timestamps();
        });

        $db->table('dbo.SysDepartamentos')->insert(['id' => 7, 'Depto' => 'Sistemas']);
        $db->table('dbo.SYSUsuario')->insert(['idusuario' => 3, 'nombre' => 'Ana', 'correo' => 'ana@x.mx']);
    }

    private function editor(): Usuario
    {
        $u = new Usuario(['nombre' => 'Admin mensajes']);
        $u->idusuario = 999401;
        $this->sembrarPermisos($u->idusuario, [181 => 'Mensajes']);

        return $u;
    }

    /** @return array<string, mixed> */
    private function base(): array
    {
        return ['DepartamentoId' => 7, 'Telefono' => '5551234', 'Token' => 'tok-1'];
    }

    public function test_alta_sin_casillas_guarda_todas_en_false_y_usuario_null(): void
    {
        $item = $this->actingAs($this->editor())
            ->postJson(route('configuracion.mensajes.store'), $this->base() + ['UsuarioId' => ''])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->json('item');

        $fila = (array) DB::connection('sqlsrv')->table('dbo.SYSMensajes')->first();
        foreach (self::BANDERAS as $b) {
            $this->assertSame(0, (int) $fila[$b], "{$b} debió guardarse en false");
            $this->assertFalse($item[$b], "{$b} en la respuesta JSON");
        }
        $this->assertNull($fila['UsuarioId']);
        $this->assertSame('Sistemas', $item['DepartamentoNombre']);
    }

    public function test_alta_con_casillas_del_formulario_y_usuario(): void
    {
        // El formulario manda hidden "0" + checkbox "1": PHP se queda con el último.
        $item = $this->actingAs($this->editor())
            ->postJson(route('configuracion.mensajes.store'), $this->base() + [
                'Activo' => '1', 'Andon' => '1', 'CorteSEF' => '0', 'UrdidoCalidad' => true,
                'UsuarioId' => '3', 'Nombre' => 'Grupo prod',
            ])
            ->assertOk()
            ->json('item');

        $this->assertTrue($item['Activo']);
        $this->assertTrue($item['Andon']);
        $this->assertTrue($item['UrdidoCalidad']);
        $this->assertFalse($item['CorteSEF']);
        $this->assertFalse($item['Calidad']);
        $this->assertSame(3, $item['UsuarioId']);
        $this->assertSame('Ana', $item['UsuarioNombre']);
        $this->assertSame('Grupo prod', $item['Nombre']);
    }

    public function test_edicion_apaga_las_casillas_que_ya_no_llegan(): void
    {
        $fila = array_fill_keys(self::BANDERAS, 1) + $this->base() + ['UsuarioId' => 3, 'Nombre' => 'Viejo'];
        $id = DB::connection('sqlsrv')->table('dbo.SYSMensajes')->insertGetId($fila);

        $this->actingAs($this->editor())
            ->putJson(route('configuracion.mensajes.update', $id), $this->base() + ['Activo' => '1', 'Calidad' => '1'])
            ->assertOk()
            ->assertJsonPath('item.Activo', true)
            ->assertJsonPath('item.Calidad', true)
            ->assertJsonPath('item.Andon', false)
            ->assertJsonPath('item.UsuarioId', null)
            ->assertJsonPath('item.Nombre', '');

        $guardada = (array) DB::connection('sqlsrv')->table('dbo.SYSMensajes')->where('Id', $id)->first();
        $encendidas = array_keys(array_filter(array_intersect_key($guardada, array_flip(self::BANDERAS)), fn ($v) => (int) $v === 1));
        sort($encendidas);
        $this->assertSame(['Activo', 'Calidad'], $encendidas);
        $this->assertNull($guardada['Nombre']);
    }

    public function test_casilla_con_valor_no_booleano_es_422(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('configuracion.mensajes.store'), $this->base() + ['Andon' => 'tal vez'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['Andon']);

        $this->assertSame(0, DB::connection('sqlsrv')->table('dbo.SYSMensajes')->count());
    }

    public function test_departamento_o_usuario_inexistente_es_422(): void
    {
        $this->actingAs($this->editor());

        $this->postJson(route('configuracion.mensajes.store'), ['DepartamentoId' => 99] + $this->base())
            ->assertStatus(422)->assertJsonValidationErrors(['DepartamentoId']);
        $this->postJson(route('configuracion.mensajes.store'), $this->base() + ['UsuarioId' => 404])
            ->assertStatus(422)->assertJsonValidationErrors(['UsuarioId']);
    }
}
