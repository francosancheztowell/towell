<?php

namespace Tests\Unit\Calidad;

use App\Imports\ReqCalendarioTabImport;
use App\Models\Planeacion\ReqCalendarioTab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * 22-02s: ida y vuelta de Excel con maatwebsite/excel + phpspreadsheet despues de subirlos por
 * advisories. Cubre lo que los demas tests no: el import sincrono (Excel::import, el camino de
 * Calendarios, Velocidades, Telares...) y el export descargado (Excel::download) leido de vuelta.
 */
class ExcelDependenciasTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    public function test_import_sincrono_de_un_xlsx_real(): void
    {
        // El modelo usa 'dbo.ReqCalendarioTab': en sqlite el esquema dbo es una base adjunta.
        DB::statement("ATTACH DATABASE ':memory:' AS dbo");
        Schema::create('dbo.ReqCalendarioTab', function (Blueprint $tabla) {
            $tabla->string('CalendarioId', 20)->primary();
            $tabla->string('Nombre', 255)->nullable();
        });
        // truncate() en sqlite borra de dbo.sqlite_sequence, que solo existe si hay una tabla autoincremental.
        Schema::create('dbo.secuencia', fn (Blueprint $tabla) => $tabla->id());
        ReqCalendarioTab::create(['CalendarioId' => 'VIEJO', 'Nombre' => 'Se borra en BeforeImport']);

        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray([
            ['No Calendario', 'Nombre'],
            ['CAL-01', 'Calendario normal'],
            [null, null],
            ['CAL-02', 'Calendario ñandú'],
            ['CAL-03', null],
        ]);
        $ruta = $this->temporal('.xlsx');
        (new Xlsx($libro))->save($ruta);

        $import = new ReqCalendarioTabImport;
        Excel::import($import, $ruta);

        $this->assertSame(
            ['CAL-01' => 'Calendario normal', 'CAL-02' => 'Calendario ñandú'],
            ReqCalendarioTab::orderBy('CalendarioId')->pluck('Nombre', 'CalendarioId')->all()
        );
        $this->assertSame(2, $import->getStats()['procesados']);
        $this->assertSame([], $import->getStats()['errores']);
    }

    public function test_export_descargado_se_lee_de_vuelta(): void
    {
        $export = new class implements FromArray, WithHeadings
        {
            public function headings(): array
            {
                return ['Telar', 'Eficiencia', 'Fecha'];
            }

            public function array(): array
            {
                return [[201, 0.875, '2026-09-30'], [305, 1, 'ñ acentos é']];
            }
        };

        $respuesta = Excel::download($export, 'reporte.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $respuesta);
        $this->assertStringContainsString('attachment; filename=reporte.xlsx', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('spreadsheetml', (string) $respuesta->prepare(request())->headers->get('Content-Type'));

        $hoja = IOFactory::load($respuesta->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(
            [['Telar', 'Eficiencia', 'Fecha'], [201, 0.875, '2026-09-30'], [305, 1, 'ñ acentos é']],
            $hoja->toArray(null, false, false)
        );
    }

    /**
     * Sanidad del parche de los criticos de phpspreadsheet (SSRF/RCE en IOFactory::load y su bypass):
     * los stream wrappers se rechazan antes de tocar el archivo o la red.
     */
    public function test_iofactory_rechaza_stream_wrappers(): void
    {
        foreach (['phar://'.$this->temporal('.xlsx').'/x', "\x01phar://x.xlsx", 'zip://x.zip#phar://y'] as $nombre) {
            try {
                IOFactory::load($nombre);
                $this->fail("IOFactory::load aceptó {$nombre}");
            } catch (SpreadsheetException $e) {
                $this->assertStringContainsString('Disallowed stream wrapper', $e->getMessage());
            }
        }
    }

    private function temporal(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'dep');
        array_push($this->temporales, $base, $base.$extension);

        return $base.$extension;
    }
}
