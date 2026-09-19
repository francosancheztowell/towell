<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgramBoardStatusGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'sqlsrv');
        DB::purge('sqlsrv');

        $this->createProgramTable('UrdProgramaUrdido', 'MaquinaId', true);
        $this->createProgramTable('EngProgramaEngomado', 'MaquinaEng');
        $this->createProductionTable('UrdProduccionUrdido');
        $this->createProductionTable('EngProduccionEngomado');

        $this->grantModulePermissions(['modificar' => 1, 'crear' => 1, 'registrar' => 1, 'acceso' => 1]);
    }

    public function test_default_engomado_and_urdido_boards_render_livewire_program_board(): void
    {
        $user = $this->supervisor();

        $engomado = $this->actingAs($user)->get('/engomado/programar-engomado');
        $engomado->assertOk();
        $engomado->assertSee('program-board-page', false);
        $engomado->assertSee('West Point 2');

        $urdido = $this->actingAs($user)->get('/urdido/programar-urdido');
        $urdido->assertOk();
        $urdido->assertSee('program-board-page', false);
        $urdido->assertSee('MC Coy 1');
    }

    public function test_legacy_boards_redirect_to_the_livewire_default(): void
    {
        $user = $this->supervisor();

        $this->actingAs($user)
            ->get('/engomado/programar-engomado/legacy')
            ->assertRedirect('/engomado/programar-engomado')
            ->assertStatus(301);

        $this->actingAs($user)
            ->get('/urdido/programar-urdido/legacy')
            ->assertRedirect('/urdido/programar-urdido')
            ->assertStatus(301);
    }

    public function test_verificar_en_proceso_endpoints_are_gone(): void
    {
        $user = $this->supervisor();

        $this->actingAs($user)
            ->get('/engomado/programar-engomado/verificar-en-proceso')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/urdido/programar-urdido/verificar-en-proceso')
            ->assertNotFound();
    }

    public function test_authenticated_user_is_redirected_from_urdido_livewire_alias(): void
    {
        $this->actingAs($this->supervisor())
            ->get('/urdido/programar-urdido/livewire')
            ->assertRedirect('/urdido/programar-urdido');
    }

    public function test_engomado_cannot_go_en_proceso_unless_related_urdido_is_finalizado(): void
    {
        $this->insertUrdido(1, 'ORD-300', 'Mc Coy 1', 'En Proceso', 1);
        $this->insertEngomado(1, 'ORD-300', 'West Point 2', 'Programado', 1);

        $response = $this->actingAs($this->supervisor())
            ->postJson('/engomado/programar-engomado/actualizar-status', [
                'id' => 1,
                'status' => 'En Proceso',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'La orden de Urdido debe estar finalizada antes de iniciar Engomado.',
        ]);
        $this->assertSame(
            'Programado',
            DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status')
        );
    }

    public function test_engomado_respects_max_two_en_proceso_per_machine(): void
    {
        $this->insertUrdido(1, 'ORD-401', 'Mc Coy 1', 'Finalizado', 1);
        $this->insertUrdido(2, 'ORD-402', 'Mc Coy 1', 'Finalizado', 2);
        $this->insertUrdido(3, 'ORD-403', 'Mc Coy 1', 'Finalizado', 3);
        $this->insertEngomado(1, 'ORD-401', 'West Point 2', 'En Proceso', 1);
        $this->insertEngomado(2, 'ORD-402', 'West Point 2', 'En Proceso', 2);
        $this->insertEngomado(3, 'ORD-403', 'West Point 2', 'Programado', 3);

        $response = $this->actingAs($this->supervisor())
            ->postJson('/engomado/programar-engomado/actualizar-status', [
                'id' => 3,
                'status' => 'En Proceso',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Ya existen 2 órdenes en proceso en West Point 2. Finaliza una antes de cargar otra.',
        ]);
        $this->assertSame(
            'Programado',
            DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 3)->value('Status')
        );
    }

    public function test_engomado_can_go_en_proceso_when_urdido_is_finalizado_and_machine_has_capacity(): void
    {
        $this->insertUrdido(1, 'ORD-500', 'Mc Coy 1', 'Finalizado', 1);
        $this->insertEngomado(1, 'ORD-500', 'West Point 2', 'Programado', 1);

        $response = $this->actingAs($this->supervisor())
            ->postJson('/engomado/programar-engomado/actualizar-status', [
                'id' => 1,
                'status' => 'En Proceso',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(
            'En Proceso',
            DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status')
        );
    }

    /**
     * @param  array<string, int>  $permissions
     */
    private function grantModulePermissions(array $permissions): void
    {
        app()->instance('permisos.roles', collect([
            'programa urdido' => (object) ['idrol' => 1, 'modulo' => 'Programa Urdido'],
            'programa engomado' => (object) ['idrol' => 2, 'modulo' => 'Programa Engomado'],
        ]));

        app()->instance('permisos.usuario.10', collect([
            1 => (object) $permissions,
            2 => (object) $permissions,
        ]));
    }

    private function supervisor(): Usuario
    {
        $user = new Usuario([
            'idusuario' => 10,
            'numero_empleado' => '100',
            'nombre' => 'Supervisor prueba',
            'puesto' => 'Supervisor Engomado',
        ]);
        $user->idusuario = 10;
        $user->exists = true;

        return $user;
    }

    private function createProgramTable(string $tableName, string $machineColumn, bool $quality = false): void
    {
        Schema::connection('sqlsrv')->create($tableName, function (Blueprint $table) use ($machineColumn, $quality): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('Fibra')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->float('Metros')->nullable();
            $table->string($machineColumn)->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaProg')->nullable();
            $table->integer('Prioridad')->nullable();
            $table->text('Observaciones')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->string('BomFormula')->nullable();
            $table->string('LoteProveedor')->nullable();

            if ($quality) {
                $table->string('Calidad')->nullable();
                $table->string('CalidadComentario')->nullable();
                $table->string('AutorizaCalidad')->nullable();
                $table->dateTime('FechaCalidad')->nullable();
            }
        });
    }

    private function createProductionTable(string $tableName): void
    {
        Schema::connection('sqlsrv')->create($tableName, function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('AX')->nullable();
        });
    }

    private function insertUrdido(
        int $id,
        string $folio,
        string $machine,
        string $status,
        int $priority,
    ): void {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => $id,
            'Folio' => $folio,
            'MaquinaId' => $machine,
            'Status' => $status,
            'Prioridad' => $priority,
            'CreatedAt' => '2026-07-29 08:00:00',
        ]);
    }

    private function insertEngomado(
        int $id,
        string $folio,
        string $machine,
        string $status,
        int $priority,
    ): void {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => $id,
            'Folio' => $folio,
            'MaquinaEng' => $machine,
            'Status' => $status,
            'Prioridad' => $priority,
            'FechaProg' => '2026-07-29',
        ]);
    }
}
