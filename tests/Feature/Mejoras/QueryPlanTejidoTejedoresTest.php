<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Http\Controllers\Tejedores\NotificarMontadoRollo\NotificarMontRollosController;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelMarbeteLiberadoModel;
use App\Models\Tejedores\TelTelaresOperador;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-tejido-tejedores-atadores (Tejedores):
 *  - Telares por operador: los ya asignados se leen en una consulta (antes una por telar).
 *  - Notificar montado de rollo: los marbetes liberados se buscan solo para la lista, no la tabla.
 */
class QueryPlanTejidoTejedoresTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(TelTelaresOperador::class);
        $this->tablaDe(SYSUsuario::class);
        $this->tablaDe(ReqProgramaTejido::class);
        $this->tablaDe(TelMarbeteLiberadoModel::class);
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('MaquinaId', 20)->unique();
            $t->string('Nombre', 60)->nullable();
            $t->string('Departamento', 60)->nullable();
            $t->string('Codificacion', 45)->nullable();
            $t->integer('Secuencia')->nullable();
        });

        $db = DB::connection('sqlsrv');
        $db->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => '201', 'Departamento' => 'Jacquard'],
            ['MaquinaId' => '202', 'Departamento' => 'Jacquard'],
            ['MaquinaId' => '300', 'Departamento' => 'Itema'],
        ]);
        $db->table('SYSUsuario')->insert(['idusuario' => 50, 'numero_empleado' => '1824', 'nombre' => 'Operador prueba', 'turno' => 1]);
    }

    /** @param  array<int, string>  $telares */
    private function alta(array $telares): mixed
    {
        $usuario = $this->usuarioCon([143 => ['acceso', 'crear']], 'Tejido');

        return $this->actingAs($usuario)->postJson('/tel-telares-operador', [
            'numero_empleado' => '1824', 'nombreEmpl' => 'Operador prueba', 'Turno' => '1', 'SalonTejidoId' => 'JACQUARD', 'telares' => $telares,
        ]);
    }

    /** @return array<int, string> */
    private function telaresDe(string $empleado): array
    {
        return DB::connection('sqlsrv')->table('TelTelaresOperador')->where('numero_empleado', $empleado)
            ->orderBy('NoTelarId')->pluck('NoTelarId')->map(fn ($t) => (string) $t)->all();
    }

    public function test_alta_no_duplica_telares_ya_asignados_al_mismo_operador(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('TelTelaresOperador')->insert([
            ['numero_empleado' => '1824', 'nombreEmpl' => 'Operador prueba', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Turno' => '1'],
            // El mismo telar de otro operador no cuenta como duplicado.
            ['numero_empleado' => '9999', 'nombreEmpl' => 'Otro', 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Turno' => '1'],
        ]);

        $json = $this->alta(['201', '202', ' 300 '])->assertOk()->json();

        $this->assertTrue($json['success']);
        $this->assertSame(2, $json['creados']);
        $this->assertSame(1, $json['duplicados']);
        $this->assertStringContainsString('Los telares 201 ya estaban asignados.', $json['message']);
        $this->assertSame(['201', '202', '300'], $this->telaresDe('1824'));
        $this->assertSame(['202', '300'], collect($json['data'])->pluck('NoTelarId')->map(fn ($t) => (string) $t)->sort()->values()->all());
    }

    public function test_alta_lee_los_asignados_en_una_sola_consulta(): void
    {
        $queries = [];
        DB::connection('sqlsrv')->listen(function ($q) use (&$queries): void {
            if (stripos($q->sql, 'TelTelaresOperador') !== false && stripos(ltrim($q->sql), 'select') === 0) {
                $queries[] = $q->sql;
            }
        });

        $this->alta(['201', '202', '300'])->assertOk()->assertJson(['creados' => 3, 'duplicados' => 0]);

        // Una para los ya asignados + una para devolver los creados (no una por telar).
        $this->assertCount(2, $queries, implode("\n", $queries));
    }

    /** @param  array<int, array<string, mixed>>  $marbetes */
    private function insertarMarbetes(array $marbetes): array
    {
        $request = Request::create('/tejedores/cortadoderollo/insertar', 'POST', ['marbetes' => $marbetes]);

        return app(NotificarMontRollosController::class)->insertarMarbetes($request)->getData(true);
    }

    private function marbete(string $codigo): array
    {
        return [
            'PurchBarCode' => $codigo, 'ItemId' => 'IT1', 'InventSizeId' => 'S1', 'InventBatchId' => '36160',
            'WMSLocationId' => '201', 'QtySched' => 10, 'Salon' => 'JACQUARD', 'CUANTAS' => 1,
        ];
    }

    /**
     * Un marbete ya liberado (aunque llegue con espacios al final o en minúsculas, que SQL Server
     * con CI_AS considera iguales) no se vuelve a insertar, y uno repetido en la misma petición
     * se inserta una sola vez.
     */
    public function test_insertar_marbetes_no_duplica_liberados_ni_repetidos_en_la_peticion(): void
    {
        DB::connection('sqlsrv')->table('TelMarbeteLiberado')->insert(['PurchBarCode' => 'MB0001', 'ItemId' => 'IT1']);

        $json = $this->insertarMarbetes([
            $this->marbete('MB0001'),
            $this->marbete('mb0001 '),
            $this->marbete('MB0002'),
            $this->marbete('MB0002'),
        ]);

        $this->assertTrue($json['success']);
        $this->assertSame(1, $json['insertados']);
        $this->assertSame(3, $json['yaExistian']);
        $this->assertSame([], $json['errores']);
        $this->assertSame(['MB0001', 'MB0002'], DB::connection('sqlsrv')->table('TelMarbeteLiberado')->orderBy('PurchBarCode')->pluck('PurchBarCode')->all());
    }

    public function test_insertar_marbetes_busca_liberados_solo_de_la_lista(): void
    {
        DB::connection('sqlsrv')->table('TelMarbeteLiberado')->insert([
            ['PurchBarCode' => 'VIEJO1', 'ItemId' => 'IT1'],
            ['PurchBarCode' => 'VIEJO2', 'ItemId' => 'IT1'],
        ]);
        $selects = [];
        DB::connection('sqlsrv')->listen(function ($q) use (&$selects): void {
            if (stripos(ltrim($q->sql), 'select') === 0 && stripos($q->sql, 'TelMarbeteLiberado') !== false) {
                $selects[] = $q;
            }
        });

        $json = $this->insertarMarbetes([$this->marbete('N1'), $this->marbete('N2')]);

        $this->assertSame(2, $json['insertados']);
        $this->assertCount(1, $selects, 'una sola consulta de liberados para toda la lista');
        $this->assertStringContainsString(' in ', strtolower($selects[0]->sql));
        $this->assertSame(['N1', 'N2'], $selects[0]->bindings);
    }

    /** Lista vacía: 400 como antes, sin consultar la tabla. */
    public function test_insertar_marbetes_sin_lista_responde_400(): void
    {
        $request = Request::create('/x', 'POST', ['marbetes' => []]);
        $respuesta = app(NotificarMontRollosController::class)->insertarMarbetes($request);

        $this->assertSame(400, $respuesta->getStatusCode());
    }
}
