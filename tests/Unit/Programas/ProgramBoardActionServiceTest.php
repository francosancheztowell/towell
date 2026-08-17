<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Models\Sistema\Usuario;
use App\Services\Programas\ProgramBoardActionService;
use App\Support\Programas\ProgramaModulo;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgramBoardActionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlsrv');

        $this->createProgramTable('UrdProgramaUrdido', 'MaquinaId', true);
        $this->createProgramTable('EngProgramaEngomado', 'MaquinaEng');
        $this->createProductionTable('UrdProduccionUrdido');
        $this->createProductionTable('EngProduccionEngomado');

        $user = new Usuario([
            'idusuario' => 10,
            'numero_empleado' => '100',
            'nombre' => 'Supervisor prueba',
            'puesto' => 'Supervisor Urdido',
        ]);
        $user->exists = true;
        Auth::setUser($user);

        $this->grantModulePermissions(['modificar' => 1]);
    }

    /**
     * userPermissions() memoiza los catálogos de permisos en el contenedor, así que
     * basta con sembrar esas dos instancias para que userCan() responda sin tocar BD.
     *
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

    public function test_priority_can_only_be_swapped_inside_the_same_machine(): void
    {
        $this->insertUrdido(1, 'URD-1', 'Mc Coy 1', 'Programado', 1);
        $this->insertUrdido(2, 'URD-2', 'Mc Coy 1', 'Programado', 2);
        $this->insertUrdido(3, 'URD-3', 'Mc Coy 2', 'Programado', 3);

        $service = app(ProgramBoardActionService::class);
        $service->swapPriorities(ProgramaModulo::Urdido, 1, 2);

        $this->assertSame(2, (int) DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Prioridad'));
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 2)->value('Prioridad'));

        $this->expectException(DomainException::class);
        $service->swapPriorities(ProgramaModulo::Urdido, 1, 3);
    }

    public function test_priority_swap_requires_module_modify_permission(): void
    {
        $this->insertUrdido(1, 'URD-1', 'Mc Coy 1', 'Programado', 1);
        $this->insertUrdido(2, 'URD-2', 'Mc Coy 1', 'Programado', 2);

        $this->grantModulePermissions(['modificar' => 0]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No tienes permiso para cambiar la prioridad.');

        app(ProgramBoardActionService::class)->swapPriorities(ProgramaModulo::Urdido, 1, 2);
    }

    public function test_quality_can_only_be_saved_by_the_quality_area(): void
    {
        $this->insertUrdido(1, 'URD-1', 'Mc Coy 1', 'Programado', 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo el área de Calidad puede evaluar una orden.');

        app(ProgramBoardActionService::class)->saveQuality(
            ProgramaModulo::Urdido,
            1,
            'A',
            'Sin observaciones'
        );
    }

    public function test_cancelling_urdido_removes_related_production_and_cancels_engomado(): void
    {
        $this->insertUrdido(1, 'ORD-100', 'Mc Coy 1', 'En Proceso', 1);
        $this->insertUrdido(2, 'ORD-200', 'Mc Coy 1', 'Programado', 2);
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1,
            'Folio' => 'ORD-100',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Programado',
            'Prioridad' => 1,
            'FechaProg' => '2026-07-29',
        ]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => 'ORD-100']);
        DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert(['Folio' => 'ORD-100']);

        app(ProgramBoardActionService::class)->changeStatus(
            ProgramaModulo::Urdido,
            1,
            'Cancelado'
        );

        $this->assertSame('Cancelado', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
        $this->assertSame('Cancelado', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status'));
        $this->assertSame(0, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('EngProduccionEngomado')->count());
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 2)->value('Prioridad'));
    }

    public function test_engomado_cannot_start_until_urdido_is_finalized(): void
    {
        $this->insertUrdido(1, 'ORD-300', 'Mc Coy 1', 'En Proceso', 1);
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1,
            'Folio' => 'ORD-300',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Programado',
            'Prioridad' => 1,
            'FechaProg' => '2026-07-29',
        ]);

        $reason = app(ProgramBoardActionService::class)->productionBlockReason(
            ProgramaModulo::Engomado,
            1
        );

        $this->assertSame(
            'La orden de Urdido debe estar finalizada antes de iniciar Engomado.',
            $reason
        );
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
}
