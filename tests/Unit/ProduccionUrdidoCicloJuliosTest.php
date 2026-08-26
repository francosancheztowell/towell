<?php

namespace Tests\Unit;

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\EditarOrdenesProgramadasController;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Ciclo completo del piso: editar el plan de julios (quitar / sumar), entrar a
 * produccion, salir, volver a entrar y finalizar. Lo que se vigila es que NO
 * aparezcan mas renglones de los que el plan pide.
 */
class ProduccionUrdidoCicloJuliosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');

        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Status')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->float('Metros')->nullable();
            $table->string('TipoAtado')->nullable();
            $table->date('FechaFinaliza')->nullable();
            $table->integer('Incorrecto')->nullable();
            $table->integer('ax')->nullable();
        });

        // finalizar() la consulta para resolver el destino (Karl Mayer)
        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });

        $schema->create('UrdJuliosOrden', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->integer('Hilos')->nullable();
        });

        $schema->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('HoraInicial')->nullable();
            $table->string('HoraFinal')->nullable();
            $table->string('NoJulio')->nullable();
            $table->integer('Hilos')->nullable();
            $table->float('KgBruto')->nullable();
            $table->float('Tara')->nullable();
            $table->float('KgNeto')->nullable();
            $table->integer('Hilatura')->nullable();
            $table->integer('Maquina')->nullable();
            $table->integer('Operac')->nullable();
            $table->integer('Transf')->nullable();
            $table->float('Vueltas')->nullable();
            $table->float('Diametro')->nullable();
            $table->string('TipoAtado')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->integer('Turno1')->nullable();
            $table->float('Metros2')->nullable();
            $table->float('Metros3')->nullable();
            $table->integer('Finalizar')->nullable();
            $table->integer('AX')->nullable();
        });

        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => 'C001', 'Status' => 'En Proceso',
            'MaquinaId' => 'Mc Coy 2', 'Metros' => 6000, 'Incorrecto' => 0, 'ax' => 0,
        ]);
    }

    // ── helpers del ciclo ────────────────────────────────────────────

    private function plan(int $julios, int $hilos): int
    {
        return DB::connection('sqlsrv')->table('UrdJuliosOrden')
            ->insertGetId(['Folio' => 'C001', 'Julios' => $julios, 'Hilos' => $hilos]);
    }

    /** "Entrar a produccion": lo que hace index() al abrir la pantalla. */
    private function entrar(): void
    {
        $controller = new ModuloProduccionUrdidoController;
        $orden = UrdProgramaUrdido::find(1);

        $g = new \ReflectionMethod($controller, 'getJuliosForOrder');
        $g->setAccessible(true);
        $julios = $g->invoke($controller, $orden);

        $total = 0;
        foreach ($julios as $j) {
            $total += max(0, (int) $j->Julios);
        }

        $m = new \ReflectionMethod($controller, 'ensureProductionRecordsExist');
        $m->setAccessible(true);
        $m->invoke($controller, $orden, $julios, $total);
    }

    /** Editar el plan desde la pantalla de ordenes programadas. */
    private function editarPlan(?int $id, int $julios, int $hilos): array
    {
        $controller = app(EditarOrdenesProgramadasController::class);

        return $controller->actualizarJulios(Request::create('/aj', 'POST', [
            'orden_id' => 1, 'id' => $id, 'no_julio' => $julios, 'hilos' => $hilos,
        ]))->getData(true);
    }

    private function capturar(int $id, string $julio): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', $id)->update([
            'HoraInicial' => '06:00', 'HoraFinal' => '07:00',
            'NoJulio' => $julio, 'KgBruto' => 300, 'Tara' => 50, 'KgNeto' => 250,
        ]);
    }

    private function filas(): int
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', 'C001')->count();
    }

    private function porHilos(): array
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')
            ->selectRaw('Hilos, COUNT(*) n')->groupBy('Hilos')->orderBy('Hilos')
            ->pluck('n', 'Hilos')->all();
    }

    /** finalizar() valida permisos; en el test se puentea solo eso. */
    private function controladorConPermiso(): ModuloProduccionUrdidoController
    {
        return new class extends ModuloProduccionUrdidoController
        {
            protected function ensureUserCanEdit(): void {}
        };
    }

    private function idsFilas(): array
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')->orderBy('Id')->pluck('Id')->all();
    }

    // ── el ciclo que se quiere verificar ─────────────────────────────

    public function test_quitar_sumar_entrar_salir_no_deja_filas_de_mas(): void
    {
        $idPlan = $this->plan(4, 484);

        $this->entrar();
        $this->assertSame(4, $this->filas(), 'Alta inicial debe seguir el plan.');

        $ids = $this->idsFilas();
        $this->capturar($ids[0], 'J1');
        $this->capturar($ids[1], 'J2');

        // QUITAR: 4 -> 3
        $this->editarPlan($idPlan, 3, 484);
        $this->assertSame(3, $this->filas(), 'Al quitar un julio debe quedar 3.');

        // salir y entrar varias veces
        $this->entrar();
        $this->entrar();
        $this->assertSame(3, $this->filas(), 'Entrar y salir no debe crear filas.');

        // SUMAR: 3 -> 6
        $this->editarPlan($idPlan, 6, 484);
        $this->assertSame(6, $this->filas(), 'Al sumar julios debe quedar 6.');

        $this->entrar();
        $this->entrar();
        $this->assertSame(6, $this->filas(), 'Reentrar no debe duplicar.');
        $this->assertSame([484 => 6], $this->porHilos());
    }

    public function test_cambiar_el_valor_de_hilos_no_duplica(): void
    {
        $idPlan = $this->plan(4, 484);
        $this->entrar();
        $ids = $this->idsFilas();
        $this->capturar($ids[0], 'J1');

        // cambiar Hilos 484 -> 500
        $this->editarPlan($idPlan, 4, 500);
        $this->entrar();
        $this->entrar();

        $this->assertSame(4, $this->filas(), 'Cambiar Hilos no debe duplicar la orden.');
        $this->assertSame([500 => 4], $this->porHilos(), 'Todas deben quedar en el Hilos nuevo.');
    }

    public function test_dos_grupos_de_hilos_quitar_y_sumar(): void
    {
        $g1 = $this->plan(2, 486);
        $g2 = $this->plan(4, 484);
        $this->entrar();
        $this->assertSame(6, $this->filas());

        $ids = $this->idsFilas();
        $this->capturar($ids[0], 'J1');

        $this->editarPlan($g2, 2, 484);   // quitar 2 del segundo grupo
        $this->entrar();
        $this->assertSame(4, $this->filas(), 'Quitar 2 de un grupo deja 4.');

        $this->editarPlan($g1, 5, 486);   // sumar 3 al primero
        $this->entrar();
        $this->entrar();
        $this->assertSame(7, $this->filas(), 'Sumar 3 deja 7, no mas.');
        $this->assertSame([484 => 2, 486 => 5], $this->porHilos());
    }

    // ── los casos que SI se ven rotos en produccion ──────────────────

    /**
     * Folio 01021 real: el plan traia Hilos NULL. sincronizarHilosProduccionPorFolio
     * corta con `return 0` cuando $hilosAnterior es null, asi que al poner un
     * Hilos de verdad las filas se quedan en NULL y el grupo queda huerfano.
     */
    public function test_plan_con_hilos_null_y_luego_se_le_pone_valor(): void
    {
        $idPlan = $this->plan(4, 0);
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->where('Id', $idPlan)->update(['Hilos' => null]);

        $this->entrar();
        $this->assertSame(4, $this->filas());

        // ahora se le asigna Hilos = 472
        $this->editarPlan($idPlan, 4, 472);
        $this->entrar();
        $this->entrar();

        $this->assertSame(4, $this->filas(), 'No debe duplicar al asignar Hilos por primera vez.');
        $this->assertSame([472 => 4], $this->porHilos());
    }

    /**
     * Folio 00077 real: 6 filas capturadas con Hilos 461 que el plan no menciona.
     * Al entrar, el conteo por grupo veia cero filas del grupo del plan.
     */
    public function test_filas_capturadas_con_hilos_fuera_del_plan(): void
    {
        $idPlan = $this->plan(6, 395);
        $this->entrar();

        // las 6 se capturan y alguien deja su Hilos en 461 (ruta de edicion que no remapeo)
        foreach ($this->idsFilas() as $i => $id) {
            $this->capturar($id, 'J'.$i);
        }
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', 'C001')->update(['Hilos' => 461]);

        $this->entrar();
        $this->entrar();

        $this->assertSame(6, $this->filas(), 'No debe recrear el grupo del plan encima de las capturadas.');
    }

    /**
     * Dos grupos con el MISMO Hilos: sincronizarHilosProduccionPorFolio empareja
     * por WHERE Hilos = anterior, asi que al editar uno remapea los dos.
     */
    public function test_dos_grupos_con_el_mismo_hilos(): void
    {
        $g1 = $this->plan(2, 484);
        $g2 = $this->plan(3, 484);
        $this->entrar();
        $this->assertSame(5, $this->filas());

        // cambiar solo el primero a 500
        $this->editarPlan($g1, 2, 500);
        $this->entrar();
        $this->entrar();

        $this->assertSame(5, $this->filas(), 'No debe duplicar cuando dos grupos comparten Hilos.');
        $this->assertSame([484 => 3, 500 => 2], $this->porHilos());
    }

    /** Una fila en AX no se puede remapear: el grupo queda huerfano por diseno. */
    public function test_fila_en_ax_bloqueada_no_duplica_la_orden(): void
    {
        $idPlan = $this->plan(3, 484);
        $this->entrar();

        $ids = $this->idsFilas();
        $this->capturar($ids[0], 'J1');
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', $ids[0])->update(['AX' => 1]);

        $this->editarPlan($idPlan, 3, 500);
        $this->entrar();
        $this->entrar();

        $this->assertSame(3, $this->filas(), 'La fila en AX no debe provocar que se recree el grupo.');
    }

    /** Los renglones van de MAYOR a menor numero de hilos. */
    public function test_los_hilos_mayores_van_arriba(): void
    {
        // a proposito: el grupo de MAS hilos tiene MAS julios, para que ordenar
        // por la cantidad de julios dé el orden contrario al correcto
        $this->plan(2, 484);
        $this->plan(4, 486);
        $this->entrar();

        $hilosPorRenglon = DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')->orderBy('Id')->pluck('Hilos')->all();

        $this->assertSame([486, 486, 486, 486, 484, 484], array_map('intval', $hilosPorRenglon));
    }

    /** El plan pide 6: al finalizar deben cerrarse 6, ni uno mas. */
    public function test_al_finalizar_no_se_va_un_renglon_extra(): void
    {
        $idPlan = $this->plan(6, 484);
        $this->entrar();

        // se corren 4 de los 6; el operador elige los renglones (orden aleatorio)
        $ids = $this->idsFilas();
        foreach ([$ids[4], $ids[0], $ids[3], $ids[1]] as $i => $id) {
            $this->capturar($id, 'J'.$i);
        }

        // entrar y salir varias veces antes de cerrar
        $this->entrar();
        $this->entrar();
        $this->assertSame(6, $this->filas(), 'Antes de cerrar debe haber exactamente 6.');

        $payload = $this->controladorConPermiso()->finalizar(Request::create('/f', 'POST', [
            'orden_id' => 1, 'confirmar_descarte' => true,
        ]))->getData(true);

        $this->assertTrue($payload['success'], json_encode($payload));

        $cerrados = DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')->where('Finalizar', 1)->count();

        $this->assertSame(4, $cerrados, 'Solo los 4 corridos se cierran.');
        $this->assertSame(4, $this->filas(), 'Los 2 esqueletos se descartan; no queda ninguno extra.');
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
    }

    /** Aunque se hayan editado los julios en el camino, al cerrar no sobra nada. */
    public function test_ciclo_completo_editar_entrar_salir_y_finalizar(): void
    {
        $idPlan = $this->plan(4, 484);
        $this->entrar();

        $this->capturar($this->idsFilas()[0], 'J1');

        $this->editarPlan($idPlan, 6, 484);   // sumar
        $this->entrar();
        $this->editarPlan($idPlan, 5, 484);   // quitar
        $this->entrar();
        $this->entrar();
        $this->assertSame(5, $this->filas(), 'El plan quedo en 5.');

        foreach (array_slice($this->idsFilas(), 1, 2) as $i => $id) {
            $this->capturar($id, 'K'.$i);
        }

        $payload = $this->controladorConPermiso()->finalizar(Request::create('/f', 'POST', [
            'orden_id' => 1, 'confirmar_descarte' => true,
        ]))->getData(true);

        $this->assertTrue($payload['success'], json_encode($payload));
        $this->assertSame(3, $this->filas(), 'Quedan las 3 corridas, sin extras.');
        $this->assertSame(3, DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')->where('Finalizar', 1)->count());
    }
}
