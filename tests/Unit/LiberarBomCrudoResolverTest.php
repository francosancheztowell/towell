<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarBomCrudoResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Comportamiento del resolver de L.Mat CRUDO extraído de LiberarOrdenesController.
 * Misma entrada → misma salida: ESTAND no autoasigna, versiones AX colapsan, sufijo '-1'
 * solo al final, y la query EXISTS no lista L.Mat de otro item.
 */
class LiberarBomCrudoResolverTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarBomCrudoResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('NAME')->nullable();
            $table->string('ITEMGROUPID')->nullable();
            $table->string('TWINVENTSIZEID')->nullable();
            $table->string('TWSALON')->nullable();
            $table->integer('Vigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('BOMVERSION', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('ITEMID');
        });

        $this->resolver = new LiberarBomCrudoResolver;
    }

    protected function tearDown(): void
    {
        foreach (['BOMTABLE', 'BOMVERSION'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    private function sembrarBom(
        string $bomId,
        string $itemId,
        string $inventSizeId,
        string $salon = 'JACQUARD',
        int $versiones = 1,
        int $vigente = 1,
        string $itemGroup = 'CRUDO'
    ): void {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA MATERIALES '.$bomId,
            'ITEMGROUPID' => $itemGroup,
            'TWINVENTSIZEID' => $inventSizeId,
            'TWSALON' => $salon,
            'Vigente' => $vigente,
        ]);
        for ($i = 0; $i < $versiones; $i++) {
            DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
                'BOMID' => $bomId,
                'ITEMID' => $itemId.'-1',
            ]);
        }
    }

    private function registro(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->ItemId = $attrs['ItemId'] ?? 'IT100';
        $r->InventSizeId = $attrs['InventSizeId'] ?? 'STD';
        $r->SalonTejidoId = $attrs['SalonTejidoId'] ?? 'JACQUARD';

        return $r;
    }

    public function test_item_id_sin_sufijo_solo_quita_guion_uno_final(): void
    {
        $this->assertSame('IT100', LiberarBomCrudoResolver::itemIdSinSufijo('IT100-1'));
        $this->assertSame('3-100', LiberarBomCrudoResolver::itemIdSinSufijo('3-100-1'));
        $this->assertSame('3-100-1-extra', LiberarBomCrudoResolver::itemIdSinSufijo('3-100-1-extra'));
        $this->assertSame('IT100', LiberarBomCrudoResolver::itemIdSinSufijo('IT100'));
    }

    public function test_es_bom_estandar_detecta_prefijo_sin_importar_caja(): void
    {
        $this->assertTrue(LiberarBomCrudoResolver::esBomEstandar('ESTAND JS 3060-3524'));
        $this->assertTrue(LiberarBomCrudoResolver::esBomEstandar('estand l 2524-2876'));
        $this->assertFalse(LiberarBomCrudoResolver::esBomEstandar('TEJ MB SD NAT'));
        $this->assertFalse(LiberarBomCrudoResolver::esBomEstandar('PRESTAND'));
    }

    public function test_bom_autoasignable_exige_exactamente_una_no_estandar(): void
    {
        $estand = ['bomId' => 'ESTAND JS 3060-3524', 'bomName' => 'ESTANDAR'];
        $propia = ['bomId' => 'TEJ MB SD NAT', 'bomName' => 'TEJIDO MB'];

        $this->assertNull(LiberarBomCrudoResolver::bomAutoAsignable([$estand]));
        $this->assertSame($propia, LiberarBomCrudoResolver::bomAutoAsignable([$propia]));
        $this->assertSame($propia, LiberarBomCrudoResolver::bomAutoAsignable([$estand, $propia]));
        $this->assertNull(LiberarBomCrudoResolver::bomAutoAsignable([
            $propia,
            ['bomId' => 'TEJ OTRA', 'bomName' => 'y'],
        ]));
        $this->assertNull(LiberarBomCrudoResolver::bomAutoAsignable([]));
    }

    public function test_normalizar_salon_traduce_variantes_ax(): void
    {
        $this->assertSame('JACQUARD', LiberarBomCrudoResolver::normalizarSalon('JACUARD'));
        $this->assertSame('SMIT', LiberarBomCrudoResolver::normalizarSalon('ITEMA'));
        $this->assertSame('KARL MAYER', LiberarBomCrudoResolver::normalizarSalon('KM'));
    }

    public function test_parsear_combinaciones_acepta_doble_dos_puntos_y_legado(): void
    {
        $pairs = $this->resolver->parsearCombinaciones('IT100::STD,IT200:FEL,IT300::XL::legado');

        $this->assertSame([
            ['itemIdWithSuffix' => 'IT100-1', 'inventSizeId' => 'STD'],
            ['itemIdWithSuffix' => 'IT200-1', 'inventSizeId' => 'FEL'],
            ['itemIdWithSuffix' => 'IT300-1', 'inventSizeId' => 'XL'],
        ], $pairs);
    }

    public function test_resolver_opciones_colapsa_versiones_ax_y_no_lista_otro_item(): void
    {
        $this->sembrarBom('BOM-MULTI-03', 'IT700', 'STD', 'JACQUARD', versiones: 3);
        $this->sembrarBom('BOM-AJENO-99', 'IT999', 'STD');

        $opciones = $this->resolver->resolverOpciones($this->registro([
            'ItemId' => 'IT700',
            'InventSizeId' => 'STD',
            'SalonTejidoId' => 'JACQUARD',
        ]));

        $this->assertCount(1, $opciones);
        $this->assertSame('BOM-MULTI-03', $opciones[0]['bomId']);
        $this->assertSame('LISTA MATERIALES BOM-MULTI-03', $opciones[0]['bomName']);
    }

    public function test_resolver_exacto_autoasigna_unica_propia_e_ignora_estand(): void
    {
        $this->sembrarBom('ESTAND JS 1', 'IT100', 'STD');
        $this->sembrarBom('TEJ PROPIA', 'IT100', 'STD');

        $exacto = $this->resolver->resolverExacto($this->registro());

        $this->assertNotNull($exacto);
        $this->assertSame('TEJ PROPIA', $exacto->bomId);
    }

    public function test_resolver_opciones_sin_item_talla_o_salon_devuelve_vacio(): void
    {
        $this->sembrarBom('BOM-X', 'IT100', 'STD');

        $this->assertSame([], $this->resolver->resolverOpciones($this->registro(['ItemId' => ''])));
        $this->assertSame([], $this->resolver->resolverOpciones($this->registro(['InventSizeId' => ''])));
        $this->assertSame([], $this->resolver->resolverOpciones($this->registro(['SalonTejidoId' => ''])));
    }

    public function test_buscar_por_item_respeta_salon_y_fallback_no_cambia_de_item(): void
    {
        $this->sembrarBom('BOM-JAC', 'IT100', 'STD', 'JACQUARD');
        $this->sembrarBom('BOM-SMIT', 'IT100', 'OTRA', 'ITEMA');
        $this->sembrarBom('BOM-AJENO', 'IT999', 'STD', 'JACQUARD');

        $exactas = $this->resolver->buscarPorItem('IT100', 'STD', 'JACQUARD');
        $this->assertCount(1, $exactas);
        $this->assertSame('BOM-JAC', $exactas[0]->bomId);

        $conFallback = $this->resolver->buscarPorItem('IT100', 'NO-EXISTE', 'JACQUARD', '', true);
        $ids = $conFallback->pluck('bomId')->all();
        $this->assertContains('BOM-JAC', $ids);
        $this->assertContains('BOM-SMIT', $ids);
        $this->assertNotContains('BOM-AJENO', $ids);
    }

    public function test_opciones_por_combinaciones_omite_estand_y_colapsa_duplicados(): void
    {
        $this->sembrarBom('TEJ PROPIA', 'IT100', 'STD', 'JACQUARD', versiones: 2);
        $this->sembrarBom('ESTAND JS 1', 'IT100', 'STD');

        $map = $this->resolver->opcionesPorCombinaciones([
            ['itemIdWithSuffix' => 'IT100-1', 'inventSizeId' => 'STD'],
        ]);

        $this->assertArrayHasKey('IT100|STD', $map);
        $this->assertCount(1, $map['IT100|STD']);
        $this->assertSame('TEJ PROPIA', $map['IT100|STD'][0]['bomId']);
    }

    public function test_precargar_sirve_opciones_desde_cache_sin_segunda_consulta_por_renglon(): void
    {
        $this->sembrarBom('BOM-A', 'IT100', 'STD', 'JACQUARD');
        $this->sembrarBom('BOM-B', 'IT200', 'STD', 'ITEMA');

        $lote = collect([
            $this->registro(['ItemId' => 'IT100', 'SalonTejidoId' => 'JACQUARD']),
            $this->registro(['ItemId' => 'IT200', 'SalonTejidoId' => 'SMIT']),
        ]);

        $this->resolver->precargar($lote);

        DB::connection('sqlsrv_ti')->flushQueryLog();
        DB::connection('sqlsrv_ti')->enableQueryLog();

        $opA = $this->resolver->resolverOpciones($lote[0]);
        $opB = $this->resolver->resolverOpciones($lote[1]);

        $this->assertSame([], DB::connection('sqlsrv_ti')->getQueryLog());
        $this->assertSame('BOM-A', $opA[0]['bomId']);
        $this->assertSame('BOM-B', $opB[0]['bomId']);
    }

    public function test_salones_validos_por_bom_normaliza_variante_ax(): void
    {
        $this->sembrarBom('BOM-ITEMA-01', 'IT300', 'STD', 'ITEMA');

        $map = $this->resolver->salonesValidosPorBomIds(['BOM-ITEMA-01']);

        $this->assertSame(['SMIT'], $map['IT300|STD|BOM-ITEMA-01']);
    }

    public function test_query_exists_no_devuelve_bom_sin_version_del_item(): void
    {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => 'BOM-HUERFANO',
            'NAME' => 'SIN VERSION',
            'ITEMGROUPID' => 'CRUDO',
            'TWINVENTSIZEID' => 'STD',
            'TWSALON' => 'JACQUARD',
            'Vigente' => 1,
        ]);

        $rows = $this->resolver->query('IT100', 'STD', 'JACQUARD')->get();
        $this->assertCount(0, $rows);
    }
}
