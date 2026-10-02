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

    $p = visit('/engomado/modulo-produccion-engomado?orden_id=1')
        ->assertSee('Merma con Goma');
    $p->script('window.__dbg = []; window.addEventListener("towell:http-error", (e) => window.__dbg.push(JSON.stringify(e.detail)));');
    $p->fill($campo, '12.5')->keys($campo, 'Tab')->wait(2);
    fwrite(STDERR, "\nDBG http=".json_encode($p->script('window.__dbg'))
        .' js='.json_encode($p->script('window.__pestBrowser.jsErrors'))
        .' cfg='.json_encode($p->script('typeof window.http + "|" + !!document.getElementById("produccion-engomado") + "|" + document.querySelectorAll("'.str_replace('"', '\\"', $campo).'").length'))
        .' toasts='.json_encode($p->script('document.getElementById("towell-toasts")?.innerText ?? "none"'))
        .' db='.json_encode(DB::connection('sqlsrv')->table('EngProgramaEngomado')->get())
        .' user='.json_encode(auth()->id())."\n");
    $p->assertSee('Merma con goma actualizado correctamente')
        ->assertNoJavaScriptErrors();

    expect((float) DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('MermaGoma'))->toBe(12.5);
});
