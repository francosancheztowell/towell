<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarCodigoDibujoResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Resolución de Código de Dibujo extraída de LiberarOrdenesController.
 * Misma entrada → misma salida: pantalla gana; si no, CatCodificados
 * (Item+Departamento, luego Item+talla+Departamento, luego Item+talla).
 */
class LiberarCodigoDibujoResolverTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarCodigoDibujoResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
        });

        $this->resolver = new LiberarCodigoDibujoResolver;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        parent::tearDown();
    }

    private function sembrar(array $attrs): int
    {
        return (int) DB::connection('sqlsrv')->table('CatCodificados')->insertGetId([
            'ItemId' => $attrs['ItemId'] ?? 'IT100',
            'InventSizeId' => $attrs['InventSizeId'] ?? 'STD',
            'Departamento' => $attrs['Departamento'] ?? 'JACQUARD',
            'CodigoDibujo' => $attrs['CodigoDibujo'] ?? 'DIB-01',
        ]);
    }

    private function registro(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->ItemId = $attrs['ItemId'] ?? 'IT100';
        $r->InventSizeId = $attrs['InventSizeId'] ?? 'STD';
        $r->SalonTejidoId = $attrs['SalonTejidoId'] ?? 'JACQUARD';

        return $r;
    }

    public function test_resolver_prioriza_item_mas_departamento_aunque_la_talla_no_coincida(): void
    {
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => 'POR-SALON',
        ]);
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'SMIT',
            'CodigoDibujo' => 'OTRO-SALON',
        ]);
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'FEL',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => '',
        ]);

        $this->assertSame('POR-SALON', $this->resolver->resolver('IT100', 'FEL', 'JACQUARD'));
        $this->assertSame('OTRO-SALON', $this->resolver->resolver('IT100', 'STD', 'SMIT'));
        $this->assertNull($this->resolver->resolver('', '', ''));
    }

    public function test_resolver_cae_a_item_talla_y_departamento_si_el_salon_no_tiene_codigo(): void
    {
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'SMIT',
            'CodigoDibujo' => 'SOLO-SMIT',
        ]);
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'FEL',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => 'POR-TALLA',
        ]);

        $this->assertSame('POR-TALLA', $this->resolver->resolver('IT100', 'FEL', 'JACQUARD'));
    }

    public function test_resolver_cae_a_item_mas_talla_sin_salon(): void
    {
        $this->sembrar([
            'ItemId' => 'IT200',
            'InventSizeId' => 'XL',
            'Departamento' => 'SMIT',
            'CodigoDibujo' => 'SIN-SALON-UI',
        ]);

        $this->assertSame('SIN-SALON-UI', $this->resolver->resolver('IT200', 'XL', ''));
        $this->assertNull($this->resolver->resolver('IT200', 'NO-EXISTE', ''));
    }

    public function test_resolver_omite_filas_con_codigo_vacio_y_elige_el_id_mas_alto(): void
    {
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => 'VIEJO',
        ]);
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => '   ',
        ]);
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => 'NUEVO',
        ]);

        $this->assertSame('NUEVO', $this->resolver->resolver('IT100', 'STD', 'JACQUARD'));
    }

    public function test_para_liberacion_prioriza_el_codigo_explicito_de_pantalla(): void
    {
        $this->sembrar(['CodigoDibujo' => 'CATALOGO']);

        $this->assertSame(
            'GRILLA',
            $this->resolver->paraLiberacion(
                ['codigoDibujo' => '  GRILLA  '],
                $this->registro()
            )
        );
    }

    public function test_para_liberacion_usa_catalogo_si_la_grilla_viene_vacia(): void
    {
        $this->sembrar(['CodigoDibujo' => 'CATALOGO']);

        $this->assertSame(
            'CATALOGO',
            $this->resolver->paraLiberacion(['codigoDibujo' => '   '], $this->registro())
        );
        $this->assertSame(
            '',
            $this->resolver->paraLiberacion([], $this->registro(['ItemId' => 'IT999']))
        );
    }

    public function test_mapear_combinaciones_acepta_doble_dos_puntos_y_legado(): void
    {
        $this->sembrar([
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
            'CodigoDibujo' => 'DIB-100',
        ]);
        $this->sembrar([
            'ItemId' => 'IT200',
            'InventSizeId' => 'FEL',
            'Departamento' => 'SMIT',
            'CodigoDibujo' => 'DIB-200',
        ]);

        $map = $this->resolver->mapearCombinaciones('IT100::STD::JACQUARD,IT200:FEL,IT300::XL::SMIT');

        $this->assertSame('DIB-100', $map['IT100|STD|JACQUARD']);
        $this->assertSame('DIB-200', $map['IT200|FEL|']);
        $this->assertArrayNotHasKey('IT300|XL|SMIT', $map);
    }

    public function test_mapear_combinaciones_vacia_o_mal_formada_devuelve_vacio(): void
    {
        $this->assertSame([], $this->resolver->mapearCombinaciones(''));
        $this->assertSame([], $this->resolver->mapearCombinaciones('   ,  ,'));
        $this->assertSame([], $this->resolver->mapearCombinaciones('::STD::JACQUARD'));
    }
}
