<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\SesionService;
use Illuminate\Support\Str;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class AccesosTest extends TestCase
{
    use PreparaMonitoreo;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        $this->registrarRutaDePrueba();
        $this->uuid = (string) Str::uuid();
    }

    private function login(string $numero = '7701', string $contrasenia = 'secret')
    {
        return $this->withCookie(DispositivoService::COOKIE, $this->uuid)
            ->from('/login')
            ->post('/login', ['numero_empleado' => $numero, 'contrasenia' => $contrasenia]);
    }

    public function test_login_correcto_crea_sesion_y_acceso(): void
    {
        $usuario = $this->crearUsuario();

        $this->login()->assertRedirect('/produccionProceso');

        $sesion = $this->mon('SYSMonSesion')->first();
        $this->assertNotNull($sesion);
        $this->assertSame('login', $sesion->Origen);
        $this->assertSame((int) $usuario->idusuario, (int) $sesion->UsuarioId);
        $this->assertNull($sesion->Fin);
        $this->assertSame((int) $sesion->Id, session(SesionService::LLAVE_SESION));

        $dispositivo = $this->mon('SYSMonDispositivo')->first();
        $this->assertSame($this->uuid, $dispositivo->Uuid);
        $this->assertSame((int) $sesion->Id, (int) $dispositivo->UltimaSesionId);

        $acceso = $this->mon('SYSMonAcceso')->first();
        $this->assertSame('login', $acceso->Tipo);
        $this->assertSame('7701', $acceso->NumeroEmpleado);
        $this->assertSame((int) $dispositivo->Id, (int) $acceso->DispositivoId);
    }

    public function test_un_nuevo_login_en_el_mismo_dispositivo_reemplaza_la_sesion_abierta(): void
    {
        $this->crearUsuario();

        $this->login();
        $this->nuevoProceso();
        $this->login();

        $this->assertSame(['reemplazada', null], $this->mon('SYSMonSesion')->orderBy('Id')->pluck('MotivoFin')->all());
    }

    public function test_login_fallido_registra_y_no_crea_sesion(): void
    {
        $this->crearUsuario();

        $this->login('7701', 'mala')->assertRedirect('/login')->assertSessionHas('error');
        $this->login('9999', 'x');

        $this->assertSame(0, $this->mon('SYSMonSesion')->count());
        $this->assertSame(
            [['login_fallido', '7701', 'contrasena'], ['login_fallido', '9999', 'usuario_inexistente']],
            $this->mon('SYSMonAcceso')->orderBy('Id')->get()->map(fn ($a) => [$a->Tipo, $a->NumeroEmpleado, $a->Motivo])->all()
        );
        $this->assertStringNotContainsString('mala', json_encode($this->mon('SYSMonAcceso')->get()));
    }

    public function test_el_intento_11_en_un_minuto_se_bloquea_aunque_la_contrasena_sea_correcta(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 10; $i++) {
            $this->login('7701', 'mala');
        }

        $this->login('7701', 'secret')
            ->assertRedirect('/login')
            ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Demasiados intentos, espera'));

        $this->assertGuest();
        $this->assertSame('bloqueo', $this->mon('SYSMonAcceso')->orderByDesc('Id')->value('Tipo'));

        $this->travel(61)->seconds();
        $this->login('7701', 'secret')->assertRedirect('/produccionProceso');
    }

    public function test_logout_cierra_la_sesion_y_no_rota_el_remember_token(): void
    {
        $usuario = $this->crearUsuario();
        $this->login();
        $token = $usuario->fresh()->remember_token;
        $this->assertNotEmpty($token);

        $this->withCookie(DispositivoService::COOKIE, $this->uuid)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('logout', $this->mon('SYSMonSesion')->value('MotivoFin'));
        $this->assertSame('logout', $this->mon('SYSMonAcceso')->orderByDesc('Id')->value('Tipo'));
        $this->assertSame($token, $usuario->fresh()->remember_token, 'Las otras tablets del usuario siguen recordadas.');
    }

    public function test_restauracion_por_recordarme_registra_origen_recordarme(): void
    {
        $usuario = $this->crearUsuario(['remember_token' => Str::random(60)]);

        foreach ($this->cookieRecordarme($usuario) as $nombre => $valor) {
            $this->withCookie($nombre, $valor);
        }
        $this->withCookie(DispositivoService::COOKIE, $this->uuid)->get('/_prueba/monitoreo')->assertOk();

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame('recordarme', $this->mon('SYSMonSesion')->value('Origen'));
        $this->assertSame('recordarme', $this->mon('SYSMonAcceso')->value('Tipo'));
    }

    public function test_kill_switch_no_escribe_accesos_pero_el_login_funciona(): void
    {
        config()->set('monitoreo.enabled', false);
        $this->crearUsuario();

        $this->login('7701', 'mala');
        $this->login()->assertRedirect('/produccionProceso');

        $this->assertSame(0, $this->mon('SYSMonAcceso')->count());
        $this->assertSame(0, $this->mon('SYSMonSesion')->count());
    }
}
