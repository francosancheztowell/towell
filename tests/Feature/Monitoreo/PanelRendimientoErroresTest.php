<?php

namespace Tests\Feature\Monitoreo;

use App\Livewire\Admin\ErrorDetalle;
use App\Livewire\Admin\Errores;
use App\Livewire\Admin\Rendimiento;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\Feature\Monitoreo\Concerns\SiembraPanel;
use Tests\TestCase;

/** MON-26 (rendimiento) y MON-25 (errores con flujo de estado). */
class PanelRendimientoErroresTest extends TestCase
{
    use PreparaMonitoreo;
    use SiembraPanel;

    private int $disp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->disp = $this->dispositivo();
    }

    /** @param  array<int, int>  $valores */
    private function vistas(string $ruta, string $metrica, array $valores, int $diasAtras = 1): void
    {
        foreach ($valores as $i => $ms) {
            $this->vista($this->disp, 1, ['Ruta' => $ruta, $metrica => $ms, 'Inicio' => now()->subDays($diasAtras)->subMinutes($i)]);
        }
    }

    public function test_percentiles_de_rango_mas_cercano(): void
    {
        $this->vistas('a', 'ServidorMs', range(10, 200, 10)); // 20 valores: p50 = 100, p95 = 190
        $this->vistas('b', 'ServidorMs', [5]);
        $this->vistas('a', 'CargaMs', [1000, 2000, 3000]);    // 3 valores: p50 = 2000, p95 = 3000
        $this->vistas('a', 'ServidorMs', [999999], 10);       // semana previa: fuera del rango

        $servidor = PanelConsultas::percentiles('ServidorMs', now()->subDays(7), now());
        $this->assertSame(['n' => 20, 'p50' => 100, 'p95' => 190], $servidor['a']);
        $this->assertSame(['n' => 1, 'p50' => 5, 'p95' => 5], $servidor['b']);
        $this->assertSame(['n' => 3, 'p50' => 2000, 'p95' => 3000], PanelConsultas::percentiles('CargaMs', now()->subDays(7), now())['a']);
    }

    public function test_percentiles_solo_aceptan_metricas_de_la_lista_blanca(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PanelConsultas::percentiles('1; DROP TABLE SYSMonVista', now()->subDay(), now());
    }

    public function test_rendimiento_compara_semanas_y_resalta_lentas(): void
    {
        $this->vistas('pantalla.lenta', 'ServidorMs', [900, 900, 900]);
        $this->vistas('pantalla.lenta', 'ServidorMs', [450, 450], 10);
        $this->vistas('pantalla.rapida', 'ServidorMs', [100, 100]);
        $this->vistas('pantalla.rapida', 'ServidorMs', [100], 10);
        $this->vistas('solo.previa', 'ServidorMs', [100], 10);

        $componente = Livewire::actingAs($this->admin)->test(Rendimiento::class)
            ->assertSee('pantalla.lenta')
            ->assertSee('+100%')
            ->assertSee('pantalla.rapida')
            ->assertSee('0%')
            ->assertDontSee('solo.previa')
            ->assertViewHas('filas', fn ($filas) => $filas->first()['ruta'] === 'pantalla.lenta' && $filas->first()['lenta'] === true);

        $componente->set('soloLentas', true)->assertSee('pantalla.lenta')->assertDontSee('pantalla.rapida');
        $componente->set('soloLentas', false)->set('buscar', 'RAPIDA')->assertSee('pantalla.rapida')->assertDontSee('pantalla.lenta');

        $this->assertTrue(Cache::has(Rendimiento::CACHE));
        $componente->call('recalcular');
    }

    public function test_errores_filtra_por_estado_y_origen(): void
    {
        $this->error(['Clase' => 'App\\Errores\\NuevoPhp']);
        $this->error(['Clase' => 'TypeError', 'Origen' => 'js', 'Estado' => 'visto']);
        $this->error(['Clase' => 'ResueltoViejo', 'Estado' => 'resuelto']);

        $componente = Livewire::actingAs($this->admin)->test(Errores::class)
            ->assertSee('NuevoPhp')
            ->assertSee('TypeError')
            ->assertDontSee('ResueltoViejo');

        $componente->set('origen', 'js')->assertSee('TypeError')->assertDontSee('NuevoPhp');
        $componente->set('origen', '')->set('estado', 'resuelto')->assertSee('ResueltoViejo')->assertDontSee('TypeError');
        $componente->set('estado', 'todos')->assertSee('ResueltoViejo')->assertSee('NuevoPhp');
    }

    public function test_ver_detalle_redirige(): void
    {
        $id = $this->error();

        Livewire::actingAs($this->admin)->test(Errores::class)
            ->call('verDetalle', (string) $id)
            ->assertRedirect(route('admin.errores.show', ['id' => $id]));
    }

    public function test_detalle_muestra_eventos_y_cambia_estado_auditado(): void
    {
        $ana = $this->crearUsuario(['numero_empleado' => '1111', 'nombre' => 'Ana Tejido']);
        $id = $this->error(['Mensaje' => 'Folio N no existe', 'Ruta' => 'tejido.guardar']);
        $this->mon('SYSMonErrorEvento')->insert([
            'ErrorId' => $id, 'Fecha' => now(), 'UsuarioId' => $ana->idusuario, 'DispositivoId' => $this->disp,
            'Url' => '/tejido/guardar', 'Metodo' => 'POST', 'Status' => 500, 'VersionFront' => 'abc123', 'Traza' => "RuntimeException @ app/X.php:10\nInput: folio",
        ]);

        $componente = Livewire::actingAs($this->admin)->test(ErrorDetalle::class, ['errorId' => $id])
            ->assertSee('Folio N no existe')
            ->assertSee('Ana Tejido (#1111)')
            ->assertSee('/tejido/guardar')
            ->assertSee('front abc123')
            ->assertSee('Input: folio');

        $componente->set('estado', 'resuelto')->set('nota', ' Corregido en 1.2 ')->call('guardar')->assertHasNoErrors()->assertDispatched('aviso');

        $error = $this->mon('SYSMonError')->where('Id', $id)->first();
        $this->assertSame('resuelto', $error->Estado);
        $this->assertSame('Corregido en 1.2', $error->Nota);
        $this->assertSame((int) $this->admin->idusuario, (int) $error->ResueltoPor);
        $this->assertNotNull($error->ResueltoEn);

        $acceso = $this->mon('SYSMonAcceso')->where('Tipo', 'admin_accion')->first();
        $this->assertSame('error_estado: #'.$id.' nuevo → resuelto', $acceso->Motivo);
        $this->assertSame((int) $this->admin->idusuario, (int) $acceso->ActorId);

        // Editar la nota de uno ya resuelto conserva quién y cuándo lo resolvió.
        $otroAdmin = $this->crearUsuario(['numero_empleado' => '9002', 'area' => 'Sistemas']);
        $resueltoEn = $error->ResueltoEn;
        Livewire::actingAs($otroAdmin)->test(ErrorDetalle::class, ['errorId' => $id])->set('nota', 'Más contexto')->call('guardar');
        $error = $this->mon('SYSMonError')->where('Id', $id)->first();
        $this->assertSame((int) $this->admin->idusuario, (int) $error->ResueltoPor);
        $this->assertSame($resueltoEn, $error->ResueltoEn);
        $this->assertSame('error_nota: #'.$id.' (resuelto)', $this->mon('SYSMonAcceso')->orderByDesc('Id')->value('Motivo'));

        // Reabrir limpia quién lo resolvió.
        $componente->set('estado', 'visto')->call('guardar');
        $this->assertNull($this->mon('SYSMonError')->where('Id', $id)->value('ResueltoPor'));
    }

    public function test_detalle_rechaza_estado_invalido_y_nota_larga(): void
    {
        $id = $this->error();

        Livewire::actingAs($this->admin)->test(ErrorDetalle::class, ['errorId' => $id])
            ->set('estado', 'borrado')->call('guardar')->assertHasErrors(['estado'])
            ->set('estado', 'visto')->set('nota', str_repeat('x', 501))->call('guardar')->assertHasErrors(['nota' => 'max']);

        $this->assertSame('nuevo', $this->mon('SYSMonError')->where('Id', $id)->value('Estado'));
    }

    public function test_el_id_del_detalle_no_se_puede_cambiar_desde_el_cliente(): void
    {
        $id = $this->error();

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->admin)->test(ErrorDetalle::class, ['errorId' => $id])->set('errorId', $id + 1);
    }
}
