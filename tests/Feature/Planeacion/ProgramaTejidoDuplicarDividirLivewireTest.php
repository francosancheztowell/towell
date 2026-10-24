<?php

namespace Tests\Feature\Planeacion;

use App\Livewire\Planeacion\ProgramaTejido\CatalogosDestino;
use App\Services\Planeacion\ProgramaTejido\DuplicarTejido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Planeacion\Concerns\DialogoDuplicarDividir;
use Tests\TestCase;

/**
 * Diálogo Livewire de Duplicar/Dividir sobre la lógica de DividirTejido/DuplicarTejido. Escenario
 * en {@see DialogoDuplicarDividir}; Vincular en ProgramaTejidoVincularLivewireTest.
 */
class ProgramaTejidoDuplicarDividirLivewireTest extends TestCase
{
    use DialogoDuplicarDividir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDialogo();
    }

    public function test_abrir_carga_el_origen_y_las_filas_de_cada_modo(): void
    {
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSet('registroId', 1)
            ->assertSet('modo', 'duplicar')
            ->assertSet('esGrupo', false)
            ->assertSet('origen.disponible', 800.0)
            ->assertSet('filas.duplicar.0.pedido', '1000')
            ->assertSet('filas.duplicar.0.saldo', '1000')
            ->assertSet('filas.dividir.0.telar', '201')
            ->assertSet('filas.dividir.0.pedido', '1000') // (saldo 800 + producido 200)
            ->assertSet('filas.dividir.0.aviso', null)
            ->assertSet('filas.dividir.0.existente', true)
            ->assertCount('filas.dividir', 2)
            ->assertSee('Duplicar registro');

        // Telares de SMIT juntan los alias (SMITH) en una sola lista.
        $this->assertSame(['201', '210'], CatalogosDestino::telares()['SMIT']);
    }

    public function test_grupo_abre_en_dividir_con_el_saldo_del_grupo(): void
    {
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 3)
            ->assertSet('esGrupo', true)
            ->assertSet('modo', 'dividir')
            ->assertSet('origen.disponible', 1000.0)
            ->assertCount('filas.dividir', 3);
    }

    public function test_redistribuir_grupo_sin_usar_la_fila_vacia(): void
    {
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 3)
            ->set('filas.dividir.0.pedido', '700')
            ->set('filas.dividir.1.pedido', '300')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado', fn ($e, $p) => $p['ord_compartida'] === '7');

        $this->assertEqualsWithDelta(700, DB::table('ReqProgramaTejido')->where('Id', 3)->value('SaldoPedido'), 0.01);
        $this->assertEqualsWithDelta(300, DB::table('ReqProgramaTejido')->where('Id', 4)->value('SaldoPedido'), 0.01);
        $this->assertSame([], $this->filasNuevas());
        $this->assertSame([30003, 30003], DB::table('ReqProgramaTejido')->where('OrdCompartida', 7)->orderBy('Id')->pluck('OrdPrincipal')->map(fn ($v) => (int) $v)->all());
    }

    public function test_cambiar_de_modo_no_pierde_lo_capturado(): void
    {
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.descripcion', 'copia')
            ->set('modo', 'dividir')
            ->set('filas.dividir.1.pedido', '300')
            ->set('modo', 'duplicar')
            ->assertSet('filas.duplicar.0.descripcion', 'copia')
            ->assertSet('filas.dividir.1.pedido', '300');
    }

    /**
     * Sin NoProduccion se divide igual (owner, 23-oct): original y parte comparten un número
     * temporal (900000000 + Id de la parte), sin líder ni OrdPrincipal hasta liberar.
     */
    public function test_sin_no_produccion_divide_con_numero_temporal_y_sin_lider(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['TamanoClave' => 'CLV-1', 'FechaArranque' => '2026-09-03 18:10:00']);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 2)
            ->assertSet('esGrupo', false)
            ->set('modo', 'dividir')
            ->assertSet('modo', 'dividir')
            ->set('filas.dividir.0.pedido', '300')
            ->call('elegirTelar', 1, 'SMIT|210')
            ->set('filas.dividir.1.pedido', '200')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('aviso', null)
            ->assertDispatched('pt-duplicar-guardado', fn ($e, $p) => $p['modo'] === 'dividir'
                && str_contains($p['message'], 'toma su número al liberar'));

        [$parte] = $this->filasNuevas();
        $grupo = 900000000 + (int) $parte->Id;
        $original = DB::table('ReqProgramaTejido')->where('Id', 2)->first();
        $this->assertSame([$grupo, $grupo], [(int) $original->OrdCompartida, (int) $parte->OrdCompartida]);
        $this->assertSame([null, null], [$original->OrdCompartidaLider, $parte->OrdCompartidaLider]);
        $this->assertSame([null, null], [$original->OrdPrincipal, $parte->OrdPrincipal]);
        $this->assertSame([300.0, 200.0], [(float) $original->SaldoPedido, (float) $parte->SaldoPedido]);
        $this->assertNull($parte->NoProduccion);
        $this->assertNull($parte->FechaArranque, 'la parte no hereda el arranque del original');

        // Abrir un miembro del grupo temporal redistribuye dentro del mismo grupo.
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 2)
            ->assertSet('esGrupo', true)
            ->assertSet('modo', 'dividir')
            ->assertSet('origen.disponible', 500.0)
            ->set('filas.dividir.0.pedido', '100')
            ->set('filas.dividir.1.pedido', '400')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado');
        $this->assertSame([100.0, 400.0], DB::table('ReqProgramaTejido')->where('OrdCompartida', $grupo)->orderBy('Id')->pluck('SaldoPedido')->map(fn ($v) => (float) $v)->all());
        $this->assertSame(0, DB::table('ReqProgramaTejido')->where('OrdCompartida', $grupo)->where('OrdCompartidaLider', 1)->count());
    }

    public function test_pedido_y_porcentaje_recalculan_el_saldo_al_duplicar(): void
    {
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.pedido', '1,000')
            ->set('filas.duplicar.0.porcSeg', '5')
            ->assertSet('filas.duplicar.0.saldo', '1050');
    }

    /**
     * Lo que ->set() no ve: en Livewire 4 wire:model.blur no manda request, así que el saldo y el
     * cuadre se calculan en el navegador (ptDuplicar, duplicar.ts), y wire:blur en Clave/Flog
     * volvía a consultar y pisaba Flog/Descripción con solo pasar por el campo.
     */
    public function test_cantidades_se_calculan_en_el_cliente_y_clave_flog_confirman_solo_al_cambiar(): void
    {
        $duplicar = $this->componente()->dispatch('pt-duplicar-abrir', id: 1);
        // En Duplicar el saldo es texto que sale de pedido y %2as: no hay input de saldo.
        $duplicar->assertSeeHtml("x-text=\"ptDuplicar.saldo(\$wire.\$get('filas.duplicar.0'))\">1,000</span>")
            ->assertDontSeeHtml('wire:model="filas.duplicar.0.saldo"')
            ->assertDontSee('Observaciones') // el modal ya no las captura: se quedan las del original
            ->assertSeeHtml('wire:change="confirmar(\'clave\', 0)"')
            ->assertSeeHtml('wire:change="confirmar(\'flog\', 0)"')
            ->assertDontSeeHtml('wire:blur="confirmar(');
        foreach (['pedido', 'porcSeg', 'saldo'] as $campo) {
            $duplicar->assertDontSeeHtml("wire:model.blur=\"filas.duplicar.0.$campo\"");
        }

        // En Dividir también se captura el pedido (el original toma la diferencia) y el saldo es texto.
        $this->componente()->dispatch('pt-duplicar-abrir', id: 1)->set('modo', 'dividir')
            ->assertSeeHtml('x-data="ptDuplicar.cuadreVivo(800)"')
            ->assertSeeHtml("ptDuplicar.absorber(\$wire, 0, 'pedido', \$el.value)")
            ->assertSeeHtml("x-text=\"ptDuplicar.saldo(\$wire.\$get('filas.dividir.0'))\">800</span>")
            ->assertDontSeeHtml('wire:model="filas.dividir.0.saldo"')
            ->assertSeeHtml('x-text="c.estado">Cuadra</span>');
    }

    public function test_dividir_parte_del_saldo_y_avisa_si_no_coincide_con_el_pedido_del_programa(): void
    {
        // Saldo 900 con 200 producidas da pedido 1,100, pero el programa dice 1,000: se avisa, no se bloquea.
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['SaldoPedido' => 900]);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSet('filas.dividir.0.pedido', '1100')
            ->assertSet('filas.dividir.0.aviso', 'En el programa el pedido es 1,000.00; con el saldo pendiente da 1,100.00.')
            ->set('modo', 'dividir')
            ->assertSeeHtml('En el programa el pedido es 1,000.00; con el saldo pendiente da 1,100.00.');
    }

    public function test_sin_saldo_pendiente_no_deja_dividir(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['SaldoPedido' => 0]);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('modo', 'dividir')
            ->assertSet('modo', 'duplicar')
            ->assertSeeHtml('Dividir no está disponible: no hay saldo pendiente que dividir.');
    }

    public function test_pedido_menor_a_lo_producido_no_se_guarda(): void
    {
        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('modo', 'dividir')
            ->set('filas.dividir.0.pedido', '100') // producidas 200: saldo -100
            ->set('filas.dividir.1.telar', '210')
            ->set('filas.dividir.1.pedido', '900');
        $this->assertSame('El pedido de la fila 1 es menor a lo ya producido.', $c->instance()->pendiente());

        $c->call('guardar')
            ->assertHasErrors(['filas.dividir.0.pedido' => 'El pedido no puede ser menor a lo ya producido (200).'])
            ->assertNotDispatched('pt-duplicar-guardado');
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_descuadre_deshabilita_guardar_y_no_toca_la_bd(): void
    {
        $antes = DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray();

        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('modo', 'dividir')
            ->set('filas.dividir.1.telar', '210')
            ->set('filas.dividir.1.pedido', '300');

        $this->assertFalse($c->instance()->cuadre()['cuadra']);
        $this->assertSame(300.0, $c->instance()->cuadre()['diferencia']);
        $c->assertSeeHtml('Sobran 300');

        $c->call('guardar')->assertNotDispatched('pt-duplicar-guardado')->assertSet('registroId', 1);
        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray());
    }

    public function test_guardar_dividir_despacha_el_json_del_endpoint(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['PesoGRM2' => 676.44, 'AnchoToalla' => 40]);
        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('modo', 'dividir')
            ->set('filas.dividir.0.pedido', '700') // saldo 500 + producido 200
            ->set('filas.dividir.1.telar', '210')
            ->set('filas.dividir.1.pedido', '300');

        $this->assertTrue($c->instance()->cuadre()['cuadra']);

        $c->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('aviso', null)
            ->assertDispatched('pt-duplicar-guardado', fn ($evento, $p) => $p['success'] === true
                && $p['modo'] === 'dividir'
                && $p['ord_compartida'] === '30001' // texto: la columna es nvarchar (en Muestras "M3175")
                && $p['registro_id_original'] === 1
                && count($p['registros_ids']) === 1)
            ->assertSet('registroId', null);

        $this->assertEqualsWithDelta(500, DB::table('ReqProgramaTejido')->where('Id', 1)->value('SaldoPedido'), 0.01);
        $this->assertEqualsWithDelta(700, DB::table('ReqProgramaTejido')->where('Id', 1)->value('TotalPedido'), 0.01); // el capturado
        [$nuevo] = $this->filasNuevas();
        $this->assertSame('210', (string) $nuevo->NoTelarId);
        $this->assertEqualsWithDelta(300, $nuevo->SaldoPedido, 0.01);

        // Líder = el original (único con NoProduccion); OrdPrincipal = su NoProduccion en todo el grupo.
        $original = DB::table('ReqProgramaTejido')->where('Id', 1)->first();
        $this->assertSame([30001, 1, 30001], [(int) $original->OrdCompartida, (int) $original->OrdCompartidaLider, (int) $original->OrdPrincipal]);
        $this->assertSame([30001, 0, 30001], [(int) $nuevo->OrdCompartida, (int) $nuevo->OrdCompartidaLider, (int) $nuevo->OrdPrincipal]);
        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30001')->first();
        $this->assertSame([30001, 1, 30001], [(int) $cat->OrdCompartida, (int) $cat->OrdCompartidaLider, (int) $cat->OrdPrincipal]);
        $this->assertNull($nuevo->FechaArranque, 'la parte no hereda el arranque del original');
        $this->assertEqualsWithDelta(1607.14, (float) $nuevo->PesoGRM2, 0.001, 'la parte calcula su PesoGRM2, no copia el 676.44');
    }

    public function test_guardar_duplicar_despacha_el_json_con_registros_ids(): void
    {
        // El original trae un PesoGRM2 copiado de otra orden; la copia toma peso, largo y ancho del
        // Modelo de su clave y calcula el suyo.
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['PesoGRM2' => 676.44, 'AnchoToalla' => 40]);
        DB::table('ReqModelosCodificados')->where('TamanoClave', 'CLV-1')->update(['PesoCrudo' => 300, 'LargoToalla' => 60, 'AnchoToalla' => 50]);
        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.telar', '210')
            ->set('filas.duplicar.0.pedido', '1,500')
            ->set('filas.duplicar.0.saldo', '5') // el saldo no se captura: lo que mande el cliente se ignora
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado', fn ($evento, $p) => $p['modo'] === 'duplicar' && count($p['registros_ids']) === 1);

        [$nuevo] = $this->filasNuevas();
        $this->assertSame(['SMIT', '210'], [$nuevo->SalonTejidoId, (string) $nuevo->NoTelarId]);
        $this->assertEqualsWithDelta(1500, $nuevo->TotalPedido, 0.01);
        $this->assertEqualsWithDelta(1500, $nuevo->SaldoPedido, 0.01);
        $this->assertSame([null, null, null, null], [$nuevo->OrdCompartida, $nuevo->NoProduccion, $nuevo->FechaArranque, $nuevo->FechaFinaliza]);
        $this->assertSame([300, 60, 50.0], [(int) $nuevo->PesoCrudo, (int) $nuevo->LargoCrudo, (float) $nuevo->AnchoToalla]);
        $this->assertEqualsWithDelta(1000.0, (float) $nuevo->PesoGRM2, 0.001, '300 × 10000 / (60 × 50), no el 676.44 del original');
    }

    public function test_duplicar_sin_largo_o_ancho_deja_peso_grm2_vacio_y_no_el_del_original(): void
    {
        // El Modelo CLV-1 del fixture no trae peso, largo ni ancho: no hay con qué calcular.
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['PesoGRM2' => 676.44]);

        $this->componente()->dispatch('pt-duplicar-abrir', id: 1)
            ->call('elegirTelar', 0, 'SMIT|210')
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado');

        [$nuevo] = $this->filasNuevas();
        $this->assertNull($nuevo->PesoGRM2);
    }

    public function test_clave_se_autocompleta_por_prefijo_del_salon_y_se_elige(): void
    {
        DB::table('ReqModelosCodificados')->insert([
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'CLV-1'],
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'CLV-2'],
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'OTRA'],
            ['SalonTejidoId' => 'JACQUARD', 'TamanoClave' => 'CLV-9'],
        ]);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.clave', 'CLV')
            ->assertSet('sugerencias', ['campo' => 'clave', 'i' => 0, 'items' => [
                ['valor' => 'CLV-1', 'detalle' => ''], ['valor' => 'CLV-2', 'detalle' => ''], ['valor' => 'CLV-9', 'detalle' => ''],
            ]])
            ->call('elegir', 'clave', 0, 'CLV-2')
            ->assertSet('filas.duplicar.0.clave', 'CLV-2')
            ->assertSet('sugerencias', [])
            ->assertHasNoErrors('filas.duplicar.0.clave');
    }

    public function test_telar_lista_los_salones_de_la_clave_por_numero_y_fija_el_salon(): void
    {
        DB::table('ReqModelosCodificados')->insert([
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'CLV-X'],
            ['SalonTejidoId' => 'JACQUARD', 'TamanoClave' => 'CLV-X'],
        ]);

        // 201 y 210 son SMIT (210 viene como alias SMITH), 205 es JACQUARD: orden por número.
        $this->assertSame(['SMIT|201', 'JACQUARD|205', 'SMIT|210'], array_column(CatalogosDestino::telaresParaClave('CLV-X'), 'valor'));

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.clave', 'CLV-X')
            ->call('elegirTelar', 0, 'JACQUARD|205')
            ->assertSet('filas.duplicar.0.salon', 'JACQUARD')
            ->assertSet('filas.duplicar.0.telar', '205')
            ->call('elegirTelar', 0, 'JACQUARD|999') // no está en la lista: se ignora
            ->assertSet('filas.duplicar.0.telar', '205');
    }

    public function test_clave_que_no_existe_en_modelos_bloquea_guardar(): void
    {
        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->call('elegirTelar', 0, 'SMIT|210')
            ->set('filas.duplicar.0.clave', 'NO-EXISTE')
            ->assertHasNoErrors('filas.duplicar.0.clave') // mientras escribe no se marca
            ->call('confirmar', 'clave', 0)
            ->assertHasErrors(['filas.duplicar.0.clave' => 'La clave NO-EXISTE no existe en Modelos de SMIT.'])
            ->assertSeeHtml('La clave NO-EXISTE no existe en Modelos de SMIT.')
            ->assertDontSeeHtml('Corrige la clave'); // el error va bajo el campo, sin aviso sobre el botón
        $this->assertSame('', $c->instance()->pendiente()); // botón bloqueado

        $c->call('guardar')->assertSet('registroId', 1)->assertNotDispatched('pt-duplicar-guardado');
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_sin_telar_el_boton_se_bloquea_y_dice_por_que(): void
    {
        // Al abrir, la fila trae el telar del registro elegido (201) y ya se puede guardar.
        $c = $this->componente()->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSet('filas.duplicar.0.telar', '201')
            ->assertSeeHtml('value="SMIT|201" title="SMIT" selected');
        $this->assertNull($c->instance()->pendiente());

        // "Agregar telar" llega sin telar: hay que elegirlo.
        $c->call('agregarFila')->assertSet('filas.duplicar.1.telar', '');
        $this->assertSame('Elige el telar en la fila 2.', $c->instance()->pendiente());
        $c->call('quitarFila', 1)->call('elegirTelar', 0, '');

        $this->assertSame('Elige el telar en la fila 1.', $c->instance()->pendiente());
        $c->assertSeeHtml('Elige el telar en la fila 1.')
            ->call('guardar')
            ->assertHasErrors(['filas.duplicar.0.telar' => 'Elige el telar.'])
            ->assertNotDispatched('pt-duplicar-guardado')
            ->call('elegirTelar', 0, 'SMIT|210')
            ->assertHasNoErrors('filas.duplicar.0.telar');
        $this->assertNull($c->instance()->pendiente());

        // Volver a la opción "Telar" quita el telar: antes se quedaba el 210 y se guardaba con él.
        $c->call('elegirTelar', 0, '')->assertSet('filas.duplicar.0.telar', '');
        $this->assertSame('Elige el telar en la fila 1.', $c->instance()->pendiente());
        $c->call('guardar')->assertNotDispatched('pt-duplicar-guardado');
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_flog_debe_estar_vigente_en_ax(): void
    {
        Cache::put('pt-duplicar:flogs', ['RS-FLOG-1' => 'PROYECTO UNO'], 600);

        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->call('elegirTelar', 0, 'SMIT|210')
            ->set('filas.duplicar.0.flog', 'rs-flog-1')
            ->call('confirmar', 'flog', 0)
            ->assertSet('filas.duplicar.0.flog', 'RS-FLOG-1')
            ->assertSet('filas.duplicar.0.descripcion', 'PROYECTO UNO')
            ->assertHasNoErrors('filas.duplicar.0.flog')
            ->set('filas.duplicar.0.flog', 'VIEJO-1')
            ->call('confirmar', 'flog', 0)
            ->assertHasErrors(['filas.duplicar.0.flog' => 'El flog VIEJO-1 no está vigente en AX.']);

        // Releer AX falla en los tests: se queda el catálogo que había y la validación sigue.
        $c->call('guardar')->assertHasErrors('filas.duplicar.0.flog')->assertNotDispatched('pt-duplicar-guardado');
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_al_enfocar_flog_salen_los_vigentes_primero_los_del_articulo(): void
    {
        Cache::put('pt-duplicar:flogs', ['A-1' => 'UNO', 'B-2' => 'DOS', 'C-3' => 'TRES'], 600);
        Cache::put('pt-duplicar:flogs-articulo:7139|MB', ['C-3', 'X-VIEJO'], 600);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.itemId', '7139')
            ->set('filas.duplicar.0.inventSizeId', 'MB')
            ->call('abrirSugerencias', 'flog', 0)
            ->assertSet('sugerencias', ['campo' => 'flog', 'i' => 0, 'items' => [
                ['valor' => 'C-3', 'detalle' => ''], ['valor' => 'A-1', 'detalle' => ''], ['valor' => 'B-2', 'detalle' => ''],
            ]])
            ->assertSeeHtml("wire:focus=\"abrirSugerencias('flog', 0)\"")
            ->assertSeeHtml('x-on:mousedown.prevent');
    }

    public function test_sin_permiso_de_crear_es_forbidden(): void
    {
        $this->componente(['acceso'])
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertForbidden();
    }

    public function test_perder_crear_con_el_dialogo_abierto_bloquea_cualquier_accion(): void
    {
        $c = $this->componente()->dispatch('pt-duplicar-abrir', id: 1);
        $this->actingAs($this->usuarioConPermisos([2 => ['acceso']]));

        // Antes solo abrir/guardar revisaban: set de una celda o agregar fila consultaban Modelos/AX igual.
        $c->set('filas.duplicar.0.clave', 'CLV-1')->assertForbidden();
    }

    public function test_redistribuir_grupo_respeta_lo_capturado_en_la_fila_nueva(): void
    {
        DB::table('ReqModelosCodificados')->insert(['SalonTejidoId' => 'JACQUARD', 'TamanoClave' => 'CLV-1']);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 3)
            ->set('filas.dividir.0.pedido', '500')
            ->set('filas.dividir.1.pedido', '400')
            ->set('filas.dividir.2.telar', '205')
            ->set('filas.dividir.2.flog', 'FLOG-NUEVO')
            ->set('filas.dividir.2.pedido', '100')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado');

        $nuevas = $this->filasNuevas();
        $this->assertCount(1, $nuevas);
        // Antes la redistribución ignoraba flog/producto/clave de la fila y copiaba los del grupo.
        $this->assertSame('FLOG-NUEVO', $nuevas[0]->FlogsId);
        $this->assertEqualsWithDelta(100, $nuevas[0]->SaldoPedido, 0.01);
    }

    public function test_duplicar_copia_el_registro_elegido_aunque_el_salon_en_bd_sea_alias(): void
    {
        // En BD 'SMITH' (alias de SMIT): la comparación cruda descartaba el elegido (Id 1) y
        // duplicaba el último del telar (Id 2, sin calendario ni producto).
        DB::table('ReqProgramaTejido')->whereIn('Id', [1, 2])->update(['SalonTejidoId' => 'SMITH']);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.telar', '210')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado');

        $nuevas = $this->filasNuevas();
        $this->assertCount(1, $nuevas);
        $this->assertSame('Calendario Tej1', $nuevas[0]->CalendarioId);
    }

    public function test_muestras_usa_su_tabla_y_su_permiso(): void
    {
        $this->componente(['crear'], 'muestras')
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSet('filas.duplicar.0.telar', '201')
            ->set('filas.duplicar.0.telar', '210')
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado');

        $this->assertCount(1, $this->filasNuevas('MuestrasPrograma'));
        $this->assertSame([], $this->filasNuevas());
    }

    public function test_al_enfocar_modelo_salen_las_tallas_del_articulo_con_talla_y_producto(): void
    {
        DB::table('ReqModelosCodificados')->insert([
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MB906', 'ItemId' => '906', 'InventSizeId' => 'MB', 'Nombre' => 'TOALLA BAÑO'],
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MANOS906', 'ItemId' => '906', 'InventSizeId' => 'MAN', 'Nombre' => 'TOALLA MANOS'],
            ['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MB800', 'ItemId' => '800', 'InventSizeId' => 'MB', 'Nombre' => 'OTRO'],
        ]);
        $mb906 = ['valor' => 'MB906', 'detalle' => 'MB · TOALLA BAÑO'];

        $c = $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.itemId', '906')
            ->call('abrirSugerencias', 'clave', 0)
            ->assertSet('sugerencias.items', [['valor' => 'MANOS906', 'detalle' => 'MAN · TOALLA MANOS'], $mb906]);

        // Al escribir, primero las del mismo artículo y luego las demás que empiezan igual.
        $c->set('filas.duplicar.0.clave', 'MB')
            ->assertSet('sugerencias.items', [$mb906, ['valor' => 'MB800', 'detalle' => 'MB · OTRO']])
            ->assertSeeHtml("wire:focus=\"abrirSugerencias('clave', 0)\"")
            ->assertSeeHtml('MB · TOALLA BAÑO');

        // Por número de artículo: la talla va antes ("906" no empieza ninguna clave). Primero las del
        // artículo 906 exacto, luego las que solo lo contienen (MB3906 es el artículo 3906).
        DB::table('ReqModelosCodificados')->insert(['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MB3906', 'ItemId' => '3906', 'InventSizeId' => 'MB', 'Nombre' => 'OTRA']);
        $c->set('filas.duplicar.0.itemId', '800')
            ->set('filas.duplicar.0.clave', '906')
            ->assertSet('sugerencias.items', [['valor' => 'MANOS906', 'detalle' => 'MAN · TOALLA MANOS'], $mb906, ['valor' => 'MB3906', 'detalle' => 'MB · OTRA']]);
    }

    public function test_el_flog_puede_ir_vacio_pero_no_mal_escrito(): void
    {
        Cache::put('pt-duplicar:flogs', ['RS-FLOG-1' => 'PROYECTO UNO'], 600);

        $this->componente()
            ->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.flog', '')
            ->call('confirmar', 'flog', 0)
            ->assertHasNoErrors('filas.duplicar.0.flog')
            ->set('filas.duplicar.0.flog', 'RS-FLGO-1') // mal escrito
            ->call('confirmar', 'flog', 0)
            ->assertHasErrors(['filas.duplicar.0.flog' => 'El flog RS-FLGO-1 no está vigente en AX.'])
            ->set('filas.duplicar.0.flog', '')
            ->call('confirmar', 'flog', 0)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('pt-duplicar-guardado');

        $this->assertCount(1, $this->filasNuevas());
    }

    /** Las filas nuevas abren en "NA" aunque el original tenga otra (owner, 23-oct); NA = sin aplicación. */
    public function test_aplicacion_na_por_default_aunque_el_original_tenga_otra(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['AplicacionId' => 'BOR']);

        $this->componente()->dispatch('pt-duplicar-abrir', id: 1)
            ->assertSet('filas.duplicar.0.aplicacion', 'NA')
            ->call('agregarFila')
            ->assertSet('filas.duplicar.1.aplicacion', 'NA')
            ->assertSet('filas.dividir.0.aplicacion', 'BOR') // la que ya existe muestra la suya
            ->assertSet('filas.dividir.1.aplicacion', 'NA')
            ->call('quitarFila', 1)
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado');

        [$nuevo] = $this->filasNuevas();
        $this->assertNull($nuevo->AplicacionId, 'antes la copia heredaba BOR del original');
    }

    public function test_duplicar_respeta_la_aplicacion_que_se_elige(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['AplicacionId' => 'BOR']);

        $this->componente()->dispatch('pt-duplicar-abrir', id: 1)
            ->set('filas.duplicar.0.aplicacion', 'BOR')
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado');

        [$nuevo] = $this->filasNuevas();
        $this->assertSame('BOR', $nuevo->AplicacionId);
    }

    /** Dividir: la parte nueva toma la aplicación de su fila (NA por default) y el original conserva la suya. */
    public function test_dividir_la_parte_nueva_va_en_na_y_el_original_conserva_su_aplicacion(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['AplicacionId' => 'BOR']);

        $this->componente()->dispatch('pt-duplicar-abrir', id: 1)
            ->set('modo', 'dividir')
            ->set('filas.dividir.0.pedido', '700')
            ->call('elegirTelar', 1, 'SMIT|210')
            ->set('filas.dividir.1.pedido', '300')
            ->call('guardar')
            ->assertDispatched('pt-duplicar-guardado');

        [$parte] = $this->filasNuevas();
        $this->assertNull($parte->AplicacionId, 'antes la parte heredaba BOR por replicate()');
        $this->assertSame('BOR', DB::table('ReqProgramaTejido')->where('Id', 1)->value('AplicacionId'));
    }
}
