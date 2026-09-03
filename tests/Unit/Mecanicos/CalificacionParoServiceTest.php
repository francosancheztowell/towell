<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\CalificacionParoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CalificacionParoServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private CalificacionParoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        $schema = Schema::connection('sqlsrv');
        $schema->create('dbo.ManFallasParos', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Estatus')->nullable();
            $table->integer('Calidad')->nullable();
            $table->string('CveAtendio')->nullable();
            $table->string('NomAtendio')->nullable();
        });
        $schema->create('dbo.SYSUsuario', function (Blueprint $table): void {
            $table->increments('idusuario');
            $table->string('numero_empleado')->nullable();
            $table->string('nombre')->nullable();
        });
        $schema->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->string('FolioParo')->nullable();
            $table->string('Estatus')->nullable();
        });
        $schema->create('MecOrdenTrabajoLine', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio');
            $table->integer('Calificacion')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
        });

        $this->servicio = new CalificacionParoService;
    }

    // =====================================================================
    // Qué calificación aporta un paro
    // =====================================================================

    public function test_un_paro_cerrado_aporta_su_calidad_y_a_quien_la_puso(): void
    {
        DB::connection('sqlsrv')->table('dbo.SYSUsuario')
            ->insert(['numero_empleado' => '5501', 'nombre' => 'Ana Tejedora']);
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 4, 'CveAtendio' => '5501']);

        $this->assertSame([
            'Calificacion' => 4,
            'CveTejedor' => '5501',
            'NomTejedor' => 'Ana Tejedora',
        ], $this->servicio->calificacionDelParo($paro));
    }

    /**
     * CveAtendio es de quien CIERRA el paro; NomAtendio sale de un select de
     * operadores de mantenimiento y nombra a quien atendió la máquina. El nombre
     * que se guarda en la orden tiene que ser el de quien realmente calificó.
     */
    public function test_el_nombre_guardado_es_el_de_quien_cerro_el_paro_no_el_del_mecanico(): void
    {
        DB::connection('sqlsrv')->table('dbo.SYSUsuario')
            ->insert(['numero_empleado' => '5501', 'nombre' => 'Ana Tejedora']);
        $paro = $this->crearParo([
            'Estatus' => 'Terminado',
            'Calidad' => 5,
            'CveAtendio' => '5501',
            'NomAtendio' => 'Luis Mecánico',
        ]);

        $calificacion = $this->servicio->calificacionDelParo($paro);

        $this->assertSame('Ana Tejedora', $calificacion['NomTejedor']);
    }

    public function test_sin_usuario_en_catalogo_se_recurre_al_nombre_de_quien_atendio(): void
    {
        $paro = $this->crearParo([
            'Estatus' => 'Terminado',
            'Calidad' => 3,
            'CveAtendio' => '9999',
            'NomAtendio' => 'Luis Mecánico',
        ]);

        $this->assertSame('Luis Mecánico', $this->servicio->calificacionDelParo($paro)['NomTejedor']);
    }

    public function test_un_paro_todavia_activo_no_aporta_calificacion(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Activo', 'Calidad' => 4]);

        $this->assertNull($this->servicio->calificacionDelParo($paro));
    }

    public function test_un_paro_cerrado_sin_calidad_no_aporta_calificacion(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => null]);

        $this->assertNull($this->servicio->calificacionDelParo($paro));
    }

    public function test_una_calidad_historica_fuera_de_escala_se_ignora(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 10]);

        $this->assertNull($this->servicio->calificacionDelParo($paro));
    }

    public function test_una_orden_de_captura_manual_no_hereda_nada(): void
    {
        $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 5]);

        $this->assertNull($this->servicio->calificacionDeFolio(null));
        $this->assertNull($this->servicio->calificacionDeFolio('   '));
    }

    public function test_un_folio_de_paro_inexistente_no_hereda_nada(): void
    {
        $this->assertNull($this->servicio->calificacionDeFolio('PARO-BORRADO'));
    }

    // =====================================================================
    // El mecánico finaliza una orden cuyo paro ya estaba cerrado
    // =====================================================================

    public function test_calificar_una_orden_finalizada_la_deja_en_calificado(): void
    {
        $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 4, 'CveAtendio' => '5501']);
        $orden = $this->crearOrden('MEC00001', 'PARO-201-A', 'Terminado');
        $this->crearRenglones('MEC00001', 2);

        $this->assertTrue($this->servicio->calificarOrden($orden));

        $this->assertSame('Calificado', $orden->fresh()->Estatus);
        $this->assertSame([4, 4], $this->calificacionesDe('MEC00001'));
    }

    public function test_la_nota_manual_del_tejedor_le_gana_a_la_del_paro(): void
    {
        $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 2]);
        $orden = $this->crearOrden('MEC00001', 'PARO-201-A', 'Terminado');
        $this->crearRenglones('MEC00001', 1);
        DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')
            ->insert(['Folio' => 'MEC00001', 'Calificacion' => 5]);

        $this->servicio->calificarOrden($orden);

        $this->assertSame([2, 5], $this->calificacionesDe('MEC00001'));
    }

    public function test_una_orden_sin_paro_cerrado_no_cambia_de_estatus(): void
    {
        $this->crearParo(['Estatus' => 'Activo', 'Calidad' => null]);
        $orden = $this->crearOrden('MEC00001', 'PARO-201-A', 'Terminado');
        $this->crearRenglones('MEC00001', 1);

        $this->assertFalse($this->servicio->calificarOrden($orden));

        $this->assertSame('Terminado', $orden->fresh()->Estatus);
        $this->assertSame([null], $this->calificacionesDe('MEC00001'));
    }

    // =====================================================================
    // El paro se cierra después, con la orden ya creada
    // =====================================================================

    public function test_cerrar_el_paro_califica_las_ordenes_que_nacieron_de_el(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 3, 'CveAtendio' => '5501']);
        // Un mismo paro puede originar varias órdenes: una intervención puede
        // requerir varios pases.
        $this->crearOrden('MEC00001', 'PARO-201-A', 'Terminado');
        $this->crearRenglones('MEC00001', 1);
        $this->crearOrden('MEC00002', 'PARO-201-A', 'Terminado');
        $this->crearRenglones('MEC00002', 1);

        $this->assertSame(2, $this->servicio->propagarAOrdenesDelParo($paro));

        $this->assertSame([3], $this->calificacionesDe('MEC00001'));
        $this->assertSame([3], $this->calificacionesDe('MEC00002'));
    }

    /**
     * Una orden todavía Activa recibe la nota pero no avanza de estatus: el
     * mecánico puede seguir agregando renglones y finalizar() la reevalúa.
     */
    public function test_una_orden_todavia_activa_recibe_la_nota_pero_no_avanza(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 5]);
        $orden = $this->crearOrden('MEC00001', 'PARO-201-A', 'Activo');
        $this->crearRenglones('MEC00001', 1);

        $this->servicio->propagarAOrdenesDelParo($paro);

        $this->assertSame('Activo', $orden->fresh()->Estatus);
        $this->assertSame([5], $this->calificacionesDe('MEC00001'));
    }

    public function test_las_ordenes_ya_cerradas_no_se_recalifican(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 1]);
        $this->crearOrden('MEC00001', 'PARO-201-A', 'Autorizado');
        $this->crearRenglones('MEC00001', 1);
        $this->crearOrden('MEC00002', 'PARO-201-A', 'Calificado');
        $this->crearRenglones('MEC00002', 1);
        $this->crearOrden('MEC00003', 'PARO-201-A', 'Cancelado');
        $this->crearRenglones('MEC00003', 1);

        $this->assertSame(0, $this->servicio->propagarAOrdenesDelParo($paro));

        $this->assertSame([null], $this->calificacionesDe('MEC00001'));
        $this->assertSame([null], $this->calificacionesDe('MEC00002'));
        $this->assertSame([null], $this->calificacionesDe('MEC00003'));
    }

    public function test_las_ordenes_de_otro_paro_no_se_tocan(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 4]);
        $this->crearOrden('MEC00001', 'PARO-OTRO', 'Terminado');
        $this->crearRenglones('MEC00001', 1);

        $this->assertSame(0, $this->servicio->propagarAOrdenesDelParo($paro));

        $this->assertSame([null], $this->calificacionesDe('MEC00001'));
    }

    public function test_un_paro_sin_ordenes_se_cierra_sin_efectos(): void
    {
        $paro = $this->crearParo(['Estatus' => 'Terminado', 'Calidad' => 4]);

        $this->assertSame(0, $this->servicio->propagarAOrdenesDelParo($paro));
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearParo(array $atributos): ManFallasParos
    {
        return ManFallasParos::create([
            'Folio' => 'PARO-201-A',
            ...$atributos,
        ]);
    }

    private function crearOrden(string $folio, ?string $folioParo, string $estatus): MecOrdenTrabajoModel
    {
        return MecOrdenTrabajoModel::create([
            'Folio' => $folio,
            'FolioParo' => $folioParo,
            'Estatus' => $estatus,
        ]);
    }

    private function crearRenglones(string $folio, int $cuantos): void
    {
        for ($i = 0; $i < $cuantos; $i++) {
            DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')->insert(['Folio' => $folio]);
        }
    }

    /**
     * @return list<int|null>
     */
    private function calificacionesDe(string $folio): array
    {
        return DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')
            ->where('Folio', $folio)
            ->orderBy('Id')
            ->pluck('Calificacion')
            ->map(fn ($valor) => $valor === null ? null : (int) $valor)
            ->all();
    }
}
