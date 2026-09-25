<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\DispositivoService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

/**
 * SEC-02: sin proxy delante de Laragon no se confía en X-Forwarded-*.
 * La IP que se registra y la que usa el rate limit es REMOTE_ADDR.
 */
class IpRealTest extends TestCase
{
    use PreparaMonitoreo;

    private const REAL = '10.1.2.3';

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        $this->uuid = (string) Str::uuid();
    }

    private function login(string $contrasenia, string $xff)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::REAL])
            ->withHeader('X-Forwarded-For', $xff)
            ->withCookie(DispositivoService::COOKIE, $this->uuid)
            ->from('/login')
            ->post('/login', ['numero_empleado' => '7701', 'contrasenia' => $contrasenia]);
    }

    public function test_request_ip_ignora_x_forwarded_for(): void
    {
        Route::get('/_prueba/ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => self::REAL])
            ->withHeader('X-Forwarded-For', '6.6.6.6')
            ->get('/_prueba/ip')
            ->assertSee(self::REAL);
    }

    public function test_login_registra_la_ip_real_en_acceso_sesion_y_dispositivo(): void
    {
        $this->crearUsuario();

        $this->login('secret', '6.6.6.6')->assertRedirect('/produccionProceso');

        $this->assertSame(self::REAL, $this->mon('SYSMonAcceso')->value('Ip'));
        $this->assertSame(self::REAL, $this->mon('SYSMonSesion')->value('Ip'));
        $this->assertSame(self::REAL, $this->mon('SYSMonDispositivo')->value('UltimaIp'));
    }

    public function test_ip_v6_no_cae_al_x_forwarded_for(): void
    {
        $this->crearUsuario();

        $this->withServerVariables(['REMOTE_ADDR' => '::1'])
            ->withHeader('X-Forwarded-For', '6.6.6.6')
            ->withCookie(DispositivoService::COOKIE, $this->uuid)
            ->from('/login')
            ->post('/login', ['numero_empleado' => '7701', 'contrasenia' => 'secret']);

        $this->assertSame('::1', $this->mon('SYSMonAcceso')->value('Ip'));
    }

    public function test_rotar_x_forwarded_for_no_evita_el_bloqueo_de_login(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 10; $i++) {
            $this->login('mala', '6.6.6.'.$i);
        }

        $this->login('secret', '7.7.7.7')
            ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Demasiados intentos, espera'));
        $this->assertGuest();
    }
}
