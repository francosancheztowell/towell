<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Tejedores\Desarrolladores\MovimientoDesarrolladorService;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Regla del owner (2026-10-10): cuando el líder de un grupo OrdCompartida desaparece, el nuevo
 * líder es el de FechaInicio más antigua QUE TENGA NoProduccion (OrdCompartida::seleccionarLider);
 * si ninguno la tiene, el más antiguo. Aquí se prueban los dos respaldos que la aplican al
 * transferir el SaldoPedido del que se va:
 *
 *  1. Utilería · Finalizar órdenes (POST planeacion/utileria/finalizar/procesar).
 *  2. Desarrolladores · MovimientoDesarrolladorService::moverRegistroEnProceso (elimina el
 *     registro EnProceso sin Reprogramar).
 *
 * Grupo OrdCompartida 50, cada miembro en su telar:
 *   A (Id 101, telar 301, líder, EnProceso, FechaInicio 09-01, NoProduccion 40001, Saldo 300)
 *   B (Id 102, telar 302, FechaInicio 09-02, SIN NoProduccion, Saldo 200)
 *   C (Id 103, telar 303, FechaInicio 09-03, NoProduccion 40003, Saldo 100)
 *   X (Id 104, telar 301, pos 2, fuera del grupo): el que toma EnProceso en el flujo de desarrolladores.
 *
 * La lógica anterior (orderBy FechaInicio ->first()) elegía a B; la regla elige a C.
 */
