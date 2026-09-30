<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Sistema\Usuario;
use App\Services\Programas\ProgramBoardActionService;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PERF (19-05 p2.4): consultas de ProgramBoardActionService medidas con DB::enableQueryLog()
 * en sqlite. Cada test deja el número ANTES (código previo) y asserta el DESPUÉS, y comprueba
 * que el resultado en BD sea el mismo.
 */
class ProgramaBoardQueriesTest extends TestCase
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

        $this->tablaPrograma('UrdProgramaUrdido', 'MaquinaId');
        $this->tablaPrograma('EngProgramaEngomado', 'MaquinaEng');
        $this->tablaProduccion('UrdProduccionUrdido');
        $this->tablaProduccion('EngProduccionEngomado');

        app()->instance('permisos.roles', collect([
            'programa urdido' => (object) ['idrol' => 1, 'modulo' => 'Programa Urdido'],
            'programa engomado' => (object) ['idrol' => 2, 'modulo' => 'Programa Engomado'],
        ]));
        $permisos = (object) ['acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 0, 'registrar' => 1];
        app()->instance('permisos.usuario.10', collect([1 => $permisos, 2 => $permisos]));

        $usuario = new Usuario(['idusuario' => 10, 'numero_empleado' => '100', 'nombre' => 'Supervisor', 'puesto' => 'Supervisor Urdido']);
        $usuario->idusuario = 10;
        $usuario->exists = true;
        Auth::setUser($usuario);
    }

    public function test_guardar_salones_agrupa_el_update_por_maquina(): void
    {
        // 30 órdenes activas repartidas en MC Coy 1-3 y 2 que no cuentan (Finalizado / Karl Mayer).
        $filas = [];
        for ($id = 1; $id <= 30; $id++) {
            $this->insertar('UrdProgramaUrdido', $id, 'MaquinaId', 'Mc Coy 1', $id % 2 ? 'Programado' : 'En Proceso', $id);
            $filas[] = ['id' => $id, 'status' => $id % 2 ? 'Programado' : 'En Proceso', 'machine' => 'MC Coy '.(($id % 3) + 1)];
        }
        $this->insertar('UrdProgramaUrdido', 31, 'MaquinaId', 'Mc Coy 1', 'Finalizado', null);
        $this->insertar('UrdProgramaUrdido', 32, 'MaquinaId', 'Karl Mayer', 'Programado', 32);
        $filas[] = ['id' => 31, 'status' => 'Finalizado', 'machine' => 'MC Coy 2'];
        $filas[] = ['id' => 32, 'status' => 'Programado', 'machine' => 'Karl Mayer'];

        $consultas = $this->contarConsultas(
            fn () => app(ProgramBoardActionService::class)->saveUrdidoSalons(ProgramaModulo::Urdido, $filas)
        );

        // ANTES: 30 (un UPDATE por fila válida). DESPUÉS: 3 (un UPDATE ... WHERE Id IN por máquina).
        $this->assertSame(3, $consultas);

        $maquinas = DB::connection('sqlsrv')->table('UrdProgramaUrdido')->orderBy('Id')->pluck('MaquinaId', 'Id')->all();
        for ($id = 1; $id <= 30; $id++) {
            $this->assertSame('MC Coy '.(($id % 3) + 1), $maquinas[$id], "orden {$id}");
        }
        $this->assertSame('Mc Coy 1', $maquinas[31], 'Finalizado no se mueve');
        $this->assertSame('Karl Mayer', $maquinas[32], 'máquina fuera de MC Coy 1-3 no se asigna');
    }

    public function test_cancelar_recalcula_prioridades_en_un_solo_update(): void
    {
        // 25 órdenes activas con huecos y nulos en Prioridad; se cancela la 5.
        for ($id = 1; $id <= 25; $id++) {
            $prioridad = $id % 5 === 0 ? null : $id * 10;
            $this->insertar('UrdProgramaUrdido', $id, 'MaquinaId', 'Mc Coy '.(($id % 3) + 1), 'Programado', $prioridad);
        }
        $this->insertar('UrdProgramaUrdido', 26, 'MaquinaId', 'Mc Coy 1', 'Finalizado', 1);

        $consultas = $this->contarConsultas(
            fn () => app(ProgramBoardActionService::class)->changeStatus(ProgramaModulo::Urdido, 5, 'Cancelado')
        );

        // ANTES: 30 = select de la orden + exists de AX + update de la orden + delete de producción
        // + select de engomado + select de activas + 24 UPDATE (un save() por orden activa).
        // DESPUÉS: 7 (las 24 prioridades en un solo UPDATE ... CASE).
        $this->assertSame(7, $consultas);

        $tabla = DB::connection('sqlsrv')->table('UrdProgramaUrdido');
        $this->assertSame('Cancelado', (clone $tabla)->where('Id', 5)->value('Status'));
        $this->assertNull((clone $tabla)->where('Id', 5)->value('Prioridad'));
        $this->assertSame(1, (int) (clone $tabla)->where('Id', 26)->value('Prioridad'), 'Finalizado no se toca');

        // Mismo orden que antes: primero las que tenían prioridad (10, 20, ...), luego las nulas por Id.
        $esperado = [];
        $conPrioridad = array_values(array_filter(range(1, 25), fn (int $id): bool => $id % 5 !== 0));
        $sinPrioridad = array_values(array_filter(range(1, 25), fn (int $id): bool => $id % 5 === 0 && $id !== 5));
        foreach ([...$conPrioridad, ...$sinPrioridad] as $posicion => $id) {
            $esperado[$id] = $posicion + 1;
        }
        $reales = (clone $tabla)->where('Status', 'Programado')->pluck('Prioridad', 'Id')->map(fn ($p): int => (int) $p)->all();
        ksort($reales);
        ksort($esperado);
        $this->assertSame($esperado, $reales);
    }

    private function contarConsultas(callable $accion): int
    {
        DB::connection('sqlsrv')->flushQueryLog();
        DB::connection('sqlsrv')->enableQueryLog();
        $accion();
        $total = count(DB::connection('sqlsrv')->getQueryLog());
        DB::connection('sqlsrv')->disableQueryLog();

        return $total;
    }

    private function tablaPrograma(string $tabla, string $maquina): void
    {
        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($maquina): void {
            $t->increments('Id');
            $t->string('Folio')->nullable();
            $t->string($maquina)->nullable();
            $t->string('Status')->nullable();
            $t->integer('Prioridad')->nullable();
            $t->date('FechaProg')->nullable();
            $t->dateTime('CreatedAt')->nullable();
        });
    }

    private function tablaProduccion(string $tabla): void
    {
        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('Folio')->nullable();
            $t->integer('AX')->nullable();
        });
    }

    private function insertar(string $tabla, int $id, string $columnaMaquina, string $maquina, string $status, ?int $prioridad): void
    {
        DB::connection('sqlsrv')->table($tabla)->insert([
            'Id' => $id,
            'Folio' => 'F-'.$id,
            $columnaMaquina => $maquina,
            'Status' => $status,
            'Prioridad' => $prioridad,
            'FechaProg' => '2026-09-01',
            'CreatedAt' => '2026-09-01 08:00:00',
        ]);
    }
}
