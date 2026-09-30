<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Endpoints JSON de Programar Urdido/Engomado (19-05 p2.3):
 *  - SEC-07: un 500 no lleva el texto de la excepción, sí `trace_id`; el contrato `error` sigue.
 *  - 20-03 (auditar, sin enforce): actualizar-prioridades de Engomado está "habilitado para
 *    todos", así que la entrada se valida entera (422).
 */
class ProgramaBoardEndpointsTest extends TestCase
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

        app()->instance('permisos.roles', collect([
            'programa urdido' => (object) ['idrol' => 1, 'modulo' => 'Programa Urdido'],
            'programa engomado' => (object) ['idrol' => 2, 'modulo' => 'Programa Engomado'],
        ]));
        $permisos = (object) ['acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 0, 'registrar' => 1];
        app()->instance('permisos.usuario.10', collect([1 => $permisos, 2 => $permisos]));

        $usuario = new Usuario(['idusuario' => 10, 'numero_empleado' => '100', 'nombre' => 'Supervisor', 'puesto' => 'Supervisor Engomado']);
        $usuario->idusuario = 10;
        $usuario->exists = true;
        $this->actingAs($usuario);
    }

    public function test_un_500_no_muestra_el_texto_de_la_excepcion(): void
    {
        // Sin tablas: la consulta revienta con "no such table: ..." (SQL incluido).
        foreach (['/urdido/programar-urdido/ordenes', '/urdido/programar-urdido/todas-ordenes', '/engomado/programar-engomado/ordenes', '/engomado/programar-engomado/todas-ordenes'] as $url) {
            $respuesta = $this->getJson($url);

            $respuesta->assertStatus(500)
                ->assertJson(['success' => false, 'message' => 'Error al obtener órdenes.', 'error' => 'Error al obtener órdenes.'])
                ->assertJsonStructure(['trace_id']);
            $this->assertStringNotContainsString('no such table', $respuesta->getContent(), $url);
            $this->assertStringNotContainsString('select', strtolower($respuesta->getContent()), $url);
        }
    }

    public function test_un_500_al_guardar_tampoco_filtra_la_excepcion(): void
    {
        $this->tablaEngomado();
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert(['Id' => 1, 'Folio' => 'E-1', 'MaquinaEng' => 'West Point 2', 'Status' => 'Programado']);
        // La columna Observaciones no existe: el save() falla con SQL.
        $respuesta = $this->postJson('/engomado/programar-engomado/guardar-observaciones', ['id' => 1, 'observaciones' => 'x']);

        $respuesta->assertStatus(500)->assertJson(['success' => false, 'error' => 'Error al guardar observaciones.']);
        $this->assertStringNotContainsString('Observaciones', (string) $respuesta->json('message'));
        $this->assertStringNotContainsString('update', strtolower($respuesta->getContent()));
    }

    public function test_validacion_conserva_error_y_agrega_errors(): void
    {
        $this->tablaEngomado();

        $this->postJson('/engomado/programar-engomado/actualizar-status', ['id' => 'x', 'status' => 'Nada'])
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['error', 'message', 'errors' => ['id', 'status'], 'trace_id']);
    }

    public function test_actualizar_prioridades_engomado_valida_la_entrada(): void
    {
        $this->tablaEngomado();
        foreach ([1, 2, 3] as $id) {
            DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert(['Id' => $id, 'Folio' => 'E-'.$id, 'Status' => 'Programado', 'Prioridad' => $id]);
        }
        $url = '/engomado/programar-engomado/actualizar-prioridades';

        $invalidos = [
            'sin lista' => [],
            'lista vacía' => ['prioridades' => []],
            'no es lista de filas' => ['prioridades' => ['1', '2']],
            'id no entero' => ['prioridades' => [['id' => 'abc', 'prioridad' => 1]]],
            'id cero' => ['prioridades' => [['id' => 0, 'prioridad' => 1]]],
            'prioridad cero' => ['prioridades' => [['id' => 1, 'prioridad' => 0]]],
            'prioridad enorme' => ['prioridades' => [['id' => 1, 'prioridad' => 999999]]],
            'id repetido' => ['prioridades' => [['id' => 1, 'prioridad' => 1], ['id' => 1, 'prioridad' => 2]]],
            'prioridad repetida' => ['prioridades' => [['id' => 1, 'prioridad' => 1], ['id' => 2, 'prioridad' => 1]]],
            'id inexistente' => ['prioridades' => [['id' => 1, 'prioridad' => 2], ['id' => 99, 'prioridad' => 1]]],
        ];
        foreach ($invalidos as $payload) {
            $this->postJson($url, $payload)->assertStatus(422)->assertJson(['success' => false]);
        }
        $this->assertSame([1 => 1, 2 => 2, 3 => 3], $this->prioridades(), 'nada cambió con entradas inválidas');

        $this->postJson($url, ['prioridades' => [['id' => 3, 'prioridad' => 1], ['id' => 1, 'prioridad' => 2], ['id' => 2, 'prioridad' => 3]]])
            ->assertOk()
            ->assertJson(['success' => true]);
        $this->assertSame([1 => 2, 2 => 3, 3 => 1], $this->prioridades());
    }

    /** @return array<int, int> */
    private function prioridades(): array
    {
        return DB::connection('sqlsrv')->table('EngProgramaEngomado')->orderBy('Id')->pluck('Prioridad', 'Id')
            ->map(fn ($p): int => (int) $p)->all();
    }

    private function tablaEngomado(): void
    {
        Schema::connection('sqlsrv')->create('EngProgramaEngomado', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('Folio')->nullable();
            $t->string('MaquinaEng')->nullable();
            $t->string('Status')->nullable();
            $t->integer('Prioridad')->nullable();
            $t->date('FechaProg')->nullable();
        });
    }
}
