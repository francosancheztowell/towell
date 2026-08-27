<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Engomado\ProgramaEngomado\ProgramarEngomadoController;
use App\Models\Sistema\Usuario;
use App\Support\Programas\ProgramaConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgramarEngomadoActualizarStatusAxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'sqlsrv');
        DB::purge('sqlsrv');

        Schema::connection('sqlsrv')->create('EngProgramaEngomado', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('Status')->nullable();
            $table->integer('Prioridad')->nullable();
        });

        Schema::connection('sqlsrv')->create('EngProduccionEngomado', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('AX')->nullable();
        });

        $user = new Usuario([
            'idusuario' => 10,
            'numero_empleado' => '100',
            'nombre' => 'Supervisor prueba',
            'puesto' => 'Supervisor Engomado',
        ]);
        $user->exists = true;
        Auth::setUser($user);
    }

    public function test_no_permite_cancelado_programado_ni_en_proceso_si_el_folio_tiene_ax(): void
    {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1,
            'Folio' => '00088',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Parcial',
            'Prioridad' => 1,
        ]);
        DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert([
            'Folio' => '00088',
            'AX' => 1,
        ]);

        $controller = app(ProgramarEngomadoController::class);

        foreach (['Cancelado', 'Programado', 'En Proceso'] as $status) {
            $response = $controller->actualizarStatus(Request::create(
                '/engomado/programar-engomado/actualizar-status',
                'POST',
                ['id' => 1, 'status' => $status]
            ));

            $this->assertSame(422, $response->getStatusCode(), $status);
            $payload = $response->getData(true);
            $this->assertFalse($payload['success']);
            $this->assertSame(
                ProgramaConfig::mensajeAxBloqueaEstatus('EngProduccionEngomado'),
                $payload['error']
            );
        }

        $this->assertSame('Parcial', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status'));
        $this->assertSame(1, DB::connection('sqlsrv')->table('EngProduccionEngomado')->count());
    }

    public function test_permite_cambiar_estatus_si_ax_no_esta_en_1(): void
    {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1,
            'Folio' => '00089',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Parcial',
            'Prioridad' => 1,
        ]);
        DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert([
            'Folio' => '00089',
            'AX' => 0,
        ]);

        $response = app(ProgramarEngomadoController::class)->actualizarStatus(Request::create(
            '/engomado/programar-engomado/actualizar-status',
            'POST',
            ['id' => 1, 'status' => 'Programado']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Programado', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status'));
    }
}
