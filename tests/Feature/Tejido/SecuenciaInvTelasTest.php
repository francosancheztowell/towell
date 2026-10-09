<?php

namespace Tests\Feature\Tejido;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/** Secuencia Inv Telas sobre URDCatalogoMaquinas.Secuencia (antes InvSecuenciaTelares). */
class SecuenciaInvTelasTest extends TestCase
{
    use ModuloTejido;

    private const URL = '/tejido/secuencia-inv-telas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('MaquinaId', 20)->unique();
            $t->string('Nombre', 60)->nullable();
            $t->string('Departamento', 60)->nullable();
            $t->string('Codificacion', 45)->nullable();
            $t->integer('Secuencia')->nullable();
        });
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => '201', 'Departamento' => 'Jacquard', 'Secuencia' => 2],
            ['MaquinaId' => '300', 'Departamento' => 'Itema', 'Secuencia' => 1],
            ['MaquinaId' => '305', 'Departamento' => 'Smith', 'Secuencia' => null],
            ['MaquinaId' => '401', 'Departamento' => 'Karl Mayer', 'Secuencia' => null],
            ['MaquinaId' => 'MC1', 'Departamento' => 'Urdido', 'Secuencia' => null],
        ]);
    }

    private function usuario(): mixed
    {
        $todas = ['acceso', 'crear', 'modificar', 'eliminar'];

        return $this->usuarioCon([29 => $todas, 'Secuencia Inv Telas' => $todas]);
    }

    private function secuencia(string $telar): ?int
    {
        $v = DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', $telar)->value('Secuencia');

        return $v === null ? null : (int) $v;
    }

    public function test_lista_solo_telares_con_secuencia_en_orden_con_el_tipo_de_antes(): void
    {
        $html = $this->actingAs($this->usuario())->get(self::URL)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>300<.*>ITEMA<.*>201<.*>JACQUARD</s', $html);
        $this->assertStringNotContainsString('>305<', $html);
        $this->assertStringNotContainsString('>MC1<', $html);
    }

    public function test_alta_cambio_y_baja_mueven_la_secuencia_del_telar(): void
    {
        $u = $this->usuario();

        $this->actingAs($u)->postJson(self::URL, ['NoTelar' => 305, 'TipoTelar' => 'SMIT'])->assertOk()->assertJson(['success' => true, 'data' => ['TipoTelar' => 'SMIT', 'Secuencia' => 3]]);
        $this->assertSame(3, $this->secuencia('305'));

        $id = DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', '305')->value('Id');
        $this->actingAs($u)->putJson(self::URL.'/'.$id, ['NoTelar' => 305, 'TipoTelar' => 'SMIT', 'Secuencia' => 7])->assertOk();
        $this->assertSame(7, $this->secuencia('305'));

        // Cambiar el número de telar pasa la secuencia al otro telar.
        $this->actingAs($u)->putJson(self::URL.'/'.$id, ['NoTelar' => 401, 'TipoTelar' => 'KARL MAYER', 'Secuencia' => 8])->assertOk();
        $this->assertNull($this->secuencia('305'));
        $this->assertSame(8, $this->secuencia('401'));

        $id401 = DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', '401')->value('Id');
        $this->actingAs($u)->deleteJson(self::URL.'/'.$id401)->assertOk();
        $this->assertNull($this->secuencia('401'));
        $this->assertTrue(DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', '401')->exists(), 'quitar la secuencia no borra la máquina');
    }

    public function test_telar_que_no_esta_en_el_catalogo_o_no_es_telar(): void
    {
        $u = $this->usuario();

        $this->actingAs($u)->postJson(self::URL, ['NoTelar' => 999])->assertStatus(422);
        $this->actingAs($u)->deleteJson(self::URL.'/999')->assertNotFound();
        $this->assertNull($this->secuencia('MC1'));
    }

    public function test_orden_en_una_sola_sentencia(): void
    {
        $ids = DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->whereIn('MaquinaId', ['201', '300'])->pluck('Id', 'MaquinaId');

        $this->actingAs($this->usuario())->postJson(self::URL.'/orden', ['orden' => [
            ['Id' => $ids['201'], 'Secuencia' => 1],
            ['Id' => $ids['300'], 'Secuencia' => 2],
        ]])->assertOk();

        $this->assertSame(1, $this->secuencia('201'));
        $this->assertSame(2, $this->secuencia('300'));
    }
}
