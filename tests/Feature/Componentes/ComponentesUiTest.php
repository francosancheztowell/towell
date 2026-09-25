<?php

declare(strict_types=1);

namespace Tests\Feature\Componentes;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Fase 16: contrato HTML de los componentes. Los usos existentes (PT, /admin, login,
 * configuración) dependen de ids, clases y props: si algo de esto cambia, se rompen.
 */
class ComponentesUiTest extends TestCase
{
    public function test_modal_base_es_dialog_con_la_api_que_usa_programa_tejido(): void
    {
        $html = (string) $this->blade(
            '<x-ui.modal-base id="modalRepaso" title="Crear Repaso" size="md" onclose="cerrarModalRepaso()"><p>cuerpo</p></x-ui.modal-base>'
        );

        // El JS de PT hace getElementById('modalRepaso').classList.remove/add('hidden').
        $this->assertMatchesRegularExpression('/<dialog id="modalRepaso"[^>]*class="ui-modal hidden"/', $html);
        $this->assertStringContainsString('aria-labelledby="modalRepaso-titulo"', $html);
        $this->assertStringContainsString('<h3 id="modalRepaso-titulo"', $html);
        $this->assertStringContainsString('onclick="cerrarModalRepaso()"', $html);
        $this->assertStringContainsString('data-ui-modal-close', $html);
        $this->assertStringContainsString('max-w-lg', $html);
        $this->assertStringContainsString('<p>cuerpo</p>', $html);
        $this->assertStringNotContainsString('data-ui-modal-backdrop-close', $html);
    }

    public function test_modal_base_sin_onclose_se_cierra_poniendo_hidden(): void
    {
        $this->blade('<x-ui.modal-base id="m1" title="T" size="xl" :close-on-backdrop="true">x</x-ui.modal-base>')
            ->assertSee('document.getElementById(&#039;m1&#039;).classList.add(&#039;hidden&#039;)', false)
            ->assertSee('max-w-4xl', false)
            ->assertSee('data-ui-modal-backdrop-close', false);
    }

    public function test_modal_base_pinta_footer_y_tono_danger(): void
    {
        $this->blade('<x-ui.modal-base id="m2" title="Borrar" tone="danger">x<x-slot:footer><button>Ok</button></x-slot:footer></x-ui.modal-base>')
            ->assertSee('bg-red-50', false)
            ->assertSee('text-red-700', false)
            ->assertSee('<button>Ok</button>', false);
    }

    public function test_button_conserva_las_variantes_con_degradado(): void
    {
        $this->blade('<x-ui.button type="submit" :full-width="true">Guardar</x-ui.button>')
            ->assertSee('type="submit"', false)
            ->assertSee('from-blue-500 via-blue-500 to-blue-600', false)
            ->assertSee('rounded-xl', false)
            ->assertSee('w-full', false)
            ->assertSee('<span>Guardar</span>', false);

        $this->blade('<x-ui.button variant="secondary" size="sm">Cancelar</x-ui.button>')
            ->assertSee('bg-gray-500 hover:bg-gray-600', false)
            ->assertSee('px-3 py-1.5 text-sm', false);
    }

    public function test_button_plano_con_paleta_del_navbar_icono_y_enlace(): void
    {
        $this->blade('<x-ui.button variant="create" icon="fa-plus">Crear</x-ui.button>')
            ->assertSee('bg-primary hover:bg-primary-hover', false)
            ->assertSee('min-h-touch', false)
            ->assertSee('fa-solid fa-plus', false);

        $html = (string) $this->blade('<x-ui.button variant="delete" size="icon" icon="fa-trash" label="Eliminar" href="/x" />');
        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('href="/x"', $html);
        $this->assertStringContainsString('aria-label="Eliminar"', $html);
        $this->assertStringContainsString('size-touch', $html);
        $this->assertStringNotContainsString('type="button"', $html);
    }

    public function test_button_loading_deshabilita_y_marca_ocupado(): void
    {
        $this->blade('<x-ui.button :loading="true">Procesando</x-ui.button>')
            ->assertSee('disabled', false)
            ->assertSee('aria-busy="true"', false)
            ->assertSee('animate-spin -ml-1 mr-2', false);
    }

