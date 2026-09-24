<?php

namespace Tests\Feature\Monitoreo;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class SenalesHtmlTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        Route::middleware(['web', 'auth'])->get('/_prueba/json', fn () => response()->json(['ok' => true]));
    }

    public function test_get_html_trae_server_timing_y_json_no(): void
    {
        $usuario = $this->crearUsuario(['area' => 'Sistemas']);

        $html = $this->actingAs($usuario)->get('/admin')->assertOk();
        $this->assertMatchesRegularExpression('/^app;dur=\d+(\.\d)?, db;dur=\d+(\.\d)?;desc="\d+ q"$/', (string) $html->headers->get('Server-Timing'));

        $this->get('/_prueba/json')->assertOk()->assertHeaderMissing('Server-Timing');
    }

    public function test_el_layout_imprime_las_metas_de_telemetria(): void
    {
        $usuario = $this->crearUsuario(['area' => 'Sistemas']);

        $this->actingAs($usuario)->get('/admin')
            ->assertSee('<meta name="towell-ruta" content="admin.index">', false)
            ->assertSee('<meta name="towell-version" content="', false)
            ->assertSee('<meta name="towell-telemetria" content="1">', false);
    }

    public function test_con_kill_switch_no_hay_meta_de_telemetria_ni_server_timing(): void
    {
        config()->set('monitoreo.enabled', false);
        $usuario = $this->crearUsuario(['area' => 'Sistemas']);

        $this->actingAs($usuario)->get('/admin')
            ->assertOk()
            ->assertDontSee('towell-telemetria', false)
            ->assertHeaderMissing('Server-Timing');
    }
}
