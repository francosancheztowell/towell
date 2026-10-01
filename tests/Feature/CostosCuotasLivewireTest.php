<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Costos\Cuotas;
use App\Models\Costos\CosCuotasReal;
use App\Models\Costos\CosCuotasSTD;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CostosCuotasLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararSqlsrvConUsuarios();

        // Heaps, como en SQL Server: sin llave primaria.
        foreach (['CosCuotasReal' => CosCuotasReal::class, 'CosCuotasSTD' => CosCuotasSTD::class] as $tabla => $modelo) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($modelo) {
                $t->string('Depto', 50)->nullable();
                $t->integer('Año')->nullable();
                $t->integer('Mes')->nullable();
                foreach ($modelo::columnasValor() as $campo) {
                    $t->decimal($campo, 18, 4)->nullable();
                }
            });
        }

        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 1, 'Minutos' => 3000, 'GtosFijos' => 16.6667]);
        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 2, 'Minutos' => 3000]);
        CosCuotasSTD::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 9, 'Minutos' => 3000]);
    }

    private function autenticar(array $acciones = ['acceso', 'crear', 'modificar', 'eliminar']): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo(Cuotas::MODULO, $acciones);
    }

    private function llave(string $modelo, int $mes): string
    {
        return $modelo::where('Mes', $mes)->firstOrFail()->getKey();
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(Cuotas::class)->assertForbidden();
    }

    public function test_cada_pestana_lista_su_tabla(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(Cuotas::class)
            ->assertSet('tabla', 'std')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1)
            ->set('tabla', 'real')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 2)
            ->set('tabla', 'inventada')
            ->assertSet('tabla', 'std');
    }

    public function test_badges_de_anio_filtran_y_cuentan(): void
    {
        $this->autenticar(['acceso']);
        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2027, 'Mes' => 1]);

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->assertViewHas('anios', [2026 => 2, 2027 => 1])
            ->set('anio', '2027')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1)
            ->set('tabla', 'std')
            ->assertSet('anio', '');
    }

    public function test_costeo_directo_oculta_los_fijos(): void
    {
        $this->autenticar(['acceso']);
        $campos = fn ($c) => array_column($c->instance()->columnas(), 'campo');

        $c = Livewire::test(Cuotas::class);
        $this->assertContains('GtosFijos', $campos($c), 'absorbente (default) toma todo');

        $c->set('ordenPor', 'GtosFijos')->set('costeo', 'directo');
        $this->assertSame([], array_intersect($campos($c), ['SabGtosFijos', 'SabProrrateoFijo', 'GtosFijos', 'ProrrateoFijo']));
        $this->assertContains('GtosVariables', $campos($c));
        $c->assertHasNoErrors(); // el orden por una columna oculta se ignora, no truena

        $c->set('costeo', 'otra')->assertSet('costeo', 'absorbente');
    }

    public function test_mes_se_muestra_con_nombre(): void
    {
        $this->autenticar(['acceso']);

        $mes = collect(Livewire::test(Cuotas::class)->instance()->columnas())->firstWhere('campo', 'Mes');

        $this->assertSame('Septiembre', ($mes['valor'])(CosCuotasSTD::firstOrFail()));
        $this->assertSame('Diciembre', ($mes['valor'])(new CosCuotasSTD(['Mes' => 12])));
        $this->assertNull(($mes['valor'])(new CosCuotasSTD(['Mes' => null])));
    }

    public function test_filtros_como_ax_por_nombre_de_mes_rango_y_exclusion(): void
    {
        $this->autenticar(['acceso']);
        foreach ([3, 6, 12] as $mes) {
            CosCuotasReal::create(['Depto' => 'Engomado', 'Año' => 2027, 'Mes' => $mes]);
        }
        // Real: Urdido 2026 ene, feb + Engomado 2027 mar, jun, dic. Columnas: 0 Depto, 1 Año, 2 Mes.
        $meses = fn ($c) => $c->viewData('filas')->getCollection()->map(fn ($f) => $f->Año.'-'.$f->Mes)->sort()->values()->all();

        $c = Livewire::test(Cuotas::class)->set('tabla', 'real');

        $c->set('filtrosColumna', [2 => 'enero, Febrero']);
        $this->assertSame(['2026-1', '2026-2'], $meses($c), 'nombres separados por coma = varios meses');

        $c->set('filtrosColumna', [2 => 'feb..jun']);
        $this->assertSame(['2026-2', '2027-3', '2027-6'], $meses($c), 'rango por nombre');

        $c->set('filtrosColumna', [2 => '!dic', 1 => '2027']);
        $this->assertSame(['2027-3', '2027-6'], $meses($c), 'año exacto y excluir un mes');

        $c->set('filtrosColumna', [1 => '26']);
        $this->assertSame([], $meses($c), 'el año es exacto: 26 no es 2026');

        $c->set('filtrosColumna', [2 => 'nada']);
        $this->assertSame([], $meses($c), 'algo que no se entiende no muestra todo');
    }

    public function test_los_filtros_se_conservan_al_cambiar_de_pestana(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->set('filtrosColumna', [2 => 'septiembre'])
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 0)
            ->set('tabla', 'std')
            ->assertSet('filtrosColumna', [2 => 'septiembre'])
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1);
    }

    public function test_alta_y_llave_repetida(): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->set('tabla', 'std')
            ->call('abrirAlta')
            ->set('form.Depto', 'Engomado')
            ->set('form.Año', '2026')
            ->set('form.Mes', '9')
            ->set('form.SabGtosFijos', '50000.1234')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('editando', null);

        $this->assertEqualsWithDelta(50000.1234, (float) CosCuotasSTD::where('Depto', 'Engomado')->value('SabGtosFijos'), 0.00001);

        // Misma llave: no se guarda otra línea; el diálogo sigue abierto y ofrece reemplazar.
        $c = Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->call('abrirAlta')
            ->set('form.Depto', 'Urdido')
            ->set('form.Año', '2026')
            ->set('form.Mes', '1')
            ->set('form.Minutos', '4200')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('conflicto', $this->llave(CosCuotasReal::class, 1))
            ->assertSet('editando', '');
        $this->assertSame(2, CosCuotasReal::count());

        // Reemplazar = editar la existente.
        $c->call('reemplazar')->assertSet('editando', null)->assertSet('conflicto', null);
        $this->assertSame(2, CosCuotasReal::count());
        $this->assertEquals(4200, CosCuotasReal::where('Mes', 1)->value('Minutos'));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> campo, valor, error esperado */
    public static function capturasInvalidas(): array
    {
        return [
            'año de 6 dígitos' => ['Año', '202939', 'form.Año'],
            'año muy lejano' => ['Año', '2099', 'form.Año'],
            'año con letras' => ['Año', '20a6', 'form.Año'],
            'depto con símbolos' => ['Depto', 'Urdido<script>', 'form.Depto'],
            'depto de 1 letra' => ['Depto', 'U', 'form.Depto'],
            'valor con letras' => ['GtosFijos', '12abc', 'form.GtosFijos'],
            'valor negativo' => ['GtosFijos', '-5', 'form.GtosFijos'],
            '5 decimales' => ['GtosFijos', '1.23456', 'form.GtosFijos'],
            'más minutos que un mes' => ['Minutos', '50000', 'form.Minutos'],
        ];
    }

    #[DataProvider('capturasInvalidas')]
    public function test_el_esquema_rechaza_capturas_invalidas(string $campo, string $valor, string $error): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->call('abrirAlta')
            ->set('form.Depto', 'Tejido')
            ->set('form.Año', '2026')
            ->set('form.Mes', '3')
            ->set('form.Minutos', '3000')
            ->set("form.{$campo}", $valor)
            ->call('guardar')
            ->assertHasErrors($error)
            ->assertSet('editando', '');

        $this->assertSame(1, CosCuotasSTD::count(), 'no se guardó nada');
    }

    public function test_min_paro_no_mayor_que_minutos_y_al_menos_un_valor(): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->call('abrirAlta')
            ->set('form.Depto', 'Tejido')
            ->set('form.Minutos', '100')
            ->set('form.MinParo', '150')
            ->call('guardar')
            ->assertHasErrors('form.MinParo');

        Livewire::test(Cuotas::class)
            ->call('abrirAlta')
            ->set('form.Depto', 'Tejido')
            ->call('guardar')
            ->assertHasErrors('form.Minutos');

        // Espacios de sobra se limpian y el resto pasa.
        Livewire::test(Cuotas::class)
            ->call('abrirAlta')
            ->set('form.Depto', '  Tejido   Plano ')
            ->set('form.Minutos', '100')
            ->set('form.MinParo', '100')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertTrue(CosCuotasSTD::where('Depto', 'Tejido Plano')->exists());
    }

    public function test_cambiar_la_llave_despues_del_aviso_lo_quita(): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->call('abrirAlta')
            ->set('form.Depto', 'Urdido')
            ->set('form.Año', '2026')
            ->set('form.Mes', '1')
            ->set('form.Minutos', '3000')
            ->call('guardar')
            ->assertSet('conflicto', fn ($v) => $v !== null)
            ->set('form.Mes', '7')
            ->assertSet('conflicto', null)
            ->call('guardar')
            ->assertSet('editando', null);

        $this->assertSame(3, CosCuotasReal::count());
    }

    public function test_editar_sobre_otra_llave_y_reemplazar_no_deja_dos(): void
    {
        $this->autenticar();

        // Se edita febrero y se le pone enero (ocupado): reemplazar deja una sola fila de enero.
        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->call('abrirEdicion', $this->llave(CosCuotasReal::class, 2))
            ->set('form.Mes', '1')
            ->set('form.Minutos', '999')
            ->call('guardar')
            ->assertSet('conflicto', $this->llave(CosCuotasReal::class, 1))
            ->call('reemplazar')
            ->assertSet('editando', null);

        $this->assertSame([1], CosCuotasReal::pluck('Mes')->all());
        $this->assertEquals(999, CosCuotasReal::value('Minutos'));
    }

    public function test_reemplazar_pide_permiso_de_modificar(): void
    {
        $this->autenticar(['acceso', 'crear']);

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->call('abrirAlta')
            ->set('form.Depto', 'Urdido')
            ->set('form.Año', '2026')
            ->set('form.Mes', '1')
            ->call('guardar')
            ->call('reemplazar')
            ->assertForbidden();
    }

    public function test_duplicar_propone_el_mes_siguiente_y_crea_otra_fila(): void
    {
        $this->autenticar();
        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 12, 'Minutos' => 1500]);

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->set('seleccionado', $this->llave(CosCuotasReal::class, 12))
            ->call('duplicar')
            ->assertSet('duplicando', true)
            ->assertSet('editando', '')
            ->assertSet('form.Año', '2027')
            ->assertSet('form.Mes', '1')
            ->assertSet('form.Minutos', '1500')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('duplicando', false);

        $this->assertSame(4, CosCuotasReal::count());
        $this->assertEquals(1500, CosCuotasReal::where('Año', 2027)->where('Mes', 1)->value('Minutos'));
    }

    public function test_sin_permiso_de_crear_no_duplica(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(Cuotas::class)
            ->set('seleccionado', $this->llave(CosCuotasSTD::class, 9))
            ->call('duplicar')
            ->assertForbidden();
    }

    public function test_editar_cambia_solo_su_fila_incluida_la_llave(): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->call('abrirEdicion', $this->llave(CosCuotasReal::class, 1))
            ->assertSet('form.GtosFijos', '16.6667')
            ->set('form.Mes', '3')
            ->set('form.Minutos', '2500')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, CosCuotasReal::count());
        $this->assertEquals(2500, CosCuotasReal::where('Mes', 3)->value('Minutos'));
        $this->assertEquals(3000, CosCuotasReal::where('Mes', 2)->value('Minutos'), 'la otra fila no se toca');
    }

    public function test_sin_permiso_de_eliminar_no_borra(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->set('seleccionado', $this->llave(CosCuotasReal::class, 1))
            ->call('eliminar')
            ->assertForbidden();

        $this->assertSame(2, CosCuotasReal::count());
    }

    public function test_eliminar_borra_solo_la_seleccionada(): void
    {
        $this->autenticar();

        Livewire::test(Cuotas::class)
            ->set('tabla', 'real')
            ->set('seleccionado', $this->llave(CosCuotasReal::class, 1))
            ->call('eliminar')
            ->assertSet('seleccionado', null);

        $this->assertSame([2], CosCuotasReal::pluck('Mes')->all());
    }

    public function test_una_llave_invalida_no_encuentra_nada(): void
    {
        $this->assertSame(0, CosCuotasReal::clave('no-es-una-llave')->count());
    }
}
