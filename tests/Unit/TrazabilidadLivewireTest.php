<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Livewire\Trazabilidad\Index;
use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use App\Services\Trazabilidad\TrazabilidadResumenService;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class TrazabilidadLivewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(
            TrazabilidadFilterOptionsService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('build')->andReturn([
                    'flog' => collect(['F-100', 'F-200']),
                    'articulo' => collect([
                        ['codigo' => 'ART-1', 'label' => 'ART-1 / Toalla'],
                    ]),
                    'tamano' => collect(['GRANDE']),
                    'color' => collect(),
                    'meses' => collect(),
                ]);
                $mock->shouldReceive('summaryValues')->andReturn([
                    'flogs' => collect(['F-100']),
                    'articulos' => collect(['ART-1 · Toalla']),
                    'tamanos' => collect(['GRANDE']),
                ]);
            }
        );
        $this->mock(
            TrazabilidadResumenService::class,
            fn (MockInterface $mock) => $mock->shouldReceive('build')->andReturn([])
        );
        $this->mock(
            TrazabilidadProduccionService::class,
            fn (MockInterface $mock) => $mock->shouldReceive('buildTablaAvance')->andReturn([])
        );
    }

    public function test_it_initializes_filters_from_the_url_and_renders_the_summary(): void
    {
        Livewire::withQueryParams([
            'flog' => ' F-100 ',
            'mes' => '12,2,99',
            'metrica' => 'peso',
        ])
            ->test(TestableTrazabilidadIndex::class)
            ->assertSet('flog', 'F-100')
            ->assertSet('mes', '12,2')
            ->assertSet('metrica', 'peso')
            ->assertSee('Resumen')
            ->assertSee('F-100');
    }

    public function test_it_updates_an_allowed_filter_and_dispatches_the_current_state(): void
    {
        Livewire::test(TestableTrazabilidadIndex::class)
            ->dispatch(
                'trazabilidad-actualizar-filtro',
                campo: 'articulo',
                valor: ' ART-1 ',
            )
            ->assertSet('articulo', 'ART-1')
            ->assertDispatched(
                'trazabilidad-filtros-actualizados',
                fn (string $event, array $params): bool => $event === 'trazabilidad-filtros-actualizados'
                    && data_get($params, 'filtros.articulo') === 'ART-1'
            )
            ->assertSee('Resumen');
    }

    public function test_reset_clears_filters_and_preserves_the_selected_metric(): void
    {
        Livewire::withQueryParams([
            'flog' => 'F-100',
            'articulo' => 'ART-1',
            'metrica' => 'peso',
        ])
            ->test(TestableTrazabilidadIndex::class)
            ->call('restablecer')
            ->assertSet('flog', '')
            ->assertSet('articulo', '')
            ->assertSet('metrica', 'peso')
            ->assertDispatched('trazabilidad-filtros-actualizados')
            ->assertSee('Selecciona al menos un filtro');
    }

    public function test_it_rejects_unknown_filter_names(): void
    {
        Livewire::test(TestableTrazabilidadIndex::class)
            ->call('actualizarFiltro', 'desconocido', 'valor')
            ->assertStatus(422);
    }
}

class TestableTrazabilidadIndex extends Index
{
    protected function authorizeAccess(): void {}
}
