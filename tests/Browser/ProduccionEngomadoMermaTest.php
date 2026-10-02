<?php

declare(strict_types=1);

use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;

uses(ModuloUrdEng::class);

/**
 * Producción Engomado (pantalla real, layout completo) en Chromium: la merma con goma se
 * guarda al salir del campo vía window.http → actualizar-campo-orden, avisa con un toast
 * y queda en EngProgramaEngomado. Mismo esquema sqlite que tests/Feature/UrdEng/ProduccionEngomadoTest.
 */
beforeEach(function () {
    $this->prepararSqlite();
    foreach ([EngProgramaEngomado::class, EngProduccionEngomado::class, EngProduccionFormulacionModel::class,
        UrdProgramaUrdido::class, UrdJuliosOrden::class, CatUbicaciones::class] as $modelo) {
        $this->tablaDe($modelo);
    }

    $db = DB::connection('sqlsrv');
    $db->table('EngProgramaEngomado')->insert(['Id' => 1, 'Folio' => 'U00101', 'Status' => 'En Proceso', 'NoTelas' => 2]);
    $db->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'Finalizado']);
    $db->table('CatUbicaciones')->insert(['Codigo' => 'A1']);

    $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar'], 43 => ['acceso', 'modificar']]));
});

it('guarda la merma con goma al salir del campo', function () {
    $campo = 'input[data-field="merma_con_goma"]';

    visit('/engomado/modulo-produccion-engomado?orden_id=1')
        ->assertSee('Merma con Goma')
        // El HTML llega antes que el módulo de Vite: sin esto el change sale sin listener.
        ->assertScript('document.readyState === "complete"')
        ->fill($campo, '12.5')
        ->keys($campo, 'Tab')
        ->assertSee('Merma con goma actualizado correctamente')
        ->assertNoJavaScriptErrors();

    expect((float) DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('MermaGoma'))->toBe(12.5);
});
