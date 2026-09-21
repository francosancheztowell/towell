<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\InventarioTrama\ConsultarRequerimiento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ConsultarRequerimientoLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createAuthTable();

        $schema = Schema::connection(config('database.default'));

        $schema->create('TejTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->unique();
            $table->date('Fecha')->nullable();
            $table->string('Status')->nullable();
            $table->string('Turno')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
        });

        $schema->create('TejTramaConsumos', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('FibraTrama')->nullable();
            $table->string('CodColorTrama')->nullable();
            $table->string('ColorTrama')->nullable();
            $table->float('Cantidad')->nullable();
        });

        DB::listen(function () {
            $this->queries++;
        });

        $this->actingAs($this->createUsuario(), 'web');
    }

    private function sembrar(int $folios = 3): void
    {
        for ($i = 1; $i <= $folios; $i++) {
            $folio = 'TR'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            DB::table('TejTrama')->insert([
                'Folio' => $folio,
                'Fecha' => sprintf('2026-01-%02d', (($i - 1) % 28) + 1),
                'Status' => $i === $folios ? 'En Proceso' : 'Solicitado',
                'Turno' => '1',
                'numero_empleado' => '1001',
            ]);
            DB::table('TejTramaConsumos')->insert([
                ['Folio' => $folio, 'NoTelarId' => '201', 'CalibreTrama' => 20.5, 'FibraTrama' => 'FIL', 'CodColorTrama' => 'C1', 'ColorTrama' => 'Rojo', 'Cantidad' => 4],
                ['Folio' => $folio, 'NoTelarId' => '202', 'CalibreTrama' => 30.0, 'FibraTrama' => 'FIL2', 'CodColorTrama' => 'C2', 'ColorTrama' => 'Azul', 'Cantidad' => 7],
            ]);
        }
    }

    public function test_lista_los_folios_y_selecciona_el_mas_reciente(): void
    {
        $this->sembrar(3);

        Livewire::test(ConsultarRequerimiento::class)
            ->assertSee('TR00003')
            ->assertSee('TR00001')
            ->assertSet('folioSeleccionado', 'TR00003')
            ->assertSet('statusSeleccionado', 'En Proceso');
    }

    public function test_seleccionar_carga_los_detalles_y_cambia_la_seleccion(): void
    {
        $this->sembrar(3);

        $componente = Livewire::test(ConsultarRequerimiento::class)->call('seleccionar', 'TR00001');

        $componente->assertSet('folioSeleccionado', 'TR00001')
            ->assertSet('statusSeleccionado', 'Solicitado');

        $this->assertCount(2, $componente->get('detalles'));
    }

    public function test_cambiar_status_a_cancelado_actualiza_y_avisa(): void
    {
        $this->sembrar(3);

        Livewire::test(ConsultarRequerimiento::class)
            ->call('seleccionar', 'TR00003')
            ->call('cambiarStatus', 'Cancelado')
            ->assertDispatched('aviso', tipo: 'success')
            ->assertSet('statusSeleccionado', 'Cancelado');

        $this->assertSame('Cancelado', DB::table('TejTrama')->where('Folio', 'TR00003')->value('Status'));
    }

    public function test_pagina_de_10_en_10_con_top_y_carga_mas(): void
    {
        $this->sembrar(25);

        $componente = Livewire::test(ConsultarRequerimiento::class);

        $this->assertCount(10, $componente->get('folios'));
        $this->assertTrue($componente->get('hayMas'));

        $componente->call('cargarMas');
        $this->assertCount(20, $componente->get('folios'));
        $this->assertTrue($componente->get('hayMas'));

        $componente->call('cargarMas');
        $this->assertCount(25, $componente->get('folios'));
        $this->assertFalse($componente->get('hayMas'));
    }

    public function test_no_carga_consumos_de_todos_los_folios(): void
    {
        $this->sembrar(40);

        $this->queries = 0;
        Livewire::test(ConsultarRequerimiento::class);

        $this->assertLessThanOrEqual(6, $this->queries, "El render ejecutó {$this->queries} consultas; no debe escalar con el número de folios");
    }
}
