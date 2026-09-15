<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoSmokeTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin esto index() cae en su catch y devuelve 200 con un 'error' en la
        // vista: el test pasaba aunque la consulta reventara.
        $this->createProgramaTejidoTable();
    }

    private function usuario(): Usuario
    {
        $user = new Usuario([
            'idusuario' => 1,
            'numero_empleado' => '00001',
            'nombre' => 'Test User',
            'contrasenia' => 'hashed',
            'area' => 'TEST',
        ]);
        $user->idusuario = 1;

        return $user;
    }

    /**
     * Verifica que la ruta principal de programa-tejido responde correctamente.
     * Este test existe para garantizar que cambios en vistas/JS no rompan el flujo básico.
     */
    public function test_programa_tejido_index_route_returns_view(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 1,
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '301',
            'NombreProducto' => 'TOALLA',
            'Posicion' => 1,
            'FechaInicio' => '2026-01-01 00:00:00',
        ]);

        $response = $this->actingAs($this->usuario())->get(route('catalogos.req-programa-tejido'));

        $response->assertStatus(200);
        // index() se traga cualquier Throwable y devuelve la vista con 'error';
        // sin esto un 200 no dice nada sobre la grilla.
        $response->assertViewMissing('error');
        $response->assertSee('id="mainTable"', false);
        $response->assertSee('data-column="NoTelarId"', false);
    }

    public function test_balancear_route_returns_view(): void
    {
        $response = $this->actingAs($this->usuario())->get(route('programa-tejido.balancear'));

        $response->assertStatus(200);
    }
}
