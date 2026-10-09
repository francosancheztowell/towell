<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Livewire\Planeacion\ProgramaTejido\CatalogosDestino;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * Catálogo de Telares sobre URDCatalogoMaquinas: solo ve y toca los telares (Jacquard, Itema,
 * Smith, Karl Mayer); las máquinas de las demás áreas no se listan, no se editan ni se borran.
 */
class CatalogoTelaresTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => '201', 'Nombre' => 'Jacquard', 'Departamento' => 'Jacquard', 'Codificacion' => 'TOW-TEL201-TEJI', 'Secuencia' => 1],
            ['MaquinaId' => '300', 'Nombre' => 'Itema', 'Departamento' => 'Itema', 'Codificacion' => 'TOW-TEL300-TEJI', 'Secuencia' => 2],
            ['MaquinaId' => '305', 'Nombre' => 'Smith', 'Departamento' => 'Smith', 'Codificacion' => 'TOW-TEL305-TEJI', 'Secuencia' => 3],
            ['MaquinaId' => '401', 'Nombre' => 'Karl Mayer', 'Departamento' => 'Karl Mayer', 'Codificacion' => 'TOW-KM401-TEJI', 'Secuencia' => null],
            ['MaquinaId' => 'MC1', 'Nombre' => 'Mc Coy 1', 'Departamento' => 'Urdido', 'Codificacion' => null, 'Secuencia' => null],
            ['MaquinaId' => 'GENK', 'Nombre' => 'MONTACARGAS GENKINGER', 'Departamento' => 'Tejido', 'Codificacion' => null, 'Secuencia' => null],
        ]);
    }

    private function fila(string $id): ?object
    {
        return DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', $id)->first();
    }

    /** @param  list<list<mixed>>  $filas */
    private function excel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas);
        $ruta = tempnam(sys_get_temp_dir(), 'tel').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'telares.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_lista_los_cuatro_salones_y_ninguna_otra_maquina(): void
    {
        $html = $this->get('/planeacion/catalogos/telares')->assertOk()->getContent();

        foreach (['JAC 201', 'Smith 300', 'Smith 305', 'KM 401'] as $nombre) {
            $this->assertStringContainsString(">{$nombre}<", $html);
        }
        foreach (['>Itema<', '>Karl Mayer<'] as $salon) {
            $this->assertStringContainsString($salon, $html);
        }
        $this->assertStringNotContainsString('MC1', $html);
        $this->assertStringNotContainsString('GENK', $html);

        $config = $this->configDeVista($html);
        $this->assertSame(['Jacquard', 'Itema', 'Smith', 'Karl Mayer'], $config['campos'][0]['opciones']);
        $this->assertSame(['SalonTejidoId', 'NoTelarId'], array_column($config['campos'], 'nombre'));
    }

    public function test_filtra_por_salon_y_telar(): void
    {
        $html = $this->get('/planeacion/catalogos/telares?salon=Itema')->assertOk()->getContent();
        $this->assertStringContainsString('>300<', $html);
        $this->assertStringNotContainsString('>201<', $html);

        $html = $this->get('/planeacion/catalogos/telares?telar=40')->assertOk()->getContent();
        $this->assertStringContainsString('>401<', $html);
        $this->assertStringNotContainsString('>305<', $html);
    }

    public function test_alta_acepta_el_salon_como_se_escribia_y_arma_nombre_y_codificacion(): void
    {
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '212'])
            ->assertOk()->assertJson(['success' => true, 'message' => "Telar 'JAC 212' creado exitosamente"]);
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'KM', 'NoTelarId' => '403'])->assertOk();

        $this->assertSame(['Jacquard', 'Jacquard', 'TOW-TEL212-TEJI', null], array_values((array) collect($this->fila('212'))->only(['Nombre', 'Departamento', 'Codificacion', 'Secuencia'])->all()));
        $this->assertSame('Karl Mayer', $this->fila('403')->Departamento);
        $this->assertSame('TOW-KM403-TEJI', $this->fila('403')->Codificacion);
    }

    public function test_alta_rechaza_salon_desconocido_numero_repetido_o_de_otra_area(): void
    {
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'Urdido', 'NoTelarId' => '999'])
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'El salón debe ser Jacquard, Itema, Smith, Karl Mayer.']);
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => '201'])
            ->assertStatus(422)->assertJson(['message' => 'Ya existe un telar con ese número']);
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => 'MC1'])
            ->assertStatus(422)->assertJson(['message' => 'El número MC1 ya es una máquina de Urdido en el Catálogo de Máquinas']);

        $this->assertNull($this->fila('999'));
        $this->assertSame('Urdido', $this->fila('MC1')->Departamento);
    }

    public function test_editar_cambia_el_salon_y_conserva_secuencia_e_id(): void
    {
        $id = $this->fila('305')->Id;

        $this->putJson('/planeacion/telares/305', ['SalonTejidoId' => 'Itema', 'NoTelarId' => '305'])
            ->assertOk()->assertJson(['success' => true, 'message' => "Telar 'Smith 305' actualizado exitosamente"]);

        $fila = $this->fila('305');
        $this->assertSame(['Itema', 'Itema', 3, $id], [$fila->Departamento, $fila->Nombre, (int) $fila->Secuencia, $fila->Id]);
    }

    public function test_editar_respeta_un_nombre_propio(): void
    {
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', '201')->update(['Nombre' => 'Telar piloto']);

        $this->putJson('/planeacion/telares/201', ['SalonTejidoId' => 'Smith', 'NoTelarId' => '201'])->assertOk();

        $this->assertSame(['Smith', 'Telar piloto'], [$this->fila('201')->Departamento, $this->fila('201')->Nombre]);
    }

    public function test_editar_no_cambia_el_numero_ni_toca_otras_areas(): void
    {
        $this->putJson('/planeacion/telares/201', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => '202'])
            ->assertStatus(422)->assertJson(['success' => false]);
        $this->assertNotNull($this->fila('201'));
        $this->assertNull($this->fila('202'));

        $this->putJson('/planeacion/telares/MC1', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => 'MC1'])
            ->assertNotFound()->assertJson(['message' => 'Telar no encontrado']);
        $this->putJson('/planeacion/telares/999', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => '999'])->assertNotFound();
        $this->assertSame('Urdido', $this->fila('MC1')->Departamento);
    }

    public function test_baja_borra_el_telar_y_nunca_una_maquina_de_otra_area(): void
    {
        $this->deleteJson('/planeacion/telares/401')->assertOk()->assertJson(['message' => "Telar 'KM 401' eliminado exitosamente"]);
        $this->assertNull($this->fila('401'));

        $this->deleteJson('/planeacion/telares/MC1')->assertNotFound();
        $this->deleteJson('/planeacion/telares/GENK')->assertNotFound();
        $this->assertNotNull($this->fila('MC1'));
        $this->assertNotNull($this->fila('GENK'));
    }

    public function test_sin_permiso_no_escribe(): void
    {
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idrol', self::MODULOS['Telares'])
            ->update(['crear' => 0, 'modificar' => 0, 'eliminar' => 0]);
        Cache::flush();

        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'Jacquard', 'NoTelarId' => '216'])->assertForbidden();
        $this->deleteJson('/planeacion/telares/201')->assertForbidden();
        $this->assertNull($this->fila('216'));
        $this->assertNotNull($this->fila('201'));
    }

    public function test_excel_da_de_alta_corrige_salon_y_salta_lo_que_no_es_telar(): void
    {
        $archivo = $this->excel([
            ['Salon', 'Telar', 'Nombre', 'Grupo'],
            ['JACQUARD', '216', 'JAC 216', 'Jacquard Smith'],   // alta
            ['KM', '401', 'KM 401', 'KARL MAYER'],              // ya existe, mismo salón
            ['Smith', '300', 'Smith 300', 'Itema Nuevo'],       // Itema = Smith: no cambia
            ['Jacquard', '305', 'JAC 305', ''],                 // cambia de salón
            ['Jacquard', 'MC1', '', ''],                        // máquina de Urdido: no se toca
            ['Sulzer', '500', '', ''],                          // salón que no es de telares
            ['', '501', '', ''],                                // sin salón
            ['Salon', 'Telar', 'Nombre', 'Grupo'],              // encabezado repetido
            ['JACQUARD', '216', 'JAC 216', ''],                 // repetido en el archivo
        ]);

        $r = $this->post('/planeacion/telares/excel', ['archivo_excel' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['registros_procesados' => 5, 'registros_creados' => 1, 'registros_actualizados' => 4, 'registros_omitidos' => 4, 'total_errores' => 3],
            collect($r->json('data'))->only(['registros_procesados', 'registros_creados', 'registros_actualizados', 'registros_omitidos', 'total_errores'])->all());
        $this->assertSame(1, DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->where('MaquinaId', '216')->count());
        $this->assertSame('TOW-TEL216-TEJI', $this->fila('216')->Codificacion);
        $this->assertSame('Itema', $this->fila('300')->Departamento);
        $this->assertSame('Jacquard', $this->fila('305')->Departamento);
        $this->assertSame(3, (int) $this->fila('305')->Secuencia);
        $this->assertSame('Urdido', $this->fila('MC1')->Departamento);
        $this->assertNull($this->fila('500'));
        $this->assertNull($this->fila('501'));
    }

    public function test_los_consumidores_ven_el_alta_del_catalogo(): void
    {
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'Smith', 'NoTelarId' => '321'])->assertOk();

        $telares = CatalogosDestino::telares();
        $this->assertContains('321', $telares['SMIT']);
        $this->assertSame('Smith 321', URDCatalogoMaquina::find('321')->nombreTelar());
    }
}
