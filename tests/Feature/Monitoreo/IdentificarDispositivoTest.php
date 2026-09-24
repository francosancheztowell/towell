<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\DispositivoService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class IdentificarDispositivoTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        $this->registrarRutaDePrueba();
    }

    public function test_la_primera_visita_pone_la_cookie_aunque_sea_invitado(): void
    {
        $respuesta = $this->get('/login')->assertOk();

        $cookie = $respuesta->getCookie(DispositivoService::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue(Str::isUuid($cookie->getValue()));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $cookie->getExpiresTime());

        // Invitado: no se crea renglón (evita basura de bots en el login).
        $this->assertSame(0, $this->mon('SYSMonDispositivo')->count());
    }

    public function test_con_cookie_no_la_vuelve_a_poner_ni_duplica_el_dispositivo(): void
    {
        $usuario = $this->crearUsuario();
        $uuid = (string) Str::uuid();

        $this->actingAs($usuario)->withCookie(DispositivoService::COOKIE, $uuid)->get('/_prueba/monitoreo')
            ->assertOk()->assertCookieMissing(DispositivoService::COOKIE);

        $this->travel(31)->seconds();
        $this->withCookie(DispositivoService::COOKIE, $uuid)->get('/_prueba/monitoreo')->assertOk();

        $this->assertSame(1, $this->mon('SYSMonDispositivo')->count());
        $fila = $this->mon('SYSMonDispositivo')->first();
        $this->assertSame($uuid, $fila->Uuid);
        $this->assertSame((int) $usuario->idusuario, (int) $fila->UltimoUsuarioId);
        $this->assertSame('prueba.monitoreo', $fila->UltimaRuta);
    }

    public function test_diez_requests_en_menos_de_30_segundos_hacen_un_solo_update(): void
    {
        $usuario = $this->crearUsuario();
        $uuid = (string) Str::uuid();
        $updates = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $q) use (&$updates) {
            if (str_starts_with(strtolower($q->sql), 'update "sysmondispositivo"')) {
                $updates++;
            }
        });

        $this->actingAs($usuario);
        for ($i = 0; $i < 10; $i++) {
            $this->withCookie(DispositivoService::COOKIE, $uuid)->get('/_prueba/monitoreo')->assertOk();
        }

        // Primer touch: UPDATE (0 filas) + alta + UPDATE. Los 9 siguientes caen en el candado.
        $this->assertSame(2, $updates);
        $this->assertSame(1, $this->mon('SYSMonDispositivo')->count());
    }

    public function test_si_la_bd_de_monitoreo_falla_la_request_sigue_bien(): void
    {
        Schema::connection('sqlsrv')->drop('SYSMonDispositivo');
        Schema::connection('sqlsrv')->drop('SYSMonSesion');

        $this->actingAs($this->crearUsuario())
            ->withCookie(DispositivoService::COOKIE, (string) Str::uuid())
            ->get('/_prueba/monitoreo')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_cookie_invalida_se_reemplaza(): void
    {
        $respuesta = $this->withCookie(DispositivoService::COOKIE, 'no-es-uuid')->get('/login');

        $this->assertTrue(Str::isUuid($respuesta->getCookie(DispositivoService::COOKIE)->getValue()));
    }

    public function test_kill_switch_no_pone_cookie_ni_escribe(): void
    {
        config()->set('monitoreo.enabled', false);

        $this->actingAs($this->crearUsuario())->get('/_prueba/monitoreo')
            ->assertOk()->assertCookieMissing(DispositivoService::COOKIE);

        $this->assertSame(0, $this->mon('SYSMonDispositivo')->count());
    }
}
