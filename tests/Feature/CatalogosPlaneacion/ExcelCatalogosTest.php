<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqTelares;
use App\Models\Planeacion\ReqVelocidadStd;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * Carga de Excel de los catálogos con el import REAL (maatwebsite + PhpSpreadsheet), no mocks:
 * el archivo se arma en el test con las columnas que usa Planeación.
 */
class ExcelCatalogosTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
    }

    /** @param  list<list<mixed>>  $filas */
    private function excel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas);
        $ruta = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_telares_crea_y_actualiza(): void
    {
        ReqTelares::create(['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Nombre' => 'Viejo', 'Grupo' => 'Z']);
        $archivo = $this->excel([['Salon', 'Telar', 'Nombre', 'Grupo'], ['JACQUARD', '201', 'JAC 201', 'A'], ['SMITH', '305', 'Smith 305', 'B']]);

        $this->post('/planeacion/telares/excel', ['archivo_excel' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, ReqTelares::count());
        $this->assertSame('B', ReqTelares::where('NoTelarId', '305')->value('Grupo'));
    }

    public function test_eficiencia_importa_con_densidad_por_defecto(): void
    {
        $archivo = $this->excel([['Salon', 'No Telar', 'Fibra', 'Eficiencia', 'Densidad'], ['SMITH', '300', 'H', 0.8, 'Alta'], ['SMITH', '301', 'PAP', 0.7, null]]);

        $this->post('/planeacion/eficiencia/excel', ['archivo_excel' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true, 'data' => ['registros_procesados' => 2, 'total_errores' => 0]]);
        $this->assertSame('Normal', ReqEficienciaStd::where('NoTelarId', '301')->value('Densidad'));
    }

    public function test_velocidad_importa(): void
    {
        $archivo = $this->excel([['Salon', 'NoTelar', 'Fibra', 'RPM', 'Densidad'], ['SMITH', '300', 'H', 850, 'Normal']]);

        $this->post('/planeacion/velocidad/excel', ['archivo_excel' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame(850, (int) ReqVelocidadStd::value('Velocidad'));
    }

    public function test_aplicaciones_carga_clave_nombre_y_factor(): void
    {
        // BUG-19-06b-2 corregido: el import pedía Salón y Telar (estructura vieja) y no leía Factor,
        // así que el formato vigente (Clave, Nombre, Factor) no cargaba nada.
        ReqAplicaciones::create(['AplicacionId' => 'EST', 'Nombre' => 'Viejo', 'Factor' => 1]);
        $archivo = $this->excel([['Clave', 'Nombre', 'Factor'], ['BOR', 'Bordado', 1.5], ['EST', 'Estampado', 2], ['', 'Sin clave', 1]]);

        $this->post('/planeacion/aplicaciones/excel', ['archivo_excel' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true, 'data' => [
                'registros_procesados' => 2, 'registros_creados' => 1, 'registros_actualizados' => 1, 'total_errores' => 1,
            ]]);
        $this->assertEquals(1.5, ReqAplicaciones::where('AplicacionId', 'BOR')->value('Factor'));
        $this->assertSame('Estampado', ReqAplicaciones::where('AplicacionId', 'EST')->value('Nombre'));
        $this->assertEquals(2, ReqAplicaciones::where('AplicacionId', 'EST')->value('Factor'));
    }

    public function test_archivo_que_no_es_excel_se_rechaza(): void
    {
        $this->post('/planeacion/telares/excel', ['archivo_excel' => UploadedFile::fake()->create('x.txt', 1, 'text/plain')], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJson(['success' => false]);
    }
}
