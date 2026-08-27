<?php

namespace Tests\Unit;

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\EditarOrdenesProgramadasController;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Ensayo con 10 ordenes REALES copiadas de produccion (solo lectura).
 *
 * El snapshot vive en tests/fixtures/ordenes-urdido-reales.json y se genera
 * leyendo UrdProgramaUrdido / UrdJuliosOrden / UrdProduccionUrdido. Aqui se
 * replica cada orden en SQLite y se le corre el ciclo del piso, sin tocar
 * jamas la base de produccion.
 */
class ProduccionUrdidoOrdenesRealesTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** @var array<int,array> */
    private array $ordenes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $fixture = base_path('tests/fixtures/ordenes-urdido-reales.json');
        if (! is_file($fixture)) {
            $this->markTestSkipped('Falta el snapshot de ordenes reales.');
        }
        $this->ordenes = json_decode(file_get_contents($fixture), true);

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
    }

    // ── carga de una orden real ──────────────────────────────────────

    private function cargar(array $orden): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->delete();
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->delete();
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->delete();

        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => $orden['folio'], 'Status' => 'En Proceso',
            'MaquinaId' => 'Mc Coy 2', 'Metros' => 6000, 'Incorrecto' => 0, 'ax' => 0,
        ]);

        foreach ($orden['plan'] as $g) {
            DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert([
                'Folio' => $orden['folio'],
                'Julios' => $g['julios'] !== null ? (int) $g['julios'] : null,
                'Hilos' => $g['hilos'] !== null ? (int) $g['hilos'] : null,
            ]);
        }

        foreach ($orden['filas'] as $f) {
            DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
                'Folio' => $orden['folio'],
                'Hilos' => $f['hilos'] !== null ? (int) $f['hilos'] : null,
                'HoraInicial' => $f['hora'],
                'NoJulio' => $f['julio'],
                'KgBruto' => $f['kg'] !== null ? (float) $f['kg'] : null,
                'AX' => $f['ax'] !== null ? (int) $f['ax'] : null,
            ]);
        }
    }

    private function entrar(string $folio): void
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

    private function filas(string $folio): int
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', $folio)->count();
    }

    private function capturadas(string $folio): int
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', $folio)
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
                })->orWhere(function ($w) {
                    $w->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
                });
            })->count();
    }

    private function totalPlan(string $folio): int
    {
        return (int) DB::connection('sqlsrv')->table('UrdJuliosOrden')
            ->where('Folio', $folio)->whereNotNull('Julios')->sum('Julios');
    }

    private function editarPlan(?int $id, $julios, $hilos): void
    {
        app(EditarOrdenesProgramadasController::class)->actualizarJulios(
            Request::create('/aj', 'POST', ['orden_id' => 1, 'id' => $id, 'no_julio' => $julios, 'hilos' => $hilos])
        );
    }

    // ── el ensayo ────────────────────────────────────────────────────

    /** Solo abrir la orden (entrar y salir) no debe mover el conteo. */
    public function test_abrir_las_10_ordenes_reales_no_cambia_el_conteo(): void
    {
        $reporte = [];
        $fallos = [];

        foreach ($this->ordenes as $orden) {
            $folio = $orden['folio'];
            $this->cargar($orden);

            $antes = $this->filas($folio);
            $this->entrar($folio);
            $this->entrar($folio);
            $this->entrar($folio);
            $despues = $this->filas($folio);

            $reporte[] = sprintf(
                '%-7s plan=%-2d antes=%-2d despues=%-2d capturadas=%-2d %s',
                $folio, $this->totalPlan($folio), $antes, $despues,
                $this->capturadas($folio), $antes === $despues ? 'OK' : '<<< CAMBIO'
            );

            if ($despues !== max($this->totalPlan($folio), $this->capturadas($folio))) {
                $fallos[] = "{$folio}: abrir la orden dejo {$despues} renglones.";
            }
        }

        fwrite(STDERR, "\n--- abrir 3 veces ---\n".implode("\n", $reporte)."\n");
        fwrite(STDERR, 'FALLOS: '.(count($fallos) ? "\n  ".implode("\n  ", $fallos) : 'ninguno')."\n");

        $this->assertSame([], $fallos, implode(' | ', $fallos));
    }

    /** Sumar julios en cada orden real: debe crecer exactamente lo pedido. */
    public function test_sumar_julios_en_las_10_ordenes_reales(): void
    {
        $reporte = [];
        $fallos = [];

        foreach ($this->ordenes as $orden) {
            $folio = $orden['folio'];
            $this->cargar($orden);
            $this->entrar($folio);

            $grupo = UrdJuliosOrden::where('Folio', $folio)->orderBy('Id')->first();
            if (! $grupo) {
                continue;
            }

            $planAntes = $this->totalPlan($folio);
            $nuevo = (int) $grupo->Julios + 3;
            $this->editarPlan((int) $grupo->Id, $nuevo, (int) ($grupo->Hilos ?? 484));
            $this->entrar($folio);
            $this->entrar($folio);

            $esperado = max($this->totalPlan($folio), $this->capturadas($folio));
            $real = $this->filas($folio);

            $reporte[] = sprintf(
                '%-7s plan %d -> %-2d  filas=%-2d esperado=%-2d %s',
                $folio, $planAntes, $this->totalPlan($folio), $real, $esperado,
                $real === $esperado ? 'OK' : '<<< FALLA'
            );

            if ($real !== $esperado) {
                $fallos[] = "{$folio}: al sumar 3 julios quedaron {$real} en vez de {$esperado}.";
            }
        }

        fwrite(STDERR, "\n--- sumar 3 julios ---\n".implode("\n", $reporte)."\n");
        fwrite(STDERR, 'FALLOS: '.(count($fallos) ? "\n  ".implode("\n  ", $fallos) : 'ninguno')."\n");

        $this->assertSame([], $fallos, implode(' | ', $fallos));
    }

    /** Restar julios en cada orden real. */
    public function test_restar_julios_en_las_10_ordenes_reales(): void
    {
        $reporte = [];
        $fallos = [];

        foreach ($this->ordenes as $orden) {
            $folio = $orden['folio'];
            $this->cargar($orden);
            $this->entrar($folio);

            $grupo = UrdJuliosOrden::where('Folio', $folio)->orderBy('Id')->first();
            if (! $grupo) {
                continue;
            }

            $planAntes = $this->totalPlan($folio);
            $nuevo = max(0, (int) $grupo->Julios - 2);
            $this->editarPlan((int) $grupo->Id, $nuevo, (int) ($grupo->Hilos ?? 484));
            $this->entrar($folio);
            $this->entrar($folio);

            $esperado = max($this->totalPlan($folio), $this->capturadas($folio));
            $real = $this->filas($folio);

            $reporte[] = sprintf(
                '%-7s plan %d -> %-2d  filas=%-2d esperado=%-2d %s',
                $folio, $planAntes, $this->totalPlan($folio), $real, $esperado,
                $real === $esperado ? 'OK' : '<<< FALLA'
            );

            if ($real !== $esperado) {
                $fallos[] = "{$folio}: al restar 2 julios quedaron {$real} en vez de {$esperado}.";
            }
        }

        fwrite(STDERR, "\n--- restar 2 julios ---\n".implode("\n", $reporte)."\n");
        fwrite(STDERR, 'FALLOS: '.(count($fallos) ? "\n  ".implode("\n  ", $fallos) : 'ninguno')."\n");

        $this->assertSame([], $fallos, implode(' | ', $fallos));
    }

    /** Ciclo bravo: sumar, restar, cambiar Hilos, con reentradas en medio. */
    public function test_ciclo_completo_en_las_10_ordenes_reales(): void
    {
        $reporte = [];
        $fallos = [];

        foreach ($this->ordenes as $orden) {
            $folio = $orden['folio'];
            $this->cargar($orden);
            $this->entrar($folio);

            $grupo = UrdJuliosOrden::where('Folio', $folio)->orderBy('Id')->first();
            if (! $grupo) {
                continue;
            }
            $id = (int) $grupo->Id;
            $hilos = (int) ($grupo->Hilos ?? 484);

            foreach ([[6, $hilos], [2, $hilos], [9, 999], [4, $hilos], [0, $hilos], [5, $hilos]] as $paso) {
                $this->editarPlan($id, $paso[0], $paso[1]);
                $this->entrar($folio);
            }
            $this->entrar($folio);
            $this->entrar($folio);

            $esperado = max($this->totalPlan($folio), $this->capturadas($folio));
            $real = $this->filas($folio);

            $reporte[] = sprintf(
                '%-7s plan=%-2d capturadas=%-2d filas=%-2d esperado=%-2d %s',
                $folio, $this->totalPlan($folio), $this->capturadas($folio), $real, $esperado,
                $real === $esperado ? 'OK' : '<<< FALLA'
            );

            if ($real !== $esperado) {
                $fallos[] = "{$folio}: tras el ciclo quedaron {$real} en vez de {$esperado}.";
            }
        }

        fwrite(STDERR, "\n--- ciclo 6-2-9(hilos 999)-4-0-5 ---\n".implode("\n", $reporte)."\n");
        fwrite(STDERR, 'FALLOS: '.(count($fallos) ? "\n  ".implode("\n  ", $fallos) : 'ninguno')."\n");

        $this->assertSame([], $fallos, implode(' | ', $fallos));
    }
}
