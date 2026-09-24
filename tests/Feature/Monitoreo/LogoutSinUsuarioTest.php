<?php

namespace Tests\Feature\Monitoreo;

use App\Listeners\Monitoreo\RegistrarLogout;
use Illuminate\Auth\Events\Logout;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class LogoutSinUsuarioTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
    }

    public function test_logout_sin_usuario_no_registra_nada(): void
    {
        // SessionGuard::logout() despacha Logout aunque la sesión ya no tenga usuario.
        app(RegistrarLogout::class)->handle(new Logout('web', null));

        $this->assertSame(0, $this->mon('SYSMonAcceso')->count());
    }
}
