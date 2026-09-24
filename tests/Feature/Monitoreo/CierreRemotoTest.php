<?php

namespace Tests\Feature\Monitoreo;

use App\Models\Sistema\Monitoreo\MonDispositivo;
use App\Models\Sistema\Usuario;
use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\DispositivoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class CierreRemotoTest extends TestCase
{
    use PreparaMonitoreo;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        $this->registrarRutaDePrueba();
        $this->usuario = $this->crearUsuario(['remember_token' => Str::random(60)]);
    }

    /** Request de una tablet "recordada" (cookie remember) con su propia cookie de dispositivo. */
    private function desdeTablet(string $uuid, array $headers = [])
    {
        $this->nuevoProceso();
        foreach ($this->cookieRecordarme($this->usuario) as $nombre => $valor) {
            $this->withCookie($nombre, $valor);
        }

        return $this->withCookie(DispositivoService::COOKIE, $uuid)->withHeaders($headers)->get('/_prueba/monitoreo');
    }

    public function test_cierra_solo_el_dispositivo_indicado(): void
    {
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();

        $this->desdeTablet($a)->assertOk();
        $this->desdeTablet($b)->assertOk();

        $admin = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);
        app(CierreRemotoService::class)->solicitar(MonDispositivo::where('Uuid', $a)->firstOrFail(), $admin);

        $respuesta = $this->desdeTablet($a);
        $respuesta->assertRedirect('/login')->assertSessionHas('error', CierreRemotoService::MENSAJE);
        $this->assertGuest();
        // El navegador A pierde su cookie remember: no se vuelve a loguear solo.
        $this->assertTrue($respuesta->getCookie(Auth::guard()->getRecallerName(), false)->isCleared());

        $this->desdeTablet($b)->assertOk();
        $this->assertAuthenticatedAs($this->usuario);

        $sesionA = $this->mon('SYSMonSesion')->where('DispositivoId', MonDispositivo::where('Uuid', $a)->value('Id'))->orderByDesc('Id')->first();
        $this->assertSame('remoto', $sesionA->MotivoFin);
        $this->assertSame(
            ['admin_accion' => 1, 'logout_remoto' => 1],
            $this->mon('SYSMonAcceso')->whereIn('Tipo', ['admin_accion', 'logout_remoto'])->pluck('Tipo')->countBy()->sortKeys()->all()
        );
        $this->assertSame((int) $admin->idusuario, (int) $this->mon('SYSMonAcceso')->where('Tipo', 'admin_accion')->value('ActorId'));
        $this->assertNotNull(MonDispositivo::where('Uuid', $a)->value('CierreSolicitadoEn'));
    }

    public function test_xhr_y_livewire_reciben_401_json(): void
    {
        $uuid = (string) Str::uuid();
        $this->desdeTablet($uuid)->assertOk();

        $admin = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);
        app(CierreRemotoService::class)->solicitar(MonDispositivo::where('Uuid', $uuid)->firstOrFail(), $admin);

        $this->desdeTablet($uuid, ['X-Livewire' => '1'])
            ->assertStatus(401)
            ->assertJson(['code' => 'cierre_remoto']);
    }
}
