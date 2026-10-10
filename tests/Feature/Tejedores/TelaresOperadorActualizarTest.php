<?php

namespace Tests\Feature\Tejedores;

use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelTelaresOperador;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Editar los telares de un operador guarda el salón del catálogo (URDCatalogoMaquinas, antes ReqTelares). */
class TelaresOperadorActualizarTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(TelTelaresOperador::class);
        $this->tablaDe(SYSUsuario::class);
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
            ['MaquinaId' => '300', 'Departamento' => 'Itema'],
            ['MaquinaId' => '305', 'Departamento' => 'Smith'],
            ['MaquinaId' => '401', 'Departamento' => 'Karl Mayer'],
            ['MaquinaId' => 'MC1', 'Departamento' => 'Urdido'],
        ]);
        $db->table('SYSUsuario')->insert(['idusuario' => 50, 'numero_empleado' => '1824', 'nombre' => 'Operador prueba', 'turno' => 1]);
    }

    /** @param  array<int, string>  $telares */
    private function actualizar(array $telares): mixed
    {
        $id = DB::connection('sqlsrv')->table('TelTelaresOperador')->insertGetId([
            'numero_empleado' => '1824', 'nombreEmpl' => 'Operador prueba', 'NoTelarId' => '201', 'SalonTejidoId' => 'Jacquard', 'Turno' => '1',
        ]);
        $usuario = $this->usuarioCon([143 => ['acceso', 'modificar']], 'Tejido');

        return $this->actingAs($usuario)->putJson("/tel-telares-operador/{$id}", ['numero_empleado' => '1824', 'telares' => $telares]);
    }

    /** @return array<string, string> NoTelarId => SalonTejidoId */
    private function asignados(): array
    {
        return DB::connection('sqlsrv')->table('TelTelaresOperador')->where('numero_empleado', '1824')
            ->pluck('SalonTejidoId', 'NoTelarId')->all();
    }

    public function test_guarda_el_salon_de_cada_telar_como_lo_daba_req_telares(): void
    {
        $this->actualizar(['201', '300', '305', '401'])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['201' => 'Jacquard', '300' => 'Smith', '305' => 'Smith', '401' => 'KM'], $this->asignados());
    }

    public function test_rechaza_telares_que_no_son_del_catalogo_de_telares(): void
    {
        // 212 ya no existe y MC1 es una urdidora: ninguno es telar del catálogo.
        $this->actualizar(['201', '212', 'MC1'])->assertStatus(422)->assertJsonFragment(['success' => false]);

        $this->assertSame(['201' => 'Jacquard'], $this->asignados());
    }
}
