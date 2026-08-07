<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Mantenimiento\CatalogoFallas;
use App\Models\Mantenimiento\CatParosFallas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CatalogoFallasLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const MODULO = 'Catalogo de Fallas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        $schema = Schema::connection('sqlsrv');

        $schema->create('CatParosFallas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TipoFallaId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('Abreviado')->nullable();
            $table->string('Seccion')->nullable();
        });

        $schema->create('CatTipoFalla', function (Blueprint $table) {
            $table->string('TipoFallaId')->primary();
        });
    }

    public function test_sin_permiso_de_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CatalogoFallas::class)->assertForbidden();
    }

    public function test_busca_ordena_y_pagina_sobre_la_consulta(): void
    {
        $this->autenticarCon(['acceso']);

        foreach (range(1, 30) as $i) {
            CatParosFallas::create([
                'TipoFallaId' => $i % 2 === 0 ? 'MECANICO' : 'ELECTRICO',
                'Departamento' => 'Tejido',
                'Falla' => sprintf('Falla %02d', $i),
                'Abreviado' => 'F'.$i,
            ]);
        }
        CatParosFallas::create([
            'TipoFallaId' => 'MECANICO',
            'Departamento' => 'Urdido',
            'Falla' => 'Fuga de aire',
            'Abreviado' => 'FA',
        ]);

        Livewire::test(CatalogoFallas::class)
            // Pagina: 31 registros, 25 por página.
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 31 && $filas->count() === 25)
            // Busca por cualquiera de las columnas declaradas.
            ->set('buscar', 'Fuga')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1)
            ->assertSee('Fuga de aire')
            // Filtra por catálogo.
            ->set('buscar', '')
            ->set('tipoFallaFiltro', 'ELECTRICO')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 15)
            // Ordena y alterna dirección.
            ->set('tipoFallaFiltro', '')
            ->call('ordenar', 'Falla')
            ->assertSet('ordenDir', 'asc')
            ->assertViewHas('filas', fn ($filas) => $filas->first()->Falla === 'Falla 01')
            ->call('ordenar', 'Falla')
            ->assertSet('ordenDir', 'desc')
            ->assertViewHas('filas', fn ($filas) => $filas->first()->Falla === 'Fuga de aire');
    }

    public function test_el_orden_solo_acepta_columnas_declaradas(): void
    {
        $this->autenticarCon(['acceso']);

        Livewire::test(CatalogoFallas::class)
            ->call('ordenar', '(select 1)')
            ->assertSet('ordenPor', '');
    }

    public function test_crea_edita_y_elimina_con_permiso(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar', 'eliminar']);

        $componente = Livewire::test(CatalogoFallas::class)
            ->call('abrirAlta')
            ->set('form.TipoFallaId', 'MECANICO')
            ->set('form.Departamento', 'Tejido')
            ->set('form.Falla', 'Rotura de trama')
            ->call('guardar')
            ->assertSet('editando', null)
            ->assertDispatched('aviso');

        $falla = CatParosFallas::firstOrFail();
        $this->assertSame('Rotura de trama', $falla->Falla);

        $componente
            ->call('abrirEdicion', (string) $falla->Id)
            ->set('form.Falla', 'Rotura de pie')
            ->call('guardar');

        $this->assertSame('Rotura de pie', $falla->fresh()->Falla);

        // Tras editar, la fila queda seleccionada (clic sobre la misma fila la deselecciona).
        $componente
            ->call('confirmarBorrado')
            ->assertSet('confirmandoBorrado', true)
            ->call('eliminar')
            ->assertSet('seleccionado', null);

        $this->assertSame(0, CatParosFallas::count());
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        Livewire::test(CatalogoFallas::class)
            ->call('abrirAlta')
            ->call('guardar')
            ->assertHasErrors(['form.TipoFallaId', 'form.Departamento', 'form.Falla']);

        $this->assertSame(0, CatParosFallas::count());
    }

    public function test_sin_permiso_de_eliminar_no_borra(): void
    {
        $this->autenticarCon(['acceso']);

        $falla = CatParosFallas::create([
            'TipoFallaId' => 'MECANICO',
            'Departamento' => 'Tejido',
            'Falla' => 'Rotura de trama',
        ]);

        Livewire::test(CatalogoFallas::class)
            ->call('seleccionar', (string) $falla->Id)
            ->call('eliminar')
            ->assertForbidden();

        $this->assertSame(1, CatParosFallas::count());
    }

    /**
     * @param  array<int, string>  $acciones
     */
    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo(self::MODULO, $acciones);
    }
}
