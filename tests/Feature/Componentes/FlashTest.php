<?php

declare(strict_types=1);

namespace Tests\Feature\Componentes;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** DS-09: x-ui.flash muestra el flash de sesión sin duplicar lo que la vista ya pinta. */
class FlashTest extends TestCase
{
    public function test_muestra_el_error_de_sesion_como_alerta(): void
    {
        session()->flash('error', 'No tienes acceso a este módulo.');

        $this->blade('<x-ui.flash contenido="<main>otra cosa</main>" />')
            ->assertSee('data-ui-flash', false)
            ->assertSee('No tienes acceso a este módulo.')
            ->assertSee('role="alert"', false)
            ->assertDontSee('data-ui-autoclose', false);
    }

    public function test_exito_se_autocierra(): void
    {
        session()->flash('success', 'Usuario registrado correctamente');

        $this->blade('<x-ui.flash />')
            ->assertSee('Usuario registrado correctamente')
            ->assertSee('data-ui-autoclose="6000"', false);
    }

    public function test_no_duplica_lo_que_la_vista_ya_pinta_en_texto_o_json(): void
    {
        session()->flash('success', 'Módulo "Tejido" guardado');
        session()->flash('error', 'Falló <b>algo</b>');

        $contenido = 'Swal.fire({ text: '.json_encode('Módulo "Tejido" guardado').' }) <p>'.e('Falló <b>algo</b>').'</p>';

        $this->blade('<x-ui.flash :contenido="$contenido" />', ['contenido' => $contenido])
            ->assertDontSee('data-ui-flash', false);
    }

    public function test_sin_flash_no_pinta_nada(): void
    {
        $this->assertSame('', trim((string) $this->blade('<x-ui.flash />')));
    }

    public function test_errores_de_validacion_en_lista(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'a' => ['El campo A es obligatorio.'],
            'b' => ['El campo B no es válido.'],
        ])));

        $this->blade('<x-ui.flash />')
            ->assertSee('Revisa los datos:')
            ->assertSee('El campo A es obligatorio.')
            ->assertSee('El campo B no es válido.');
    }
}
