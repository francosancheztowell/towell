<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Sistema\Usuario;
use Tests\TestCase;

/**
 * U7 (HANDOFF 19-01): la ventana de reimpresión de Urdido sin orden_id respondía 400 con
 * `<script>alert("Falta orden_id"); window.close();</script>`. Ahora es validación normal.
 */
class ReimpresionVentanaImprimirTest extends TestCase
{
    private const URL = '/urdido/reimpresion-urdido/ventana-imprimir';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $usuario = new Usuario(['idusuario' => 10, 'numero_empleado' => '100', 'nombre' => 'Prueba', 'puesto' => 'Supervisor Urdido']);
        $usuario->idusuario = 10;
        $usuario->exists = true;
        $this->actingAs($usuario);
    }

    public function test_sin_orden_id_responde_422_sin_script(): void
    {
        // Como en producción: sin la página de depuración (trae su propio JS).
        config()->set('app.debug', false);

        foreach ([self::URL, self::URL.'?orden_id=', self::URL.'?orden_id=abc', self::URL.'?orden_id=0'] as $url) {
            $respuesta = $this->get($url);

            $respuesta->assertStatus(422);
            $respuesta->assertDontSee('<script>alert', false);
            $respuesta->assertDontSee('window.close()', false);
        }
    }

    public function test_sin_orden_id_en_json_responde_422_de_validacion(): void
    {
        $this->getJson(self::URL)
            ->assertStatus(422)
            ->assertJsonValidationErrors('orden_id');
    }

    public function test_con_orden_id_abre_la_ventana_con_el_pdf_de_la_orden(): void
    {
        $this->get(self::URL.'?orden_id=42')
            ->assertOk()
            ->assertViewIs('modulos.urdido.reimpresion-urdido-popup')
            ->assertViewHas('ordenId', 42)
            ->assertViewHas('pdfUrl', route('urdido.modulo.produccion.urdido.pdf', ['orden_id' => 42, 'tipo' => 'urdido', 'reimpresion' => 1]));
    }
}
