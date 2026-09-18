<?php

namespace Tests\Feature;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CodificacionFormularioTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useSqlsrvSqlite();
        $this->createAuthTable();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->actingAs($this->createUsuario(['area' => 'Planeacion']));
    }

    public function test_el_formulario_de_karl_mayer_pinta_valores_y_las_cuatro_barras(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'ALB7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'Nombre' => 'KM 3 CENEFAS II',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'FlogsId' => 'CE-MAR24-FPERE-F000667',
            'CuentaBarra1' => '2028',
            'CalibreBarra1' => '370',
            'FibraBarra1' => 'FIL. 370 VOLUMINIZADO',
            'CuentaBarra2' => '2104',
            'CalibreBarra2' => '75',
            'FibraBarra2' => 'FIL',
            'CuentaRizo' => '2028',
        ]);

        $html = $this->get('/planeacion/catalogos/codificacion-modelos/'.$modelo->Id.'/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="ALB7576"', $html);
        $this->assertStringContainsString('value="KM 3 CENEFAS II"', $html);
        $this->assertStringContainsString('name="CuentaBarra1"', $html);
        $this->assertStringContainsString('value="2028"', $html);
        $this->assertStringContainsString('FIL. 370 VOLUMINIZADO', $html);
        $this->assertStringContainsString('Construcción · cuatro barras', $html);
        $this->assertStringNotContainsString('sin capturar', $html);
        $this->assertDoesNotMatchRegularExpression('/data-solo-salon="km"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-solo-salon="std"[^>]*\bhidden\b/', $html);
        $this->assertStringNotContainsString('name="ColumCT"', $html);
        $this->assertStringNotContainsString('COMPROBAR modelos duplicados', $html);
        $this->assertStringNotContainsString('Datos cargados', $html);
        $this->assertStringContainsString('no tiene telar', $html);
        $this->assertStringNotContainsString('todavía tiene cuenta en rizo', $html);
        $this->assertMatchesRegularExpression('/data-solo-salon="std"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('Construcción · cuatro barras', $html);
    }

    public function test_karl_mayer_con_barras_vacias_avisa_rizo_sobrante(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'ALB0001',
            'OrdenTejido' => '1',
            'SalonTejidoId' => 'KARL MAYER',
            'CuentaRizo' => '2028',
            'CuentaPie' => '920',
        ]);

        $html = $this->get('/planeacion/catalogos/codificacion-modelos/'.$modelo->Id.'/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('todavía tiene cuenta en rizo', $html);
        $this->assertStringNotContainsString('sin capturar', $html);
    }

    public function test_el_formulario_jacquard_muestra_rizo_y_oculta_barras(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'JAC1001',
            'OrdenTejido' => '100',
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'Nombre' => 'Toalla Jacquard',
            'CuentaRizo' => '1840',
            'CuentaPie' => '920',
        ]);

        $html = $this->get('/planeacion/catalogos/codificacion-modelos/'.$modelo->Id.'/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Construcción · rizo, pie y trama', $html);
        $this->assertStringContainsString('value="1840"', $html);
        $this->assertStringContainsString('>Rizo</span>', $html);
        $this->assertStringContainsString('>Pie</span>', $html);
        $this->assertStringContainsString('>Trama</span>', $html);
        $this->assertStringNotContainsString('>Cenefa</span>', $html);
        $this->assertStringContainsString('Med. de cenefa', $html);
        $this->assertStringNotContainsString('Jacquard y Smit no usan barras', $html);
        $this->assertMatchesRegularExpression('/data-solo-salon="km"[^>]*\bhidden\b/', $html);
    }

    public function test_alta_pide_salon_antes_de_mostrar_construccion(): void
    {
        $html = $this->get('/planeacion/catalogos/codificacion-modelos/create')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-solo-salon="km"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('Clave AX', $html);
        $this->assertStringContainsString('id="cod-traer-similar"', $html);
        $this->assertStringContainsString('id="cod-modal-similar"', $html);
        $this->assertStringContainsString('ModelosCodificados', $html);
        $this->assertStringContainsString('Codificacion', $html);
        $this->assertStringNotContainsString('Esta tabla', $html);
        $this->assertStringNotContainsString('CatCodificados', $html);
        $this->assertStringContainsString('Clave modelo', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringNotContainsString('De estos campos cuelgan los cálculos', $html);
        $this->assertStringContainsString('.cod-grid--id { grid-template-columns: repeat(5, minmax(0, 1fr)); }', $html);
        $this->assertStringContainsString('.cod-id-resto { display: contents; }', $html);
        $this->assertStringNotContainsString('<h2>Identificación</h2>', $html);
        $this->assertStringNotContainsString('id="cod-identity"', $html);
        $this->assertStringNotContainsString('Si hay Clave AX y Tamaño, se busca solo', $html);
        $this->assertStringNotContainsString('El programa de tejido recalcula', $html);
        $this->assertStringContainsString('maxlength="20"', $html);
        $this->assertStringNotContainsString('href="#sec-fechas"', $html);
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*>Item ID</', $html);
        $this->assertMatchesRegularExpression('/name="TamanoClave"[^>]*type="hidden"|type="hidden"[^>]*name="TamanoClave"/', $html);
        $this->assertMatchesRegularExpression('/name="ClaveModelo"[^>]*type="hidden"|type="hidden"[^>]*name="ClaveModelo"/', $html);
        $this->assertMatchesRegularExpression('/name="OrdenTejido"[^>]*type="hidden"|type="hidden"[^>]*name="OrdenTejido"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*>Orden Tejido</', $html);
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*>Velocidad STD</', $html);
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*>Cat\. Calidad</', $html);
        $this->assertStringContainsString('>Calidad</label>', $html);
        $this->assertStringContainsString('NAC - 1', $html);
        $this->assertStringContainsString('NAC - 3', $html);
        $this->assertStringContainsString('name="Rasurado"', $html);
        $this->assertStringContainsString('name="CambioRepaso"', $html);
        $this->assertMatchesRegularExpression('/name="Rasurado"[^>]*>[\s\S]*<option value="SI"/', $html);
        $this->assertMatchesRegularExpression('/name="CambioRepaso"[^>]*>[\s\S]*<option value="NO"/', $html);
    }

    public function test_duplicar_marca_los_campos_que_hay_que_cambiar(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'ALB7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'Nombre' => 'KM 3 CENEFAS II',
        ]);

        $html = $this->get('/planeacion/catalogos/codificacion-modelos/create?duplicate='.$modelo->Id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Duplicado de un modelo existente', $html);
        $this->assertStringContainsString('value="ALB7576"', $html);
        $this->assertStringContainsString('name="Id" value=""', $html);
    }

    public function test_crear_exige_salon_y_persiste_color_de_trama_y_barras(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'TamanoClave' => 'ALB7576',
            'OrdenTejido' => '36440',
        ])->assertStatus(422)->assertJsonValidationErrors(['SalonTejidoId']);

        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'TamanoClave' => 'ALB7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'CodColorTrama' => 'TR01',
            'ColorTrama' => 'CRUDO',
            'CuentaRizo' => '9999',
            'CuentaBarra1' => '2028',
            'CalibreBarra1' => '370',
            'FibraBarra1' => 'FIL. 370 VOLUMINIZADO',
        ])->assertCreated();

        $this->assertDatabaseHas('ReqModelosCodificados', [
            'TamanoClave' => 'ALB7576',
            'SalonTejidoId' => 'KARL MAYER',
            'CuentaBarra1' => '2028',
            'FibraBarra1' => 'FIL. 370 VOLUMINIZADO',
        ]);

        $row = ReqModelosCodificados::where('TamanoClave', 'ALB7576')->first();
        $this->assertNull($row->ColorTrama);
        $this->assertNull($row->CodColorTrama);
        $this->assertNull($row->CuentaRizo);
    }

    public function test_jacquard_guarda_rizo_y_trama_y_descarta_barras(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'TamanoClave' => 'JAC1001',
            'OrdenTejido' => '100',
            'SalonTejidoId' => 'JACQUARD',
            'ItemId' => 'JAC',
            'InventSizeId' => '1001',
            'CuentaRizo' => '1840',
            'CuentaPie' => '920',
            'ColorTrama' => 'CRUDO',
            'CuentaBarra1' => '2028',
            'FibraBarra1' => 'NO DEBE QUEDAR',
        ])->assertCreated();

        $row = ReqModelosCodificados::where('TamanoClave', 'JAC1001')->first();
        $this->assertSame('1840', (string) $row->CuentaRizo);
        $this->assertSame('CRUDO', $row->ColorTrama);
        $this->assertNull($row->CuentaBarra1);
        $this->assertNull($row->FibraBarra1);
    }

    public function test_alta_oculta_trama_y_el_resto_hasta_elegir_salon(): void
    {
        $html = $this->get('/planeacion/catalogos/codificacion-modelos/create')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-solo-salon="std"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/id="sec-fechas"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-solo-salon="km"[^>]*\bhidden\b/', $html);
    }

    public function test_la_vista_comparte_los_datos_del_registro(): void
    {
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes([
            'Id' => 39380,
            'TamanoClave' => 'ALB7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'Nombre' => 'KM 3 CENEFAS II',
        ], true);
        $modelo->exists = true;

        $html = View::make('catalagos.codificacion-form', [
            'codificacion' => $modelo,
            'esDuplicado' => false,
        ])->render();

        $this->assertStringContainsString('value="ALB7576"', $html);
        $this->assertStringContainsString('KARL MAYER', $html);
    }

    public function test_salones_api_incluye_karl_mayer_aunque_la_secuencia_diga_km(): void
    {
        $schema = Schema::connection(config('database.default'));
        if (! $schema->hasTable('InvSecuenciaTelares')) {
            $schema->create('InvSecuenciaTelares', function (Blueprint $table) {
                $table->string('TipoTelar')->nullable();
                $table->string('NoTelar')->nullable();
                $table->integer('Secuencia')->nullable();
            });
        }

        DB::table('InvSecuenciaTelares')->insert([
            ['TipoTelar' => 'KM', 'NoTelar' => '401', 'Secuencia' => 1],
            ['TipoTelar' => 'JACQUARD', 'NoTelar' => '201', 'Secuencia' => 2],
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/salones-telares')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertContains('KARL MAYER', $json['salones']);
        $this->assertNotContains('KM', $json['salones']);
        $this->assertContains('401', $json['telaresPorSalon']['KARL MAYER']);
        $this->assertContains('402', $json['telaresPorSalon']['KARL MAYER']);
    }

    public function test_el_spinner_de_crear_empieza_oculto(): void
    {
        $html = $this->get('/planeacion/catalogos/codificacion-modelos/create')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="cod-submit-spin"[^>]*\bhidden\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="cod-submit-spin"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
    }

    public function test_crear_arma_tamano_clave_con_clave_ax_y_tamano(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'CuentaBarra1' => '2028',
        ])->assertCreated();

        $this->assertDatabaseHas('ReqModelosCodificados', [
            'OrdenTejido' => '36440',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'TamanoClave' => '7576FEL',
            'ClaveModelo' => '7576FEL',
        ]);
    }

    public function test_trae_modelo_similar_desde_catcodificados_por_orden(): void
    {
        $this->createTablaDesdeModelo(CatCodificados::class);
        CatCodificados::query()->create([
            'OrdenTejido' => '36440',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'ClaveModelo' => '7576FEL',
            'Nombre' => 'KM 3 CENEFAS II',
            'CuentaBarra1' => '2028',
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/catcodificados-orden?orden=36440')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame('7576', $json['clave_ax']);
        $this->assertSame('FEL', $json['tamano']);
        $this->assertSame('KM 3 CENEFAS II', $json['nombre']);
        $this->assertSame('2028', (string) $json['campos']['CuentaBarra1']);
    }

    public function test_modelo_similar_desde_esta_tabla_por_orden(): void
    {
        ReqModelosCodificados::create([
            'TamanoClave' => '7576FEL',
            'ClaveModelo' => '7576FEL',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'Nombre' => 'KM 3 CENEFAS II',
            'CuentaBarra1' => '2028',
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=req&por=orden&valor=36440')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame('req', $json['origen']);
        $this->assertSame('KM 3 CENEFAS II', $json['nombre']);
        $this->assertSame('2028', (string) $json['campos']['CuentaBarra1']);
    }

    public function test_modelo_similar_desde_catcodificados_por_clave(): void
    {
        $this->createTablaDesdeModelo(CatCodificados::class);
        CatCodificados::query()->create([
            'OrdenTejido' => '111',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'ClaveModelo' => '7576FEL',
            'Nombre' => 'Desde cat',
            'CuentaRizo' => '1840',
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=cat&por=clave&valor=7576FEL')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame('cat', $json['origen']);
        $this->assertSame('Desde cat', $json['nombre']);
        $this->assertSame('1840', (string) $json['campos']['CuentaRizo']);
    }

    public function test_km_sin_barras_copia_rizo_pie_y_c1_a_barra_1_a_4(): void
    {
        $this->createTablaDesdeModelo(CatCodificados::class);
        CatCodificados::query()->create([
            'OrdenTejido' => '36440',
            'Departamento' => 'KARL MAYER',
            'TelarId' => '401',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'ClaveModelo' => '7576FEL',
            'Nombre' => 'KM viejo',
            'CuentaRizo' => '2028',
            'CalibreRizo' => '370',
            'FibraRizo' => 'FIL RIZO',
            'CuentaPie' => '2104',
            'FibraPie' => 'FIL PIE',
            'CalibreComb1' => '75',
            'FibraComb1' => 'C1',
            'NomColorC1' => 'CRUDO',
            'CalibreComb2' => '40',
            'FibraComb2' => 'C2',
            'CalibreRizo2' => '15.949999999999999',
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=cat&por=orden&valor=36440&salon=KARL MAYER')
            ->assertOk()
            ->json('data');

        $this->assertSame('2028', (string) $json['campos']['CuentaBarra1']);
        $this->assertSame('FIL RIZO', $json['campos']['FibraBarra1']);
        $this->assertSame('2104', (string) $json['campos']['CuentaBarra2']);
        $this->assertSame('C1', $json['campos']['FibraBarra3']);
        $this->assertSame('CRUDO', $json['campos']['ColorBarra3']);
        $this->assertSame('C2', $json['campos']['FibraBarra4']);
        $this->assertEqualsWithDelta(15.95, (float) $json['campos']['CalibreRizo2'], 0.001);
    }

    public function test_km_con_barras_no_copia_rizo_ni_pie(): void
    {
        $this->createTablaDesdeModelo(CatCodificados::class);
        CatCodificados::query()->create([
            'OrdenTejido' => '555',
            'Departamento' => 'KARL MAYER',
            'ItemId' => '1',
            'InventSizeId' => 'A',
            'ClaveModelo' => '1A',
            'CuentaBarra1' => '1111',
            'FibraBarra1' => 'BARRA',
            'CuentaRizo' => '9999',
            'FibraRizo' => 'NO',
        ]);

        $json = $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=cat&por=orden&valor=555&salon=KARL MAYER')
            ->assertOk()
            ->json('data');

        $this->assertSame('1111', (string) $json['campos']['CuentaBarra1']);
        $this->assertSame('BARRA', $json['campos']['FibraBarra1']);
        $this->assertNotSame('9999', (string) $json['campos']['CuentaBarra1']);
    }

    public function test_guardar_km_con_rizo_viejo_lo_pasa_a_barras(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'OrdenTejido' => '777',
            'SalonTejidoId' => 'KARL MAYER',
            'ItemId' => '88',
            'InventSizeId' => 'B',
            'CuentaRizo' => '2028',
            'FibraRizo' => 'FIL RIZO',
            'CuentaPie' => '920',
        ])->assertCreated();

        $row = ReqModelosCodificados::where('OrdenTejido', '777')->first();
        $this->assertSame('2028', (string) $row->CuentaBarra1);
        $this->assertSame('FIL RIZO', $row->FibraBarra1);
        $this->assertSame('920', (string) $row->CuentaBarra2);
        $this->assertNull($row->CuentaRizo);
        $this->assertNull($row->CuentaPie);
    }

    public function test_calibres_se_guardan_a_dos_decimales(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', [
            'OrdenTejido' => '200',
            'SalonTejidoId' => 'JACQUARD',
            'ItemId' => '10',
            'InventSizeId' => 'A',
            'CalibreRizo' => '370.0',
            'CalibreRizo2' => '15.949999999999999',
            'CalibrePie2' => '70.919998000000007',
        ])->assertCreated();

        $row = ReqModelosCodificados::where('OrdenTejido', '200')->first();
        $this->assertEqualsWithDelta(370.0, (float) $row->CalibreRizo, 0.001);
        $this->assertEqualsWithDelta(15.95, (float) $row->CalibreRizo2, 0.001);
        $this->assertEqualsWithDelta(70.92, (float) $row->CalibrePie2, 0.001);

        $html = $this->get('/planeacion/catalogos/codificacion-modelos/'.$row->Id.'/edit')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="15.95"', $html);
        $this->assertStringContainsString('value="70.92"', $html);
        $this->assertStringNotContainsString('15.949999999999999', $html);
    }

    public function test_modelo_similar_exige_origen_por_y_valor(): void
    {
        $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=req&por=orden')
            ->assertStatus(422);
        $this->getJson('/planeacion/catalogos/codificacion-modelos/modelo-similar?origen=otro&por=orden&valor=1')
            ->assertStatus(422);
    }
}
