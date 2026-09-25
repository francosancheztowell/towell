<?php

namespace Tests\Unit\Helpers;

use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\CrearOrdenKarlMayerController;
use App\Http\Controllers\Tejedores\BPMTejedores\TelBpmController;
use App\Livewire\Mecanicos\VerificaMaquina\Index as VerificaMaquinaIndex;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\ProgramaUrdEng\CrearOrdenesService;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Caracterizacion de los folios de dbo.SSYSFoliosSecuencias antes y despues de
 * apuntarlos a FolioHelper (fase 20-01, ARQ-02).
 *
 * Las expectativas (texto del folio y consecutivo que queda) no se tocan al
 * deduplicar: si cambian, cambio la numeracion que ve la planta.
 *
 * lockForUpdate() es no-op en sqlite: el bloqueo se revisa leyendo el codigo.
 */
class FoliosCaracterizacionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        // En produccion la conexion por defecto es sqlsrv: los modelos
        // ($connection = 'sqlsrv') y DB::table() ven la misma base.
        config()->set('database.default', 'sqlsrv');

        $this->createTablaDbo('SSYSFoliosSecuencias', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'modulo' => 'TEXT',
            'prefijo' => 'TEXT',
            'consecutivo' => 'INTEGER',
        ]);

        // SSYSFoliosSecuencia::nextFolio() pregunta los nombres de columna a
        // INFORMATION_SCHEMA; en sqlite basta una base adjunta con ese nombre.
        $conexion = DB::connection();
        $conexion->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $conexion->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $conexion->table('INFORMATION_SCHEMA.COLUMNS')->insert([
                'TABLE_SCHEMA' => 'dbo',
                'TABLE_NAME' => 'SSYSFoliosSecuencias',
                'COLUMN_NAME' => $columna,
            ]);
        }
    }

    private function secuencia(string $modulo, string $prefijo, int $consecutivo, ?int $id = null): void
    {
        DB::table('dbo.SSYSFoliosSecuencias')->insert(array_filter([
            'Id' => $id,
            'modulo' => $modulo,
            'prefijo' => $prefijo,
            'consecutivo' => $consecutivo,
        ], fn ($v) => $v !== null));
    }

    private function consecutivo(string $modulo): ?int
    {
        $valor = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->value('consecutivo');

        return $valor === null ? null : (int) $valor;
    }

    private function invocarPrivado(object $objeto, string $metodo): mixed
    {
        return (new ReflectionMethod($objeto, $metodo))->invoke($objeto);
    }

    // ---------------------------------------------------------------
    // URD/ENG: CrearOrdenesService y CrearOrdenKarlMayerController
    // ---------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function origenesUrdEng(): array
    {
        return [
            'CrearOrdenesService' => ['servicio'],
            'CrearOrdenKarlMayerController' => ['karl-mayer'],
        ];
    }

    private function folioUrdEng(string $origen): string
    {
        $objeto = $origen === 'servicio'
            ? app(CrearOrdenesService::class)
            : new CrearOrdenKarlMayerController;

        return $this->invocarPrivado($objeto, 'obtenerFolioUrdEng');
    }

    #[DataProvider('origenesUrdEng')]
    public function test_urd_eng_incrementa_y_forma_prefijo_mas_cinco_digitos(string $origen): void
    {
        $this->secuencia('URD/ENG', 'U', 41);

        $this->assertSame('U00042', $this->folioUrdEng($origen));
        $this->assertSame(42, $this->consecutivo('URD/ENG'));
    }

    #[DataProvider('origenesUrdEng')]
    public function test_urd_eng_sin_modulo_usa_la_secuencia_id_14(string $origen): void
    {
        $this->secuencia('OtroNombre', 'X', 9, 14);

        $this->assertSame('X00010', $this->folioUrdEng($origen));
        $this->assertSame(10, $this->consecutivo('OtroNombre'));
    }

    #[DataProvider('origenesUrdEng')]
    public function test_urd_eng_sin_modulo_ni_id_14_lanza(string $origen): void
    {
        $this->expectException(\RuntimeException::class);

        $this->folioUrdEng($origen);
    }

    // ---------------------------------------------------------------
    // Trama: NuevoRequerimientoService::construirVm()
    // ---------------------------------------------------------------

    private function crearTablasTrama(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('InvSecuenciaTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelar')->nullable();
            $table->string('TipoTelar')->nullable();
            $table->integer('Secuencia')->nullable();
        });
        $schema->create('TejTrama', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Fecha')->nullable();
            $table->string('Status')->nullable();
            $table->string('Turno')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
        });
        $schema->create('TejTramaConsumos', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('FibraTrama')->nullable();
            $table->string('CodColorTrama')->nullable();
            $table->string('ColorTrama')->nullable();
            $table->float('Cantidad')->nullable();
        });
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
    }

    public function test_trama_nuevo_requerimiento_consume_el_folio_sugerido(): void
    {
        $this->crearTablasTrama();
        $this->secuencia('Trama', 'TR', 7);

        $vm = app(NuevoRequerimientoService::class)->construirVm(null);

        // El consecutivo es "el siguiente a usar": sale TR00007 y queda en 8.
        $this->assertSame('TR00007', $vm['folio']);
        $this->assertSame(8, $this->consecutivo('Trama'));
        $this->assertSame('En Proceso', DB::table('TejTrama')->where('Folio', 'TR00007')->value('Status'));
    }

    public function test_trama_sin_secuencia_inventa_un_folio_tr_y_no_crea_secuencia(): void
    {
        $this->crearTablasTrama();

        $vm = app(NuevoRequerimientoService::class)->construirVm(null);

        $this->assertMatchesRegularExpression('/^TR\d{5}$/', $vm['folio']);
        $this->assertSame(0, DB::table('dbo.SSYSFoliosSecuencias')->count());
        $this->assertTrue(DB::table('TejTrama')->where('Folio', $vm['folio'])->exists());
    }

    // ---------------------------------------------------------------
    // BPM Tejedores: TelBpmController::generarFolio()
    // ---------------------------------------------------------------

    private function crearTablaTelBpm(string ...$folios): void
    {
        Schema::connection('sqlsrv')->create('TelBPM', function (Blueprint $table) {
            $table->string('Folio')->primary();
        });
        foreach ($folios as $folio) {
            DB::table('TelBPM')->insert(['Folio' => $folio]);
        }
    }

    private function folioBpm(): string
    {
        return $this->invocarPrivado(new TelBpmController, 'generarFolio');
    }

    public function test_bpm_sin_secuencia_la_crea_sembrada_con_el_maximo_de_telbpm(): void
    {
        $this->crearTablaTelBpm('BT00003', 'BT00005');

        $this->assertSame('BT00006', $this->folioBpm());
        $fila = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', 'BPMTEjido')->first();
        $this->assertSame('BT', $fila->prefijo);
        $this->assertSame(6, (int) $fila->consecutivo);
    }

    public function test_bpm_sin_secuencia_ni_folios_empieza_en_uno(): void
    {
        $this->crearTablaTelBpm();

        $this->assertSame('BT00001', $this->folioBpm());
        $this->assertSame(1, $this->consecutivo('BPMTEjido'));
    }

    public function test_bpm_secuencia_atrasada_se_alinea_al_maximo_de_telbpm(): void
    {
        $this->crearTablaTelBpm('BT00010');
        $this->secuencia('BPMTEjido', 'BT', 2);

        $this->assertSame('BT00011', $this->folioBpm());
        $this->assertSame(11, $this->consecutivo('BPMTEjido'));
    }

    public function test_bpm_secuencia_adelantada_no_retrocede(): void
    {
        $this->crearTablaTelBpm('BT00003');
        $this->secuencia('BPMTEjido', 'BT', 10);

        $this->assertSame('BT00011', $this->folioBpm());
        $this->assertSame(11, $this->consecutivo('BPMTEjido'));
    }

    // ---------------------------------------------------------------
    // Mecanicos: asegurarSecuenciaFolios() en OT y Verifica Maquina
    // ---------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function secuenciasMecanicos(): array
    {
        return [
            'ordenes de trabajo' => ['ot', 'Mecanicos', 'MEC', 'MecOrdenTrabajoTable'],
            'verifica maquina' => ['vm', 'MecVerificaMaquina', 'VM', 'MecVerificaMaquinaTable'],
        ];
    }

    private function asegurarSecuenciaMecanicos(string $origen): void
    {
        $objeto = $origen === 'ot' ? new OrdenesTrabajoMecaController : new VerificaMaquinaIndex;

        $this->invocarPrivado($objeto, 'asegurarSecuenciaFolios');
    }

    private function crearTablaFolios(string $tabla, string ...$folios): void
    {
        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table) {
            $table->string('Folio')->primary();
        });
        foreach ($folios as $folio) {
            DB::table($tabla)->insert(['Folio' => $folio]);
        }
    }

    #[DataProvider('secuenciasMecanicos')]
    public function test_mecanicos_crea_la_secuencia_sembrada_con_el_ultimo_folio(string $origen, string $modulo, string $prefijo, string $tabla): void
    {
        $this->crearTablaFolios($tabla, $prefijo.'00004', $prefijo.'00012');

        $this->asegurarSecuenciaMecanicos($origen);

        $fila = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->first();
        $this->assertSame($prefijo, $fila->prefijo);
        $this->assertSame(12, (int) $fila->consecutivo);
    }

    #[DataProvider('secuenciasMecanicos')]
    public function test_mecanicos_sin_folios_crea_la_secuencia_en_cero(string $origen, string $modulo, string $prefijo, string $tabla): void
    {
        $this->crearTablaFolios($tabla);

        $this->asegurarSecuenciaMecanicos($origen);

        $this->assertSame(0, $this->consecutivo($modulo));
    }

    #[DataProvider('secuenciasMecanicos')]
    public function test_mecanicos_con_secuencia_no_la_toca(string $origen, string $modulo, string $prefijo, string $tabla): void
    {
        $this->crearTablaFolios($tabla, $prefijo.'00050');
        $this->secuencia($modulo, $prefijo, 3);

        $this->asegurarSecuenciaMecanicos($origen);

        $this->assertSame(3, $this->consecutivo($modulo));
        $this->assertSame(1, DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->count());
    }
}
