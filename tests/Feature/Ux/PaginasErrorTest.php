<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use WeakMap;

/**
 * UX-09: 403/404/419/500/503 con CSS cargado, un <h1>, textos con acentos y el mismo
 * "Volver al inicio". HANDOFF 20-02 #5: el código de referencia de la 500 es el de SU excepción.
 */
class PaginasErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.debug', false);
        Route::get('/ux-error/{codigo}', function (string $codigo) {
            abort((int) $codigo);
        });
        Route::get('/ux-error-403-propio', fn () => abort(403, 'No tienes acceso a este módulo.'));
    }

    /** @return array<string, array{int, string}> */
    public static function paginas(): array
    {
        return [
            '403' => [403, 'Acceso denegado'],
            '404' => [404, 'Página no encontrada'],
            '419' => [419, 'Tu sesión expiró'],
            '500' => [500, 'Error del servidor'],
            '503' => [503, 'Sistema en mantenimiento'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_cada_pagina_carga_css_y_tiene_un_h1(int $codigo, string $titulo): void
    {
        $html = (string) $this->get('/ux-error/'.$codigo)->assertStatus($codigo)->getContent();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/build/assets/app-[^"]+\.css"#', $html, 'sin CSS de Vite las clases de Tailwind no aplican');
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
        $this->assertStringContainsString($titulo, $html);
        $this->assertStringContainsString('<title>'.$titulo.' · Towell</title>', $html);
        $this->assertStringContainsString('<html lang="es">', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    public function test_volver_al_inicio_es_el_mismo_destino_en_todas(): void
    {
        foreach ([403, 404, 500] as $codigo) {
            $this->get('/ux-error/'.$codigo)
                ->assertSee('href="'.url('/produccionProceso').'"', false)
                ->assertSee('Volver al inicio');
        }
        $this->get('/ux-error/419')->assertSee('href="'.route('login').'"', false)->assertSee('Iniciar sesión');
    }

    public function test_403_muestra_el_mensaje_propio_y_no_el_default_en_ingles(): void
    {
        $this->get('/ux-error-403-propio')->assertForbidden()->assertSee('No tienes acceso a este módulo.');
        $this->get('/ux-error/403')->assertSee('No tienes permisos para acceder a esta página.');
    }

    public function test_regresar_solo_con_pagina_anterior_del_mismo_sitio(): void
    {
        $this->get('/ux-error/404', ['referer' => url('/tejido/inventario-telas')])
            ->assertSee('Regresar')->assertSee('href="'.url('/tejido/inventario-telas').'"', false);
        $this->get('/ux-error/404', ['referer' => 'https://otro-sitio.test/x'])->assertDontSee('Regresar');
    }

    public function test_500_muestra_el_codigo_del_evento_de_su_excepcion(): void
    {
        // Un catch previo registró OTRO error en la request (EstadoRequest::eventoId = 111);
        // la excepción que llega a la página es la 222.
        Route::get('/ux-falla', function () {
            $mapa = new WeakMap;
            app()->instance('monitoreo.eventos_por_excepcion', $mapa);
            app(\App\Services\Monitoreo\EstadoRequest::class)->eventoId = 111;
            $e = new RuntimeException('falla');
            $mapa[$e] = 222;

            throw $e;
        });

        $this->get('/ux-falla')->assertStatus(500)
            ->assertSee('Código de referencia')
            ->assertSee('#222')
            ->assertDontSee('#111');
    }

    public function test_500_sin_evento_de_su_excepcion_no_muestra_el_de_otra(): void
    {
        Route::get('/ux-falla-sin-evento', function () {
            app(\App\Services\Monitoreo\EstadoRequest::class)->eventoId = 111;

            throw new RuntimeException('falla');
        });

        $this->get('/ux-falla-sin-evento')->assertStatus(500)->assertDontSee('#111')->assertDontSee('Código de referencia');
    }
}
