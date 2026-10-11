<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Livewire\Admin\EnLinea;
use App\Livewire\Bpm\Checklist;
use App\Livewire\Crudo\Dashboard;
use App\Livewire\Crudo\MachineDetail;
use App\Livewire\Desarrolladores\Captura;
use App\Livewire\InventarioTrama\ConsultarRequerimiento;
use App\Livewire\InventarioTrama\NuevoRequerimiento;
use App\Livewire\Mantenimiento\CatalogoFallas;
use App\Livewire\Mantenimiento\CatalogoOperadores;
use App\Livewire\Tejedores\CatalogoCalibres;
use App\Livewire\Trazabilidad\Index;
use App\Livewire\UrdEng\EdicionOrden;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ramas claude/mejora-flux-livewire-{produccion,operacion}: las vistas cambiaron wire:click por
 * x-on:click="… $wire.metodo()", agregaron wire:target a flux:button (:loading) y quitaron
 * onclick inline. Un nombre mal escrito en un string de Blade no lo detecta phpstan ni el build:
 * el botón simplemente no hace nada o el spinner nunca sale. Aquí se comprueba que cada método
 * o propiedad que la vista nombra exista en su componente.
 */
class FluxLivewireContratoWireTest extends TestCase
{
    /** Llamadas mágicas de Livewire que no son métodos del componente. */
    private const MAGICOS = ['set', 'call', 'refresh', '$refresh', '$set', '$toggle', 'dispatch', 'entangle', 'js', '$parent', 'get', 'watch', 'upload', '$commit'];

    /** @return array<string, array{0: string, 1: class-string}> */
    public static function vistas(): array
    {
        return [
            'captura' => ['livewire/desarrolladores/captura', Captura::class],
            'calibres' => ['livewire/tejedores/catalogo-calibres', CatalogoCalibres::class],
            'edicion-orden' => ['livewire/urd-eng/edicion-orden', EdicionOrden::class],
            'en-linea' => ['livewire/admin/en-linea', EnLinea::class],
            'bpm-checklist' => ['livewire/bpm/checklist', Checklist::class],
            'crudo-dashboard' => ['livewire/crudo/dashboard', Dashboard::class],
            'crudo-machine-detail' => ['livewire/crudo/machine-detail', MachineDetail::class],
            'consultar-requerimiento' => ['livewire/inventario-trama/consultar-requerimiento', ConsultarRequerimiento::class],
            'nuevo-requerimiento' => ['livewire/inventario-trama/nuevo-requerimiento', NuevoRequerimiento::class],
            'catalogo-fallas' => ['livewire/mantenimiento/catalogo-fallas', CatalogoFallas::class],
            'catalogo-operadores' => ['livewire/mantenimiento/catalogo-operadores', CatalogoOperadores::class],
            'trazabilidad' => ['livewire/trazabilidad/index', Index::class],
        ];
    }

    private function fuente(string $vista): string
    {
        return (string) file_get_contents(resource_path('views/'.$vista.'.blade.php'));
    }

    private function existeEnComponente(string $clase, string $nombre): bool
    {
        // wire:target también acepta propiedades (telarId) y rutas de propiedad (form.Codigo).
        $raiz = explode('.', $nombre)[0];

        return method_exists($clase, $raiz) || property_exists($clase, $raiz);
    }

    #[DataProvider('vistas')]
    public function test_los_metodos_que_nombra_la_vista_existen_en_el_componente(string $vista, string $clase): void
    {
        $fuente = $this->fuente($vista);
        $nombres = [];

        preg_match_all('/\$wire\.(\$?\w+)\s*\(/', $fuente, $m);
        $nombres = array_merge($nombres, $m[1]);

        preg_match_all('/wire:(?:click|submit)(?:\.\w+)*="\s*(\$?\w+)/', $fuente, $m);
        $nombres = array_merge($nombres, $m[1]);

        preg_match_all('/wire:target="([^"{]+)"/', $fuente, $m);
        foreach ($m[1] as $lista) {
            // cambiarStatus('Solicitado') apunta al método cambiarStatus con ese argumento.
            $nombres = array_merge($nombres, array_map(fn ($t) => trim(preg_replace('/\(.*$/', '', $t)), explode(',', $lista)));
        }

        // x-dialogo-botones sin accion apunta a guardar.
        if (preg_match('/<x-dialogo-botones(?![^>]*accion=)/', $fuente)) {
            $nombres[] = 'guardar';
        }

        $faltan = [];
        foreach (array_unique(array_filter($nombres)) as $nombre) {
            if (in_array($nombre, self::MAGICOS, true) || str_starts_with($nombre, '$')) {
                continue;
            }
            if (! $this->existeEnComponente($clase, $nombre)) {
                $faltan[] = $nombre;
            }
        }

        $this->assertSame([], $faltan, "{$vista} nombra métodos/propiedades que {$clase} no tiene");
    }

