<?php

namespace Tests\Feature\Monitoreo;

use App\Livewire\Admin\Resumen;
use App\Services\Monitoreo\PanelConsultas;
use Livewire\Livewire;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\Feature\Monitoreo\Concerns\SiembraPanel;
use Tests\TestCase;

/** /admin · Resumen: cifras, barras por hora, errores que necesitan atención y quién está en línea. */
class PanelResumenTest extends TestCase
{
    use PreparaMonitoreo;
    use SiembraPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
    }

    public function test_por_hora_reparte_en_24_cubetas_y_la_ultima_es_la_hora_en_curso(): void
    {
        $this->travelTo(now()->setTime(10, 30));

        $cubetas = PanelConsultas::porHora([
            now(), now()->subMinutes(20),     // esta hora (10:00–10:59)
            now()->subHours(1),               // 9:30
            now()->subHours(23),              // la cubeta más vieja
            now()->subHours(30), null, '',    // fuera de rango o vacías: no cuentan
        ]);

        $this->assertCount(24, $cubetas);
        $this->assertSame(2, $cubetas[23]);
        $this->assertSame(1, $cubetas[22]);
        $this->assertSame(1, $cubetas[0]);
        $this->assertSame(4, array_sum($cubetas));
    }

    public function test_muestra_cifras_errores_abiertos_y_conectados(): void
    {
        $abierto = $this->error(['Clase' => 'App\\Tejido\\FolioDuplicado', 'Mensaje' => 'Folio 12 repetido', 'PrimeraVez' => now()->subHour()]);
        $this->error(['Clase' => 'ResueltoViejo', 'Estado' => 'resuelto', 'PrimeraVez' => now()->subDays(3)]);
        $this->mon('SYSMonErrorEvento')->insert(['ErrorId' => $abierto, 'Fecha' => now()->subMinutes(5)]);

        $operador = $this->crearUsuario(['numero_empleado' => '7702', 'nombre' => 'Operador Telar', 'area' => 'Tejido']);
        $disp = $this->dispositivo(['Nombre' => 'Tablet telar 12', 'UltimoUsuarioId' => $operador->idusuario, 'UltimaRuta' => 'tejido.index']);
        $this->vista($disp, $operador->idusuario);

        Livewire::actingAs($this->admin)->test(Resumen::class)
            ->assertViewHas('cifras', fn (array $c) => $c['abiertos'] === 1 && $c['nuevos'] === 1 && $c['enLinea'] === 1)
            ->assertViewHas('eventosHora', fn (array $h) => array_sum($h) === 1)
            ->assertViewHas('vistasHora', fn (array $h) => array_sum($h) === 1)
            ->assertSee('FolioDuplicado')
            ->assertSee('Folio 12 repetido')
            ->assertDontSee('ResueltoViejo')
            ->assertSee('Operador Telar')
            ->assertSee('tejido.index')
            ->assertSeeHtml('wire:poll.visible.');
    }

    public function test_sin_datos_dice_que_esta_vacio_sin_inventar(): void
    {
        Livewire::actingAs($this->admin)->test(Resumen::class)
            ->assertSee('Sin errores abiertos')
            ->assertSee('Ningún dispositivo se ha conectado en las últimas 24 horas')
            ->assertSee('Sin vistas medidas en los últimos 7 días');
    }
}
