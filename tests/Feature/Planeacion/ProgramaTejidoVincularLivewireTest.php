<?php

namespace Tests\Feature\Planeacion;

use App\Services\Planeacion\ProgramaTejido\DuplicarTejido;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\DialogoDuplicarDividir;
use Tests\TestCase;

/**
 * Vincular en el diálogo de Duplicar/Dividir (owner, 23-oct-2026): las órdenes nuevas (2+) forman
 * su propio grupo, sin el original, con número temporal (900000000 + Id) y sin líder hasta liberar.
 */
class ProgramaTejidoVincularLivewireTest extends TestCase
{
    use DialogoDuplicarDividir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDialogo();
    }

    public function test_vincular_exige_modificar(): void
    {
        // Sin 'modificar' no aparece la opción, y forzar el modo desde el cliente lo regresa a duplicar.
        $this->componente(['crear'])
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertDontSeeHtml('value="vincular"')
            ->set('modo', 'vincular')
            ->assertSet('modo', 'duplicar');

        $this->componente(['crear', 'modificar'])
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSeeHtml('value="vincular"')
            ->set('modo', 'vincular')
            ->call('agregarFila')
            ->call('elegirTelar', 1, 'SMIT|210')
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado', fn ($evento, $p) => $p['modo'] === 'vincular');
    }

    /** Vincular desde el modal: arma 2+ órdenes nuevas en su propio grupo, ver regla del 23-oct. */
    private function vincularDesde(int $id, array $telares)
    {
        $c = $this->componente(['crear', 'modificar'])->dispatch('pt-duplicar-abrir', id: $id)->set('modo', 'vincular');
        foreach ($telares as $i => $telar) {
            if ($i > 0) {
                $c->call('agregarFila');
            }
            $c->call('elegirTelar', $i, $telar);
        }

        return $c;
    }

    public function test_vincular_agrupa_solo_las_ordenes_nuevas_sin_el_original(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['FechaArranque' => '2026-09-01 06:40:00']);
        $antes = DB::table('ReqProgramaTejido')->where('Id', 1)->first();

        $this->vincularDesde(1, ['SMIT|201', 'SMIT|210', 'SMIT|210'])
            ->assertSeeHtml('este registro no entra al grupo')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado', fn ($e, $p) => $p['modo'] === 'vincular'
                && count($p['registros_ids']) === 3
                && $p['ord_compartida'] === 900000000 + $p['registros_ids'][0]
                && str_contains($p['message'], 'vinculadas entre sí'));

        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->where('Id', 1)->first(), 'el original no se toca');
        $nuevas = collect($this->filasNuevas());
        $grupo = 900000000 + (int) $nuevas->first()->Id;
        $this->assertSame([$grupo, $grupo, $grupo], $nuevas->pluck('OrdCompartida')->map(fn ($v) => (int) $v)->all());
        // Sin NoProduccion no hay líder ni OrdPrincipal hasta liberar; y no heredan el arranque del original.
        foreach (['OrdCompartidaLider', 'OrdPrincipal', 'NoProduccion', 'FechaArranque', 'FechaFinaliza'] as $campo) {
            $this->assertSame([null, null, null], $nuevas->pluck($campo)->all(), $campo);
        }
        $this->assertSame(3, DB::table('ReqProgramaTejido')->where('OrdCompartida', $grupo)->count());
    }

    public function test_vincular_desde_un_miembro_de_grupo_no_toca_el_grupo_existente(): void
    {
        DB::table('ReqModelosCodificados')->insert(['SalonTejidoId' => 'JACQUARD', 'TamanoClave' => 'CLV-1']);
        // Solo lo del grupo: la copia al 202 le quita "Último" al 3, y eso es correcto.
        $grupo7 = fn () => DB::table('ReqProgramaTejido')->where('OrdCompartida', 7)->orderBy('Id')
            ->get(['Id', 'OrdCompartida', 'OrdCompartidaLider', 'OrdPrincipal', 'SaldoPedido', 'TotalPedido'])->toArray();
        $antes = $grupo7();

        $this->vincularDesde(3, ['JACQUARD|202', 'JACQUARD|205'])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado', fn ($e, $p) => $p['modo'] === 'vincular');

        $this->assertEquals($antes, $grupo7(), 'el grupo 7 sigue igual');
        $nuevas = collect($this->filasNuevas());
        $this->assertCount(2, $nuevas);
        $this->assertSame(1, $nuevas->pluck('OrdCompartida')->unique()->count());
        $this->assertNotSame(7, (int) $nuevas->first()->OrdCompartida);
    }

    public function test_vincular_con_una_sola_orden_se_bloquea_y_dice_por_que(): void
    {
        $c = $this->vincularDesde(1, ['SMIT|210']);
        $this->assertSame('Agrega al menos 2 órdenes para vincularlas entre sí.', $c->instance()->pendiente());
        $c->assertSeeHtml('Agrega al menos 2 órdenes para vincularlas entre sí.')
            ->call('guardar')
            ->assertNotDispatched('pt-duplicar-guardado');
        $this->assertSame([], $this->filasNuevas());

        // Duplicar con una sola sí se puede: la regla es solo de Vincular.
        $c->set('modo', 'duplicar');
        $this->assertNull($c->instance()->pendiente());
    }

    public function test_vincular_por_http_con_una_sola_orden_es_422_sin_escribir(): void
    {
        $r = DuplicarTejido::duplicar([
            'salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1, 'vincular' => true,
            'destinos' => [['telar' => '210', 'pedido' => '100']],
        ]);

        $this->assertSame([false, 422], [$r['success'], $r['status']]);
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_vincular_dos_veces_desde_el_mismo_original_da_grupos_distintos(): void
    {
        $this->vincularDesde(1, ['SMIT|210', 'SMIT|210'])->call('guardar')->assertDispatched('pt-duplicar-guardado');
        $this->vincularDesde(1, ['SMIT|201', 'SMIT|201'])->call('guardar')->assertDispatched('pt-duplicar-guardado');

        $grupos = collect($this->filasNuevas())->groupBy('OrdCompartida')->map->count();
        $this->assertSame([2, 2], $grupos->values()->all());
    }
}
