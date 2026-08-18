<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pantalla de consulta de auditoría (AuditoriaProgramaTejidoController).
 *
 * Siembra sus propias filas en SYSAuditoria con un marcador reconocible y las borra al
 * terminar. No dispara el trigger: aquí solo se prueban el guard de acceso y los filtros.
 */
class AuditoriaProgramaTejidoPantallaTest extends TestCase
{
    /** Va dentro de PK y Detalle para poder borrar solo lo sembrado por esta prueba. */
    private const MARCA = 'ZZ-PANTALLA';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlsrv') {
            $this->markTestSkipped('La auditoría vive en SQL Server.');
        }

        $this->limpiarSembrado();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->limpiarSembrado();
        }

        parent::tearDown();
    }

    // =====================================================================
    // Acceso
    // =====================================================================

    public function test_un_invitado_no_llega_a_la_pantalla(): void
    {
        $this->get(route('programa-tejido.auditoria'))->assertRedirect();
    }

    public function test_solo_el_area_de_sistemas_puede_entrar(): void
    {
        $this->actingAs($this->usuarioAjeno())
            ->get(route('programa-tejido.auditoria'))
            ->assertForbidden();

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria'))
            ->assertOk();
    }

    public function test_el_guard_vive_en_el_controlador_no_solo_en_el_boton(): void
    {
        // Ocultar el botón en el Blade no basta: la URL es adivinable.
        $respuesta = $this->actingAs($this->usuarioAjeno())
            ->get(route('programa-tejido.auditoria').'?orden='.self::MARCA);

        $respuesta->assertForbidden();
        $respuesta->assertDontSee(self::MARCA);
    }

    /**
     * CONTEXT_INFO vive en la conexión física y el driver ODBC las reutiliza entre
     * peticiones. Si una petición anónima no lo reescribe, hereda al último usuario que
     * pasó por esa conexión y el trigger le atribuye la escritura a esa persona.
     *
     * Se prueba contra /login porque es la ruta pública del grupo web: en una ruta con
     * `auth` el redirect ocurre antes y SetSqlContextInfo ni siquiera llega a correr.
     */
    public function test_una_peticion_anonima_borra_el_contexto_del_usuario_anterior(): void
    {
        DB::statement('EXEC dbo.sp_SetAppContext ?, ?, ?, ?', [42, 'Usuario Anterior', '1.2.3.4', 'LIBERAR']);

        $this->get('/login')->assertOk();

        $contexto = (string) DB::selectOne(
            "SELECT REPLACE(CONVERT(VARCHAR(128), CONTEXT_INFO()), CHAR(0), '') AS c"
        )->c;

        $this->assertStringNotContainsString('Usuario Anterior', $contexto);
        $this->assertStringNotContainsString('uid=42', $contexto);
        $this->assertStringNotContainsString('LIBERAR', $contexto);
    }

    // =====================================================================
    // Filtros
    // =====================================================================

    public function test_sin_filtros_muestra_los_movimientos(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria'))
            ->assertOk()
            ->assertSee(self::MARCA)
            ->assertSee('LIBERAR');
    }

    public function test_filtra_por_numero_de_orden(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-A');
        $this->sembrar('ELIMINAR', 'DELETE', 'Franco Sanchez', self::MARCA.'-B');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?orden='.self::MARCA.'-A')
            ->assertOk()
            ->assertSee(self::MARCA.'-A')
            ->assertDontSee(self::MARCA.'-B');
    }

    public function test_filtra_por_accion(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-A');
        $this->sembrar('ELIMINAR', 'DELETE', 'Franco Sanchez', self::MARCA.'-B');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?accion=DELETE')
            ->assertOk()
            ->assertSee(self::MARCA.'-B')
            ->assertDontSee(self::MARCA.'-A');
    }

    public function test_filtra_por_usuario(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Zoraida Prueba', self::MARCA.'-A');
        $this->sembrar('LIBERAR', 'UPDATE', 'Otro Usuario', self::MARCA.'-B');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?usuario=Zoraida')
            ->assertOk()
            ->assertSee(self::MARCA.'-A')
            ->assertDontSee(self::MARCA.'-B');
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_los_extremos(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-VIEJO', '2020-01-15 09:00:00');
        // 23:30 comprueba que el filtro "hasta" cubre el día completo y no corta a medianoche.
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-BORDE', '2020-02-20 23:30:00');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?desde=2020-02-20&hasta=2020-02-20')
            ->assertOk()
            ->assertSee(self::MARCA.'-BORDE')
            ->assertDontSee(self::MARCA.'-VIEJO');
    }

    /**
     * Lo que teclea el usuario iba crudo dentro de un LIKE: buscar la orden "A_123"
     * también traía "AB123", y "%" traía la tabla entera.
     */
    public function test_los_comodines_de_like_se_tratan_como_texto_literal(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-A_123');
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-AB123');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?orden='.urlencode(self::MARCA.'-A_123'))
            ->assertOk()
            ->assertSee(self::MARCA.'-A_123')
            ->assertDontSee(self::MARCA.'-AB123');
    }

    public function test_un_porcentaje_no_devuelve_la_tabla_entera(): void
    {
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-REAL');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?orden='.urlencode('%'))
            ->assertOk()
            ->assertDontSee(self::MARCA.'-REAL')
            ->assertSee('Sin movimientos con esos filtros.');
    }

    public function test_una_busqueda_sin_resultados_no_truena(): void
    {
        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria').'?orden=NO-EXISTE-JAMAS')
            ->assertOk()
            ->assertSee('Sin movimientos con esos filtros.');
    }

    // =====================================================================
    // Aislamiento
    // =====================================================================

    public function test_no_muestra_auditoria_de_otras_tablas(): void
    {
        // Residuo histórico: SYSAuditoria llegó a tener TejTrama y TejTramaConsumos.
        $this->sembrar('LIBERAR', 'UPDATE', 'Franco Sanchez', self::MARCA.'-PROPIO');
        $this->sembrar('ALGO', 'UPDATE', 'Franco Sanchez', self::MARCA.'-AJENO', null, 'TejTrama');

        $this->actingAs($this->usuarioSistemas())
            ->get(route('programa-tejido.auditoria'))
            ->assertOk()
            ->assertSee(self::MARCA.'-PROPIO')
            ->assertDontSee(self::MARCA.'-AJENO');
    }

    // =====================================================================
    // Utilidades
    // =====================================================================

    private function sembrar(
        string $contexto,
        string $accion,
        string $usuario,
        ?string $orden = null,
        ?string $fecha = null,
        string $tabla = 'ReqProgramaTejido'
    ): void {
        $orden ??= self::MARCA;

        DB::table('dbo.SYSAuditoria')->insert([
            'Tabla' => $tabla,
            'Accion' => $accion,
            'PK' => 'Id=99999 | Orden='.$orden,
            'UsuarioId' => 1,
            'Usuario' => $usuario,
            'HostName' => 'test',
            'AppName' => 'PHPUnit',
            'IP' => '127.0.0.1',
            'Fecha' => $fecha ?? now(),
            'Detalle' => $contexto.' | TotalPedido: 1.00 -> 2.00',
        ]);
    }

    private function limpiarSembrado(): void
    {
        DB::table('dbo.SYSAuditoria')->where('PK', 'like', '%'.self::MARCA.'%')->delete();
    }

    private function usuarioSistemas(): Usuario
    {
        $usuario = Usuario::query()->where('area', 'Sistemas')->first();

        if (! $usuario) {
            $this->markTestSkipped('Se requiere un usuario del área Sistemas.');
        }

        return $usuario;
    }

    private function usuarioAjeno(): Usuario
    {
        $usuario = Usuario::query()->where('area', '<>', 'Sistemas')->first();

        if (! $usuario) {
            $this->markTestSkipped('Se requiere un usuario fuera del área Sistemas.');
        }

        return $usuario;
    }
}
