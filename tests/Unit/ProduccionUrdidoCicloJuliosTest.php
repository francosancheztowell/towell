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

    /** Deja el folio en blanco para el siguiente caso de la matriz. */
    private function reiniciar(): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', 'C001')->delete();
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->where('Folio', 'C001')->delete();
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->update(['Status' => 'En Proceso']);
    }

    private function totalPlan(): int
    {
        return (int) DB::connection('sqlsrv')->table('UrdJuliosOrden')
            ->where('Folio', 'C001')->whereNotNull('Julios')->sum('Julios');
    }

    private function capturadas(): int
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', 'C001')
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
                })->orWhere(function ($w) {
                    $w->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
                });
            })
            ->count();
    }

    /**
     * El invariante de todo el modulo: nunca puede haber mas renglones que el
     * plan, salvo por los que ya traen captura y por eso no se pueden borrar.
     */
    private function verificarInvariante(string $caso): void
    {
        $plan = $this->totalPlan();
        $cap = $this->capturadas();
        $filas = $this->filas();

        $this->assertSame(
            max($plan, $cap),
            $filas,
            "{$caso}: plan={$plan} capturadas={$cap} pero hay {$filas} renglones."
        );
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

    // ---------------------------------------------------------------
    // Matriz exhaustiva de sumar / restar julios
    // ---------------------------------------------------------------

    /**
     * Para cada combinacion de plan inicial, cuantos renglones se capturan y
     * a cuanto se mueve el plan, se comprueba el invariante despues de editar
     * y despues de entrar tres veces seguidas.
     */
    public function test_matriz_sumar_y_restar_un_grupo(): void
    {
        $iniciales = [1, 2, 3, 5, 8];
        $destinos = [0, 1, 2, 3, 5, 8, 12];
        $capturas = [0, 1, 2, 'todas'];
        $casos = 0;

        foreach ($iniciales as $ini) {
            foreach ($destinos as $dest) {
                foreach ($capturas as $cap) {
                    $this->reiniciar();
                    $idPlan = $this->plan($ini, 484);
                    $this->entrar();
                    $this->verificarInvariante("alta ini={$ini}");

                    $ids = $this->idsFilas();
                    $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                    for ($i = 0; $i < $nCap; $i++) {
                        $this->capturar($ids[$i], 'J'.$i);
                    }

                    $etiqueta = "ini={$ini} dest={$dest} cap={$cap}";
                    $this->editarPlan($idPlan, $dest, 484);
                    $this->verificarInvariante("tras editar {$etiqueta}");

                    $this->entrar();
                    $this->verificarInvariante("tras entrar 1 {$etiqueta}");
                    $this->entrar();
                    $this->entrar();
                    $this->verificarInvariante("tras entrar 3 {$etiqueta}");

                    $casos++;
                }
            }
        }

        $this->assertSame(140, $casos, 'La matriz debe cubrir 140 combinaciones.');
    }

    /** Lo mismo con dos grupos: se mueve uno y el otro no debe contaminarse. */
    public function test_matriz_sumar_y_restar_dos_grupos(): void
    {
        $destinos = [0, 1, 3, 6];
        $capturas = [0, 2, 'todas'];
        $cuales = ['primero', 'segundo'];
        $casos = 0;

        foreach ($destinos as $dest) {
            foreach ($capturas as $cap) {
                foreach ($cuales as $cual) {
                    $this->reiniciar();
                    $g1 = $this->plan(2, 486);
                    $g2 = $this->plan(4, 484);
                    $this->entrar();
                    $this->verificarInvariante('alta dos grupos');

                    $ids = $this->idsFilas();
                    $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                    for ($i = 0; $i < $nCap; $i++) {
                        $this->capturar($ids[$i], 'J'.$i);
                    }

                    $etiqueta = "dest={$dest} cap={$cap} grupo={$cual}";
                    if ($cual === 'primero') {
                        $this->editarPlan($g1, $dest, 486);
                    } else {
                        $this->editarPlan($g2, $dest, 484);
                    }
                    $this->verificarInvariante("tras editar {$etiqueta}");

                    $this->entrar();
                    $this->entrar();
                    $this->verificarInvariante("tras entrar {$etiqueta}");

                    $casos++;
                }
            }
        }

        $this->assertSame(24, $casos);
    }

    /** Cambiar el valor de Hilos, con y sin captura, en toda combinacion. */
    public function test_matriz_cambiar_valor_de_hilos(): void
    {
        $valores = [484, 486, 500, 0];
        $capturas = [0, 1, 'todas'];

        foreach ($valores as $nuevo) {
            foreach ($capturas as $cap) {
                $this->reiniciar();
                $idPlan = $this->plan(4, 484);
                $this->entrar();

                $ids = $this->idsFilas();
                $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                for ($i = 0; $i < $nCap; $i++) {
                    $this->capturar($ids[$i], 'J'.$i);
                }

                $etiqueta = "hilos={$nuevo} cap={$cap}";
                $this->editarPlan($idPlan, 4, $nuevo);
                $this->entrar();
                $this->entrar();
                $this->verificarInvariante("cambio de hilos {$etiqueta}");
            }
        }
    }

    /** Secuencias largas: sube, baja, sube, con entradas intercaladas. */
    public function test_secuencias_largas_de_ediciones(): void
    {
        $secuencias = [
            [4, 6, 3, 7, 2],
            [1, 10, 1, 10, 1],
            [5, 5, 5, 5, 5],
            [3, 0, 4, 0, 6],
            [2, 3, 2, 3, 2],
        ];

        foreach ($secuencias as $s => $secuencia) {
            $this->reiniciar();
            $idPlan = $this->plan(array_shift($secuencia), 484);
            $this->entrar();

            // capturar 1 al principio para que haya algo que no se pueda borrar
            $ids = $this->idsFilas();
            if (count($ids) > 0) {
                $this->capturar($ids[0], 'J0');
            }

            foreach ($secuencia as $paso => $destino) {
                $this->editarPlan($idPlan, $destino, 484);
                $this->entrar();
                $this->verificarInvariante("secuencia {$s} paso {$paso} -> {$destino}");
            }

            $this->entrar();
            $this->entrar();
            $this->verificarInvariante("secuencia {$s} final");
        }
    }

    /** Entrar N veces seguidas sin tocar nada nunca debe mover el conteo. */
    public function test_entrar_muchas_veces_no_mueve_nada(): void
    {
        $this->plan(3, 486);
        $this->plan(5, 484);
        $this->entrar();
        $antes = $this->filas();

        for ($i = 0; $i < 15; $i++) {
            $this->entrar();
        }

        $this->assertSame($antes, $this->filas(), 'Entrar 15 veces no debe crear ni borrar.');
        $this->assertSame(8, $this->filas());
    }

    // ---------------------------------------------------------------
    // Las otras puertas: agregar un grupo nuevo, borrar un grupo,
    // editar en Programado, y el endpoint actualizarHilosProduccion
    // ---------------------------------------------------------------

    /** Agregar un grupo NUEVO al plan (id = null), no crecer uno existente. */
    private function agregarGrupo(int $julios, int $hilos): array
    {
        return $this->editarPlan(null, $julios, $hilos);
    }

    /** Borrar un grupo del plan: se manda no_julio e hilos vacios. */
    private function borrarGrupo(int $idPlan): array
    {
        $controller = app(EditarOrdenesProgramadasController::class);

        return $controller->actualizarJulios(Request::create('/aj', 'POST', [
            'orden_id' => 1, 'id' => $idPlan, 'no_julio' => '', 'hilos' => '',
        ]))->getData(true);
    }

    /** El OTRO endpoint que toca Hilos, por cantidad de julios en vez de por Id. */
    private function actualizarHilosPorCantidad(int $cantidadJulios, int $hilos): array
    {
        $controller = app(EditarOrdenesProgramadasController::class);

        return $controller->actualizarHilosProduccion(Request::create('/ahp', 'POST', [
            'orden_id' => 1, 'no_julio' => $cantidadJulios, 'hilos' => $hilos,
        ]))->getData(true);
    }

    private function ponerStatus(string $status): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->update(['Status' => $status]);
    }

    public function test_matriz_agregar_grupos_nuevos(): void
    {
        $capturas = [0, 2, 'todas'];
        $nuevos = [[1, 500], [3, 500], [4, 486], [2, 484]];

        foreach ($capturas as $cap) {
            foreach ($nuevos as $n) {
                $this->reiniciar();
                $this->plan(4, 484);
                $this->entrar();

                $ids = $this->idsFilas();
                $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                for ($i = 0; $i < $nCap; $i++) {
                    $this->capturar($ids[$i], 'J'.$i);
                }

                $etiqueta = "cap={$cap} nuevo={$n[0]}x{$n[1]}";
                $this->agregarGrupo($n[0], $n[1]);
                $this->verificarInvariante("tras agregar grupo {$etiqueta}");

                $this->entrar();
                $this->entrar();
                $this->verificarInvariante("tras entrar {$etiqueta}");
            }
        }
    }

    public function test_matriz_borrar_grupos(): void
    {
        $capturas = [0, 1, 3, 'todas'];
        $cuales = ['primero', 'segundo'];

        foreach ($capturas as $cap) {
            foreach ($cuales as $cual) {
                $this->reiniciar();
                $g1 = $this->plan(2, 486);
                $g2 = $this->plan(4, 484);
                $this->entrar();

                $ids = $this->idsFilas();
                $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                for ($i = 0; $i < $nCap; $i++) {
                    $this->capturar($ids[$i], 'J'.$i);
                }

                $etiqueta = "cap={$cap} borra={$cual}";
                $this->borrarGrupo($cual === 'primero' ? $g1 : $g2);
                $this->verificarInvariante("tras borrar grupo {$etiqueta}");

                $this->entrar();
                $this->entrar();
                $this->verificarInvariante("tras entrar {$etiqueta}");
            }
        }
    }

    /** Editar el plan mientras la orden esta Programado: el remapeo se omite. */
    public function test_editar_en_programado_y_luego_entrar(): void
    {
        $destinos = [2, 4, 6];
        $hilosNuevos = [484, 500];

        foreach ($destinos as $dest) {
            foreach ($hilosNuevos as $hn) {
                $this->reiniciar();
                $idPlan = $this->plan(4, 484);
                $this->entrar();          // crea 4 filas con Hilos 484

                $ids = $this->idsFilas();
                $this->capturar($ids[0], 'J0');

                // la orden regresa a Programado y ahi se edita el plan
                $this->ponerStatus('Programado');
                $this->editarPlan($idPlan, $dest, $hn);
                $this->ponerStatus('En Proceso');

                $this->entrar();
                $this->entrar();
                $this->verificarInvariante("editado en Programado dest={$dest} hilos={$hn}");
            }
        }
    }

    /** El endpoint actualizarHilosProduccion, que empareja por cantidad de julios. */
    public function test_actualizar_hilos_por_el_otro_endpoint(): void
    {
        $capturas = [0, 1, 'todas'];
        $nuevos = [486, 500, 484];

        foreach ($capturas as $cap) {
            foreach ($nuevos as $hn) {
                $this->reiniciar();
                $this->plan(4, 484);
                $this->entrar();

                $ids = $this->idsFilas();
                $nCap = $cap === 'todas' ? count($ids) : min((int) $cap, count($ids));
                for ($i = 0; $i < $nCap; $i++) {
                    $this->capturar($ids[$i], 'J'.$i);
                }

                $this->actualizarHilosPorCantidad(4, $hn);
                $this->entrar();
                $this->entrar();
                $this->verificarInvariante("otro endpoint cap={$cap} hilos={$hn}");
            }
        }
    }

    /** Agregar un grupo, borrarlo, volver a agregarlo, varias vueltas. */
    public function test_agregar_y_borrar_grupos_en_ciclo(): void
    {
        $this->plan(3, 484);
        $this->entrar();
        $this->capturar($this->idsFilas()[0], 'J0');

        for ($vuelta = 0; $vuelta < 4; $vuelta++) {
            $r = $this->agregarGrupo(2, 500);
            $this->entrar();
            $this->verificarInvariante("vuelta {$vuelta} tras agregar");

            $nuevoId = (int) DB::connection('sqlsrv')->table('UrdJuliosOrden')
                ->where('Folio', 'C001')->where('Hilos', 500)->orderBy('Id', 'desc')->value('Id');

            $this->borrarGrupo($nuevoId);
            $this->entrar();
            $this->verificarInvariante("vuelta {$vuelta} tras borrar");
        }
    }
}
