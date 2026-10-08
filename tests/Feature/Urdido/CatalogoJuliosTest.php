<?php

declare(strict_types=1);

namespace Tests\Feature\Urdido;

use App\Livewire\Urdido\CatalogoJulios;
use App\Models\Urdido\UrdCatJulios;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CatalogoJuliosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('UrdCatJulios', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('NoJulio')->nullable();
            $t->float('Tara')->nullable();
            $t->string('Departamento')->nullable();
        });
        UrdCatJulios::insert([
            ['NoJulio' => '11', 'Tara' => 120.5, 'Departamento' => 'Urdido'],
            ['NoJulio' => '31', 'Tara' => 90, 'Departamento' => 'Engomado'],
        ]);
    }

    /** @return array<string, array{string, string, int}> */
    public static function pantallas(): array
    {
        return [
            'urdido' => ['/urdido/configuracion/catalogosjulios', 'Urdido', 37],
            'engomado' => ['/engomado/configuracion/catalogojulioseng', 'Engomado', 171],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_cada_pantalla_monta_su_departamento(string $url, string $dep, int $idrol): void
    {
        $this->autenticarCon($idrol, ['acceso']);
        $this->withoutVite();

        $this->get($url)->assertOk()->assertSeeLivewire(CatalogoJulios::class);

        $ajeno = $dep === 'Urdido' ? '31' : '11';
        Livewire::test(CatalogoJulios::class, ['departamento' => $dep])
            ->assertViewHas('filas', fn ($p) => collect($p->items())->pluck('NoJulio')->all() === [$dep === 'Urdido' ? '11' : '31'])
            ->assertDontSee('>'.$ajeno.'<', false);
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CatalogoJulios::class, ['departamento' => 'Urdido'])->assertForbidden();
    }

    public function test_el_permiso_es_por_departamento(): void
    {
        $this->autenticarCon(37, ['acceso', 'crear']);

        Livewire::test(CatalogoJulios::class, ['departamento' => 'Engomado'])->assertForbidden();
    }

    public function test_alta_edicion_y_borrado(): void
    {
        $this->autenticarCon(171, ['acceso', 'crear', 'modificar', 'eliminar']);

        $lw = Livewire::test(CatalogoJulios::class, ['departamento' => 'Engomado'])
            ->call('abrirAlta')->set('form.NoJulio', ' J99 ')->set('form.Tara', '')
            ->call('guardar')->assertHasNoErrors()->assertDispatched('aviso');

        $julio = UrdCatJulios::where('NoJulio', 'J99')->sole();
        $this->assertSame('Engomado', $julio->Departamento);
        $this->assertSame(0.0, $julio->Tara, 'Tara vacía = 0.');

        // No. Julio único en toda la tabla, también contra otro departamento.
        $lw->call('abrirAlta')->set('form.NoJulio', '11')->call('guardar')->assertHasErrors(['form.NoJulio']);
        $lw->call('abrirAlta')->set('form.NoJulio', 'X')->set('form.Tara', 'abc')->call('guardar')->assertHasErrors(['form.Tara']);

        $lw->call('abrirEdicion', (string) $julio->Id)->assertSet('form.NoJulio', 'J99')
            ->set('form.Tara', '7.5')->call('guardar')->assertHasNoErrors();
        $this->assertSame(7.5, $julio->fresh()->Tara);

        $lw->set('seleccionado', (string) $julio->Id)->call('eliminar');
        $this->assertNull($julio->fresh());
    }

    public function test_no_se_edita_un_julio_de_otro_departamento(): void
    {
        $this->autenticarCon(171, ['acceso', 'modificar']);
        $deUrdido = (string) UrdCatJulios::where('NoJulio', '11')->value('Id');

        Livewire::test(CatalogoJulios::class, ['departamento' => 'Engomado'])->call('abrirEdicion', $deUrdido)->assertNotFound();
    }

    private function autenticarCon(int $idrol, array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Catalogo Julios', $acciones, null, $idrol);
    }
}
