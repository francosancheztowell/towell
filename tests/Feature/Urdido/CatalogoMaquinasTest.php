<?php

declare(strict_types=1);

namespace Tests\Feature\Urdido;

use App\Livewire\Urdido\CatalogoMaquinas;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CatalogoMaquinasTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t) {
            $t->string('MaquinaId')->primary();
            $t->string('Nombre')->nullable();
            $t->string('Departamento')->nullable();
            $t->string('Codificacion')->nullable();
        });
        URDCatalogoMaquina::create(['MaquinaId' => 'KM-2', 'Nombre' => 'Karl Mayer', 'Departamento' => 'Urdido']);
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CatalogoMaquinas::class)->assertForbidden();
    }

    public function test_la_pagina_monta_el_componente(): void
    {
        $this->autenticarCon(['acceso']);
        $this->withoutVite();

        $this->get('/urdido/configuracion/catalogosmaquinas')->assertOk()->assertSeeLivewire(CatalogoMaquinas::class);
    }

    public function test_alta_edicion_cambio_de_id_y_borrado(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar', 'eliminar']);

        $lw = Livewire::test(CatalogoMaquinas::class)
            ->assertSee('Karl Mayer')
            ->call('abrirAlta')
            ->set('form.MaquinaId', ' MC Coy 1 ')
            ->set('form.Nombre', '')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('aviso');

        $nueva = URDCatalogoMaquina::findOrFail('MC Coy 1');
        $this->assertNull($nueva->Nombre, 'Vacío se guarda como NULL.');

        // Duplicado
        $lw->call('abrirAlta')->set('form.MaquinaId', 'KM-2')->call('guardar')->assertHasErrors(['form.MaquinaId']);

        // Editar sin cambiar el id
        $lw->call('abrirEdicion', 'KM-2')->assertSet('form.Nombre', 'Karl Mayer')
            ->set('form.Nombre', 'KM')->set('form.Codificacion', 'TOW-KMURD-URDI')->call('guardar')->assertHasNoErrors();
        $this->assertSame('KM', URDCatalogoMaquina::find('KM-2')->Nombre);
        $lw->call('abrirEdicion', 'KM-2')->set('form.Codificacion', str_repeat('X', 46))->call('guardar')->assertHasErrors(['form.Codificacion']);
        $lw->call('cerrar');

        // Cambiar el id: se borra el viejo y queda el nuevo
        $lw->call('abrirEdicion', 'KM-2')->set('form.MaquinaId', 'KM-3')->call('guardar')->assertHasNoErrors();
        $this->assertNull(URDCatalogoMaquina::find('KM-2'));
        $this->assertSame('KM', URDCatalogoMaquina::find('KM-3')->Nombre);
        $this->assertSame('TOW-KMURD-URDI', URDCatalogoMaquina::find('KM-3')->Codificacion, 'Cambiar el ID no pierde la codificación.');

        $lw->set('seleccionado', 'KM-3')->call('eliminar');
        $this->assertSame(['MC Coy 1'], URDCatalogoMaquina::pluck('MaquinaId')->all());
    }

    public function test_sin_permiso_de_crear_no_abre_el_alta(): void
    {
        $this->autenticarCon(['acceso']);

        Livewire::test(CatalogoMaquinas::class)->call('abrirAlta')->assertForbidden();
    }

    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Catalogos Maquinas', $acciones, null, CatalogoMaquinas::MODULO);
    }
}
