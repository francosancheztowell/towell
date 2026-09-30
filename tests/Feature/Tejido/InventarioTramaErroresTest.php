<?php

namespace Tests\Feature\Tejido;

use App\Livewire\InventarioTrama\NuevoRequerimiento;
use App\Models\Tejido\TejTrama;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/** Inventario de trama (19-02): el aviso de error al guardar no expone detalle interno (SEC-07). */
class InventarioTramaErroresTest extends TestCase
{
    private function componenteQueFalla(\Throwable $error)
    {
        $this->mock(NuevoRequerimientoService::class, function (MockInterface $m) use ($error) {
            $m->shouldReceive('construirVm')->andReturn([
                'folio' => 'TR00007', 'pageTitle' => 'Nuevo Requerimiento', 'fecha' => null, 'turnoDesc' => null,
                'enProcesoExists' => false, 'telares' => [], 'listaTelares' => [],
            ]);
            $m->shouldReceive('consumosDesdeTelares')->once()->andReturn([]);
            $m->shouldReceive('guardar')->once()->andThrow($error);
        });

        return Livewire::test(NuevoRequerimiento::class)->call('guardar');
    }

    public function test_error_de_bd_no_llega_al_aviso(): void
    {
        $c = $this->componenteQueFalla(new \RuntimeException('SQLSTATE[42S02]: Invalid object name dbo.TejTrama'));

        $c->assertDispatched('aviso', fn (string $evento, array $p) => $p['tipo'] === 'error'
            && str_starts_with($p['texto'], 'Error al guardar el requerimiento (ref: ')
            && ! str_contains($p['texto'], 'SQLSTATE'));
        $c->assertSet('guardando', false);
    }

    public function test_find_or_fail_no_expone_la_clase_del_modelo(): void
    {
        $e = (new ModelNotFoundException)->setModel(TejTrama::class, ['X']);
        $this->componenteQueFalla($e)->assertDispatched('aviso', fn (string $evento, array $p) => $p['tipo'] === 'error'
            && ! str_contains($p['texto'], 'TejTrama') && str_contains($p['texto'], '(ref: '));
    }

    public function test_mensaje_propio_del_servicio_se_sigue_mostrando(): void
    {
        $this->componenteQueFalla(new ModelNotFoundException('El folio indicado no existe'))
            ->assertDispatched('aviso', tipo: 'error', texto: 'Error al guardar: El folio indicado no existe');
    }
}
