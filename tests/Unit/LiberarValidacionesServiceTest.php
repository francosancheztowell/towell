<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarValidacionesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Folio único y métricas de producción extraídos de LiberarOrdenesController.
 * Misma entrada → misma salida: mensajes 422 idénticos.
 */
class LiberarValidacionesServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarValidacionesService $validaciones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('ItemId')->nullable();
        });

        $this->crearCatCodificadosEstandar();

        $this->validaciones = new LiberarValidacionesService;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    private function crearCatCodificadosEstandar(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
        });
    }

    private function registroPrograma(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->Id = $attrs['Id'] ?? 10;
        $r->NoTelarId = $attrs['NoTelarId'] ?? '201';
        $r->NombreProducto = $attrs['NombreProducto'] ?? 'MB-ARIA';
        $r->ItemId = $attrs['ItemId'] ?? 'IT100';
        $r->NoTiras = $attrs['NoTiras'] ?? 4;
        $r->SaldoPedido = $attrs['SaldoPedido'] ?? 500;
        $r->Repeticiones = $attrs['Repeticiones'] ?? 10;
        $r->SaldoMarbete = $attrs['SaldoMarbete'] ?? 5;
        $r->MtsRollo = $attrs['MtsRollo'] ?? 12.5;
        $r->PzasRollo = $attrs['PzasRollo'] ?? 100;
        $r->TotalRollos = $attrs['TotalRollos'] ?? 5;
        $r->TotalPzas = $attrs['TotalPzas'] ?? 500;
        $r->PesoCrudo = $attrs['PesoCrudo'] ?? 100;
        $r->Ancho = $attrs['Ancho'] ?? 30;
        $r->LargoCrudo = $attrs['LargoCrudo'] ?? 50;
        $r->Densidad = $attrs['Densidad'] ?? 1.5;

        return $r;
    }

    public function test_folio_vacio_o_solo_espacios_no_se_puede_validar(): void
    {
        $registro = $this->registroPrograma();

        $this->assertSame(
            'No se pudo validar el número de orden.',
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('   ', $registro)
        );
        $this->assertSame(
            'No se pudo validar el número de orden.',
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('', $registro)
        );
    }

    public function test_folio_ya_asignado_en_otro_renglon_del_programa(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            ['NoProduccion' => '77001', 'NoTelarId' => '305', 'NombreProducto' => 'OTRA', 'ItemId' => 'IT9'],
            ['NoProduccion' => null, 'NoTelarId' => '201', 'NombreProducto' => 'MB-ARIA', 'ItemId' => 'IT100'],
        ]);

        $actual = ReqProgramaTejido::query()->where('NoTelarId', '201')->first();
        $this->assertNotNull($actual);

        $this->assertSame(
            'El número de orden "77001" ya está asignado en otro registro del programa de tejido.',
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }

    public function test_mismo_folio_en_el_mismo_renglon_del_programa_no_es_duplicado(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '77001',
            'NoTelarId' => '201',
            'NombreProducto' => 'MB-ARIA',
            'ItemId' => 'IT100',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);
        $this->assertNotNull($actual);

        $this->assertNull(
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }

    public function test_folio_en_codificados_del_mismo_telar_es_valido(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => null,
            'NoTelarId' => '201',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);

        $this->assertNull(
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }

    public function test_folio_en_codificados_de_otro_telar_se_rechaza(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => null,
            'NoTelarId' => '201',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '305',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);

        $this->assertSame(
            'El número de orden "77001" ya existe en codificados para otro telar.',
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }

    public function test_folio_en_codificados_sin_columna_de_telar_se_rechaza(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
        });

        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => null,
            'NoTelarId' => '201',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);
        $servicio = new LiberarValidacionesService;

        $this->assertSame(
            'El número de orden "77001" ya existe en catálogo codificados.',
            $servicio->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }

    public function test_sin_columna_clave_en_codificados_no_bloquea(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TelarId')->nullable();
        });

        $registro = $this->registroPrograma();
        $servicio = new LiberarValidacionesService;

        $this->assertNull(
            $servicio->validarOrdenTejidoUnicoParaLiberacion('77001', $registro)
        );
    }

    public function test_folio_libre_es_valido(): void
    {
        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => null,
            'NoTelarId' => '201',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);

        $this->assertNull(
            $this->validaciones->validarOrdenTejidoUnicoParaLiberacion('88001', $actual)
        );
    }

    public function test_error_al_consultar_codificados_devuelve_mensaje_de_unicidad(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $mensaje, array $contexto): bool {
                return $mensaje === 'validarOrdenTejidoUnicoParaLiberacion'
                    && ($contexto['folio'] ?? null) === '77001';
            });

        $registro = $this->registroPrograma();
        $servicio = new LiberarValidacionesService;

        // SQLite: listar columnas de una tabla inexistente no lanza, solo devuelve [].
        // Se ceba el cache para entrar al query y forzar el catch (timeout/AX en prod).
        $cache = new \ReflectionProperty(LiberarValidacionesService::class, 'columnListingCache');
        $cache->setAccessible(true);
        $cache->setValue($servicio, [
            'CatCodificados' => ['OrdenTejido', 'TelarId'],
        ]);
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');

        $this->assertSame(
            'No se pudo validar la unicidad del número de orden en codificados.',
            $servicio->validarOrdenTejidoUnicoParaLiberacion('77001', $registro)
        );
    }

    public function test_metricas_completas_pasan(): void
    {
        $this->assertNull(
            $this->validaciones->validarMetricasProduccionParaLiberacion($this->registroPrograma())
        );
    }

    public function test_metricas_rechaza_tiras_cero_o_nulas(): void
    {
        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion(
            $this->registroPrograma(['NoTiras' => 0, 'NombreProducto' => 'Test', 'ItemId' => 'ITEM'])
        );

        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('tiras', (string) $msg);
        $this->assertStringContainsString('(Id 10 — Test / ITEM)', (string) $msg);
    }

    public function test_metricas_rechaza_saldo_pedido_cero(): void
    {
        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion(
            $this->registroPrograma(['SaldoPedido' => 0])
        );

        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('saldo pedido', (string) $msg);
    }

    public function test_metricas_rechaza_saldo_marbete_cero(): void
    {
        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion(
            $this->registroPrograma(['SaldoMarbete' => 0])
        );

        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('marbetes', (string) $msg);
    }

    public function test_metricas_rechaza_repeticiones_mts_pzas_rollos_y_piezas_en_cero(): void
    {
        $casos = [
            ['Repeticiones' => 0, 'fragmento' => 'Repeticiones'],
            ['MtsRollo' => 0, 'fragmento' => 'Metros x rollo'],
            ['PzasRollo' => 0, 'fragmento' => 'Pzas x rollo'],
            ['TotalRollos' => 0, 'fragmento' => 'Total rollos'],
            ['TotalPzas' => 0, 'fragmento' => 'Total piezas'],
        ];

        foreach ($casos as $caso) {
            $attrs = $caso;
            unset($attrs['fragmento']);
            $msg = $this->validaciones->validarMetricasProduccionParaLiberacion(
                $this->registroPrograma($attrs)
            );

            $this->assertNotNull($msg, 'Debió fallar por '.$caso['fragmento']);
            $this->assertStringContainsString($caso['fragmento'], (string) $msg);
        }
    }

    public function test_metricas_exige_densidad_si_hay_peso_ancho_y_largo(): void
    {
        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion(
            $this->registroPrograma(['Densidad' => 0])
        );

        $this->assertNotNull($msg);
        $this->assertStringContainsString('Densidad', (string) $msg);
    }

    public function test_metricas_no_exige_densidad_si_faltan_datos_para_calcularla(): void
    {
        $sinAncho = $this->registroPrograma(['Ancho' => null, 'Densidad' => null]);
        $this->assertNull($this->validaciones->validarMetricasProduccionParaLiberacion($sinAncho));

        $largoTexto = $this->registroPrograma(['LargoCrudo' => '50 Cms.', 'Densidad' => 1.2]);
        $this->assertNull($this->validaciones->validarMetricasProduccionParaLiberacion($largoTexto));
    }

    public function test_referencia_corta_con_y_sin_producto(): void
    {
        $conDatos = $this->registroPrograma(['Id' => 7, 'NombreProducto' => '  TOALLA  ', 'ItemId' => '  IT1  ']);
        $this->assertSame(' (Id 7 — TOALLA / IT1)', $this->validaciones->referenciaCortaRegistro($conDatos));

        $sinDatos = new ReqProgramaTejido;
        $sinDatos->Id = 3;
        $this->assertSame(' (Id 3)', $this->validaciones->referenciaCortaRegistro($sinDatos));
    }

    public function test_fallback_num_orden_cuando_no_existe_orden_tejido(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NumOrden')->nullable();
            $table->string('TelarId')->nullable();
        });

        $id = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => null,
            'NoTelarId' => '201',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'NumOrden' => '77001',
            'TelarId' => '999',
        ]);

        $actual = ReqProgramaTejido::query()->find($id);
        $servicio = new LiberarValidacionesService;

        $this->assertSame(
            'El número de orden "77001" ya existe en codificados para otro telar.',
            $servicio->validarOrdenTejidoUnicoParaLiberacion('77001', $actual)
        );
    }
}