class LiderRespaldoFinalizarDesarrolladoresTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const URL = '/planeacion/utileria/finalizar/procesar';

    private const GRUPO = 50;

    private const A = 101;

    private const B = 102;

    private const C = 103;

    private const X = 104;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        // ISNULL es palabra reservada en sqlite (lo usa el collision-avoidance de Posicion).
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new class($pdo, $db, $prefix, $config) extends SQLiteConnection
        {
            public function affectingStatement($query, $bindings = [])
            {
                return parent::affectingStatement(str_replace('ISNULL(', 'IFNULL(', $query), $bindings);
            }
        });
        $this->prepararSuperficies();
        Schema::table('ReqProgramaTejido', fn (Blueprint $t) => $t->integer('Posicion')->nullable()->change());
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new SQLiteConnection($pdo, $db, $prefix, $config));
    }

    // ---- fixtures ---------------------------------------------------------------------

    /** @param  bool  $xEnGrupo  X entra al grupo 50 con FechaInicio 09-05 (posterior a C), así C sigue siendo el más antiguo con NoProduccion. */
    private function sembrarGrupo(?string $noProduccionC, bool $xEnGrupo = false): void
    {
        $base = ['SalonTejidoId' => 'SMIT', 'EnProceso' => 0, 'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 0,
            'HorasProd' => 24, 'CalendarioId' => null, 'Ultimo' => '0', 'Posicion' => 1];

        $x = ['Id' => self::X, 'NoTelarId' => '301', 'Posicion' => 2, 'NoProduccion' => '40099', 'OrdCompartida' => null,
            'OrdCompartidaLider' => null, 'TotalPedido' => 50, 'SaldoPedido' => 50, 'ItemId' => 'ITEM-X',
            'FechaInicio' => '2026-09-02 06:30:00', 'FechaFinal' => '2026-09-03 06:30:00'] + $base;
        if ($xEnGrupo) {
            $x = ['OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 0,
                'FechaInicio' => '2026-09-05 06:30:00', 'FechaFinal' => '2026-09-06 06:30:00'] + $x;
        }

        DB::table('ReqProgramaTejido')->insert([
            ['Id' => self::A, 'NoTelarId' => '301', 'EnProceso' => 1, 'OrdCompartidaLider' => 1, 'NoProduccion' => '40001',
                'TotalPedido' => 300, 'SaldoPedido' => 300, 'ItemId' => 'ITEM-A',
                'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-02 06:30:00'] + $base,
            ['Id' => self::B, 'NoTelarId' => '302', 'NoProduccion' => null,
                'TotalPedido' => 200, 'SaldoPedido' => 200, 'ItemId' => 'ITEM-B',
                'FechaInicio' => '2026-09-02 06:30:00', 'FechaFinal' => '2026-09-03 06:30:00'] + $base,
            ['Id' => self::C, 'NoTelarId' => '303', 'NoProduccion' => $noProduccionC,
                'TotalPedido' => 100, 'SaldoPedido' => 100, 'ItemId' => 'ITEM-C',
                'FechaInicio' => '2026-09-03 06:30:00', 'FechaFinal' => '2026-09-04 06:30:00'] + $base,
            $x,
        ]);

        DB::table('CatCodificados')->insert([
            ['OrdenTejido' => '40001', 'TelarId' => 301, 'Departamento' => 'SMIT', 'Pedido' => 300, 'Saldos' => 300],
            ['OrdenTejido' => '40003', 'TelarId' => 303, 'Departamento' => 'SMIT', 'Pedido' => 100, 'Saldos' => 100],
            ['OrdenTejido' => '40099', 'TelarId' => 301, 'Departamento' => 'SMIT', 'Pedido' => 50, 'Saldos' => 50],
        ]);
    }

    private function fila(int $id): ?object
    {
        return DB::table('ReqProgramaTejido')->where('Id', $id)->first();
    }

    private function finalizarA(): void
    {
        $this->actingAs($this->usuarioConPermisos([188 => ['modificar']]))
            ->postJson(self::URL, ['ids' => [self::A]])
            ->assertOk()
            ->assertJson(['success' => true, 'finalizadas' => 1]);
    }

    private function eliminarAPorDesarrollador(): void
    {
        app(MovimientoDesarrolladorService::class)
            ->moverRegistroEnProceso(ReqProgramaTejido::query()->findOrFail(self::X), true);

        $this->assertSame(1, (int) $this->fila(self::X)->EnProceso, 'X toma EnProceso en el telar 301');
    }

    /** El grupo queda con un único líder ($lider) y el saldo de A sumado al suyo. */
    private function assertNuevoLider(int $lider, float $saldoEsperado): void
    {
        $this->assertNull($this->fila(self::A), 'A desaparece del programa');

        $otro = $lider === self::B ? self::C : self::B;
        $this->assertSame(1, (int) $this->fila($lider)->OrdCompartidaLider, "Id {$lider} debe ser el nuevo líder");
        $this->assertSame(0, (int) $this->fila($otro)->OrdCompartidaLider, "Id {$otro} no debe quedar como líder");
        $this->assertSame(1, DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->where('OrdCompartidaLider', 1)->count());

        $this->assertEqualsWithDelta($saldoEsperado, (float) $this->fila($lider)->SaldoPedido, 0.001, 'el líder recibe el saldo de A');
        $saldoOtro = $otro === self::B ? 200.0 : 100.0;
        $this->assertEqualsWithDelta($saldoOtro, (float) $this->fila($otro)->SaldoPedido, 0.001, 'el no-líder conserva su saldo');
    }

    // ---- 1. Utilería · Finalizar órdenes -----------------------------------------------

    public function test_finalizar_lider_pasa_el_liderazgo_y_el_saldo_al_mas_antiguo_con_no_produccion(): void
    {
        $this->sembrarGrupo('40003');

        $this->finalizarA();

        $this->assertNuevoLider(self::C, 400.0);

        // CatCodificados del nuevo líder refleja saldo y liderazgo.
        $cat = DB::table('CatCodificados')->where('OrdenTejido', '40003')->first();
        $this->assertEqualsWithDelta(400.0, (float) $cat->Saldos, 0.001);
        $this->assertSame(1, (int) $cat->OrdCompartidaLider);
        $this->assertSame('ITEM-C', DB::table('ReqProgramaTejido')->where('Id', self::B)->value('OrdPrincipal'));
    }

    public function test_finalizar_lider_sin_no_produccion_en_el_grupo_pasa_al_mas_antiguo(): void
    {
        $this->sembrarGrupo(null);

        $this->finalizarA();

        $this->assertNuevoLider(self::B, 500.0);
    }

    // ---- 2. Desarrolladores · eliminar el registro EnProceso ---------------------------

    public function test_desarrollador_elimina_lider_y_pasa_liderazgo_y_saldo_al_mas_antiguo_con_no_produccion(): void
    {
        $this->sembrarGrupo('40003');

        $this->eliminarAPorDesarrollador();

        $this->assertNuevoLider(self::C, 400.0);

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '40003')->first();
        $this->assertEqualsWithDelta(400.0, (float) $cat->Saldos, 0.001);
        $this->assertSame(1, (int) $cat->OrdCompartidaLider);
    }

    public function test_desarrollador_elimina_lider_sin_no_produccion_en_el_grupo_pasa_al_mas_antiguo(): void
    {
        $this->sembrarGrupo(null);

        $this->eliminarAPorDesarrollador();

        $this->assertNuevoLider(self::B, 500.0);
    }

    /**
     * X (el que toma EnProceso) es del mismo grupo: tras el respaldo, el paso que recalcula el
     * líder del grupo de X también debe aplicar la regla y no devolver el liderazgo a B (el más
     * antiguo sin NoProduccion), dejando el saldo transferido en un no-líder.
     */
    public function test_desarrollador_con_x_en_el_grupo_deja_como_unico_lider_al_mas_antiguo_con_no_produccion(): void
    {
        $this->sembrarGrupo('40003', xEnGrupo: true);

        $this->eliminarAPorDesarrollador();

        $this->assertNuevoLider(self::C, 400.0);
        $this->assertSame(0, (int) $this->fila(self::X)->OrdCompartidaLider, 'X no debe quedar como líder');
        $this->assertEqualsWithDelta(50.0, (float) $this->fila(self::X)->SaldoPedido, 0.001, 'X conserva su saldo');

        $this->assertSame(
            ['ITEM-C'],
            DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->distinct()->pluck('OrdPrincipal')->all(),
            'OrdPrincipal del grupo = ItemId del líder'
        );

        $catC = DB::table('CatCodificados')->where('OrdenTejido', '40003')->first();
        $this->assertEqualsWithDelta(400.0, (float) $catC->Saldos, 0.001);
        $this->assertSame(1, (int) $catC->OrdCompartidaLider);
        $this->assertSame('ITEM-C', $catC->OrdPrincipal);

        $catX = DB::table('CatCodificados')->where('OrdenTejido', '40099')->first();
        $this->assertSame(0, (int) $catX->OrdCompartidaLider);
        $this->assertSame('ITEM-C', $catX->OrdPrincipal);
    }
}
