<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * BASE-06: la pagina de QR llamaba new QRCode(...) con la libreria de CDN comentada
 * y tronaba en el navegador. Ahora el QR lo pinta resources/js/usuarios/qr.ts (npm qrcode).
 */
class UsuarioQrPageTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
    }

    public function test_qr_se_pinta_desde_el_modulo_vite_con_el_numero_de_empleado(): void
    {
        $admin = $this->createUsuario(['numero_empleado' => '1000', 'nombre' => 'Admin']);
        $this->actingAs($admin, 'web');
        $this->grantModulo('Usuarios', ['acceso'], idRol: 59);
        // UsuarioRepository lee App\Models\Sistema\Usuario, que apunta a dbo.SYSUsuario.
        $this->createTablaDbo('SYSUsuario', [
            'idusuario' => 'INTEGER PRIMARY KEY',
            'nombre' => 'TEXT',
            'contrasenia' => 'TEXT',
            'numero_empleado' => 'TEXT',
            'area' => 'TEXT',
            'puesto' => 'TEXT',
            'foto' => 'TEXT',
        ]);
        DB::table('dbo.SYSUsuario')->insert([
            'idusuario' => 77, 'nombre' => 'Ana Perez', 'contrasenia' => 'x', 'numero_empleado' => '4321',
        ]);

        $response = $this->get(route('configuracion.usuarios.qr', 77));

        $response->assertOk();
        $response->assertSee('data-qr-text="4321"', false);
        $response->assertSee('data-qr-filename="QR_4321_Ana_Perez.png"', false);
        $response->assertSee('data-qr-download', false);
        $response->assertSee(Vite::asset('resources/js/usuarios/qr.ts'), false);
        $response->assertDontSee('new QRCode', false);
        $response->assertDontSee('onclick="downloadQR()"', false);
    }

    public function test_la_vista_no_depende_de_una_libreria_global(): void
    {
        $blade = file_get_contents(resource_path('views/modulos/usuarios/qr.blade.php'));

        $this->assertStringNotContainsString('QRCode', $blade);
        $this->assertStringContainsString("@vite('resources/js/usuarios/qr.ts')", $blade);
        $this->assertStringContainsString("from 'qrcode'", file_get_contents(resource_path('js/usuarios/qr.ts')));
    }
}