    /** Las vistas tocadas ya no llevan JS inline: onclick y wire:confirm se reemplazaron por Alpine y notify.confirm. */
    #[DataProvider('vistas')]
    public function test_las_vistas_tocadas_no_tienen_onclick_ni_wire_confirm(string $vista, string $clase): void
    {
        $fuente = $this->fuente($vista);

        $this->assertDoesNotMatchRegularExpression('/\sonclick=/', $fuente, "{$vista} volvió a usar onclick inline");
        // wire:confirm solo se quitó donde la rama lo migró (la captura conserva los suyos, fuera de alcance).
        if (in_array($vista, ['livewire/inventario-trama/consultar-requerimiento', 'livewire/mantenimiento/catalogo-fallas', 'livewire/mantenimiento/catalogo-operadores'], true)) {
            $this->assertStringNotContainsString('wire:confirm', $fuente, "{$vista} usa wire:confirm (confirm() nativo) en vez de notify.confirm");
        }
    }

    /** Cada <dialog> que abre con showModal() y avisa al servidor al cerrar debe llevar wire:ignore.self y wire:key. */
    #[DataProvider('vistas')]
    public function test_los_dialog_con_show_modal_conservan_open_tras_un_refresco(string $vista, string $clase): void
    {
        $fuente = $this->fuente($vista);
        preg_match_all('/<dialog\b[^>]*>/s', $fuente, $m);

        foreach ($m[0] as $etiqueta) {
            if (! str_contains($etiqueta, 'showModal()')) {
                continue;
            }
            $this->assertStringContainsString('wire:ignore.self', $etiqueta, "{$vista}: sin wire:ignore.self el morph le quita `open` al diálogo");
            $this->assertMatchesRegularExpression('/wire:key="[^"]+"/', $etiqueta, "{$vista}: el diálogo necesita wire:key");
            if (str_contains($etiqueta, 'x-on:close=')) {
                $this->assertMatchesRegularExpression('/x-on:close="\$wire\.\w+\(/', $etiqueta);
            }
        }

        $this->addToAssertionCount(1);
    }

    /** Los flux:button con :loading="true" sin wire:click necesitan wire:target o el spinner sale con cualquier petición. */
    #[DataProvider('vistas')]
    public function test_flux_button_con_loading_forzado_lleva_wire_target(string $vista, string $clase): void
    {
        $fuente = $this->fuente($vista);
        preg_match_all('/<flux:button\b[^>]*:loading="true"[^>]*>/s', $fuente, $m);

        foreach ($m[0] as $etiqueta) {
            $this->assertMatchesRegularExpression('/wire:target="\w+(\(.*?\))?"/', $etiqueta, "{$vista}: flux:button :loading sin wire:target");
        }

        $this->addToAssertionCount(1);
    }

    /** Trazabilidad: la barra y el atenuado esperan (delay) para no parpadear en filtros rápidos. */
    public function test_trazabilidad_el_progreso_y_el_atenuado_usan_delay(): void
    {
        $fuente = $this->fuente('livewire/trazabilidad/index');

        $this->assertStringContainsString('wire:loading.delay.block class="traza-progreso hidden"', $fuente);
        $this->assertStringContainsString('wire:loading.delay.class="opacity-50 pointer-events-none"', $fuente);
    }

    /** Crudo: el botón de un paro abre el <dialog> hermano; si alguien mete un nodo entre ambos se rompe. */
    public function test_crudo_el_boton_del_paro_va_justo_antes_de_su_dialog(): void
    {
        $fuente = $this->fuente('livewire/crudo/machine-detail');

        $this->assertMatchesRegularExpression(
            '/x-on:click="\$el\.nextElementSibling\.showModal\(\)"\s*>\s*<span class="sr-only">[^<]*<\/span>\s*<\/button>\s*<dialog\s+class="crudo-paro-dialog"/s',
            $fuente
        );
    }

    /** Nuevo requerimiento: Agregar es flux:button con wire:click, así su spinner queda acotado a agregarRequerimiento. */
    public function test_nuevo_requerimiento_agregar_tiene_spinner_acotado(): void
    {
        $fuente = $this->fuente('livewire/inventario-trama/nuevo-requerimiento');

        $this->assertMatchesRegularExpression('/<flux:button[^>]*wire:click="agregarRequerimiento"/s', $fuente);
        // El viejo <span wire:loading> sin target mostraba "Agregando..." en cualquier wire:model.live.
        $this->assertDoesNotMatchRegularExpression('/<span wire:loading(\.remove)?>/', $fuente);
    }
}