    public function test_alert_se_cierra_sin_onclick(): void
    {
        $html = (string) $this->blade('<x-ui.alert type="error" title="Errores" :items="[\'Uno\', \'Dos\']" />');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('has-[.ui-dismiss:checked]:hidden', $html);
        $this->assertStringContainsString('class="ui-dismiss sr-only"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('<li class="text-sm">Dos</li>', $html);

        $this->blade('<x-ui.alert type="success" message="Listo" :dismissible="false" />')
            ->assertSee('role="status"', false)
            ->assertDontSee('class="ui-dismiss', false);
    }

    public function test_badge_spinner_y_skeleton(): void
    {
        $this->blade('<x-ui.badge tone="success" icon="fa-check">Liberada</x-ui.badge>')
            ->assertSee('bg-success-soft', false)
            ->assertSee('fa-solid fa-check', false)
            ->assertSee('text-caption', false)
            ->assertSee('Liberada');

        $this->blade('<x-ui.spinner size="sm" label="Guardando" />')
            ->assertSee('role="status"', false)
            ->assertSee('animate-spin', false)
            ->assertSee('sr-only', false)
            ->assertSee('Guardando');

        $html = (string) $this->blade('<x-ui.skeleton :lines="3" />');
        $this->assertSame(3, substr_count($html, 'motion-safe:animate-pulse'));
    }

    public function test_field_liga_label_ayuda_y_error_de_validacion(): void
    {
        $errores = (new ViewErrorBag)->put('default', new MessageBag(['Porcentaje' => ['Debe ser 0 a 100.']]));
        view()->share('errors', $errores);

        $html = (string) $this->blade('<x-ui.field as="number" name="Porcentaje" label="Porcentaje" required hint="0 a 100" min="0" />');

        $this->assertStringContainsString('<label for="Porcentaje"', $html);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="Porcentaje-ayuda Porcentaje-error"', $html);
        $this->assertStringContainsString('Debe ser 0 a 100.', $html);
        $this->assertStringContainsString('min-h-touch', $html);

        view()->share('errors', new ViewErrorBag);
        $this->blade('<x-ui.field as="textarea" name="Nota" label="Nota" value="hola" />')
            ->assertSee('<textarea id="Nota" name="Nota"', false)
            ->assertSee('hola</textarea>', false)
            ->assertDontSee('aria-invalid', false);
    }

    public function test_table_envuelve_el_cuerpo_o_respeta_un_tbody_propio(): void
    {
        $html = (string) $this->blade('<x-ui.table variant="subtle" id="t"><x-slot:head><tr><th>A</th></tr></x-slot:head><tr><td>1</td></tr></x-ui.table>');
        $this->assertStringContainsString('ui-table--subtle', $html);
        $this->assertStringContainsString('id="t"', $html);
        $this->assertStringContainsString('<thead class="sticky top-0 z-10">', $html);
        $this->assertMatchesRegularExpression('/<tbody><tr><td>1<\/td><\/tr><\/tbody>/', $html);

        $html = (string) $this->blade('<x-ui.table :sticky="false"><tbody x-data="{}"><tr><td>1</td></tr></tbody></x-ui.table>');
        $this->assertSame(1, substr_count($html, '<tbody'));
        $this->assertStringContainsString('<tbody x-data="{}">', $html);
        $this->assertStringContainsString('ui-table--primary', $html);

        $html = (string) $this->blade('<x-ui.table :loading="true" :columns="2" :loading-rows="3"><tr><td>no</td></tr></x-ui.table>');
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringNotContainsString('>no<', $html);
        $this->assertSame(6, substr_count($html, '<td>'));
    }

    public function test_table_empty_y_empty_state(): void
    {
        $this->blade('<x-ui.table-empty :colspan="3" message="Sin máquinas" icon="fa-gears" />')
            ->assertSee('colspan="3"', false)
            ->assertSee('fa-solid fa-gears', false)
            ->assertSee('Sin máquinas');

        // Uso real de modulos/configuracion.blade.php: mismo SVG y textos.
        $this->blade('<x-empty.empty-state icon="config" title="No hay módulos" message="Sin permisos" />')
            ->assertSee('bg-gray-100 rounded-2xl p-8 max-w-md mx-auto', false)
            ->assertSee('M10.325 4.317', false)
            ->assertSee('No hay módulos')
            ->assertSee('Sin permisos');

        $this->blade('<x-empty.empty-state icon="fa-inbox" title="Vacío">Acción</x-empty.empty-state>')
            ->assertSee('fa-solid fa-inbox', false)
            ->assertSee('Acción');
    }

    public function test_filter_bar_modo_cliente_y_modo_livewire(): void
    {
        $this->blade('<x-ui.filter-bar target="#tabla" placeholder="Buscar máquina"><select data-ui-filter-column="tipo"></select></x-ui.filter-bar>')
            ->assertSee('data-ui-filter-target="#tabla"', false)
            ->assertSee('data-ui-filter-text', false)
            ->assertSee('data-ui-filter-clear', false)
            ->assertSee('data-ui-filter-column="tipo"', false)
            ->assertSee('role="search"', false);

        $this->blade('<x-ui.filter-bar model="buscar" />')
            ->assertSee('wire:model.live.debounce.300ms="buscar"', false)
            ->assertDontSee('data-ui-filter-bar', false);
    }

    public function test_navbar_buttons_sin_texto_llevan_aria_label_y_omiten_onclick_vacio(): void
    {
        $this->blade('<x-navbar.button-create text="" title="Nuevo registro" />')
            ->assertSee('aria-label="Nuevo registro"', false)
            ->assertSee('w-9 h-9', false)
            ->assertDontSee('onclick', false);

        // Con texto: mismo HTML de siempre.
        $this->blade('<x-navbar.button-delete onclick="borrar()" id="btnDelete" />')
            ->assertSee('onclick="borrar()"', false)
            ->assertSee('bg-red-500', false)
            ->assertDontSee('aria-label', false);
    }

    public function test_global_loader_sin_keyframes_ni_script_inline(): void
    {
        $html = (string) $this->blade('<x-layout.global-loader />');

        $this->assertStringContainsString('id="globalLoader"', $html);
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('animate-loader', $html);
        $this->assertStringNotContainsString('@keyframes', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_layout_no_redefine_fa_spin(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringNotContainsString('@keyframes fa-spin', $layout);
        $this->assertDoesNotMatchRegularExpression('/\.fa-spin\s*\{/', $layout);
        $this->assertStringContainsString('<x-ui.flash', $layout);
    }
}
