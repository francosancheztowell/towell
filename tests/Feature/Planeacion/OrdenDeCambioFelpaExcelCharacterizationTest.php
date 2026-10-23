<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\OrdenDeCambio\OrdenDeCambioFelpaExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Caracterización del Excel de Orden de Cambio antes de sacarlo del controller: congela
 * cada celda (valor o fórmula) de cada hoja que sale para un renglón por tipo de formato.
 * Sin tablas en BD: las búsquedas de ReqModelosCodificados/ReqPesosRollosTejido fallan
 * dentro de su try y devuelven null, igual que cuando no hay coincidencia.
 *
 * Para regenerar el fixture a propósito: borrar el .json y correr el test una vez.
 */
class OrdenDeCambioFelpaExcelCharacterizationTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../fixtures/planeacion/orden-cambio-felpa.json';

    public function test_el_excel_desde_bd_conserva_cada_celda(): void
    {
        $registros = collect([
            $this->registro(['Id' => 1, 'NoProduccion' => '1001', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '305', 'TamanoClave' => 'FELPA6808']),
            $this->registro(['Id' => 2, 'NoProduccion' => '1002', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '306', 'TamanoClave' => 'MB7217']),
            $this->registro(['Id' => 3, 'NoProduccion' => '1003', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'TamanoClave' => 'MB7217']),
            $this->registro(['Id' => 4, 'NoProduccion' => '1004', 'SalonTejidoId' => 'KARL MAYER', 'NoTelarId' => '401', 'TamanoClave' => 'FELPA6808']),
        ]);

        $response = (new OrdenDeCambioFelpaExcel)->generarExcelDesdeBD($registros, false);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $celdas = $this->celdas($response);

        if (! file_exists(self::FIXTURE)) {
            file_put_contents(self::FIXTURE, json_encode($celdas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->markTestIncomplete('Fixture generado; correr de nuevo.');
        }

        // Se compara tras el mismo viaje por JSON que hizo el fixture (800.0 vuelve como 800).
        $this->assertSame(
            json_decode(file_get_contents(self::FIXTURE), true),
            json_decode(json_encode($celdas), true),
        );
    }

    public function test_sin_registro_en_la_plantilla_responde_400(): void
    {
        $response = (new OrdenDeCambioFelpaExcel)->generarExcel();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    private function registro(array $extra): ReqProgramaTejido
    {
        return (new ReqProgramaTejido)->forceFill($extra + [
            'ItemId' => '7001234',
            'InventSizeId' => 'MB',
            'NombreProducto' => 'TOALLA PRUEBA',
            'ProgramarProd' => '2026-10-01',
            'FechaFinal' => '2026-10-20 08:00:00',
            'TotalPedido' => 1200,
            'SaldoPedido' => 800,
            'SaldoMarbete' => 12,
            'PesoCrudo' => 450,
            'NoTiras' => 4,
            'Repeticiones' => 0,
            'LargoCrudo' => 150, // el programa no tiene LargoToalla: el largo del modelo vive en LargoCrudo
            'AnchoToalla' => 75,
            'CuentaRizo' => '1200',
            'CalibreRizo2' => '12',
            'VelocidadSTD' => 400,
            'EficienciaSTD' => 0.8,
        ]);
    }

    /** @return array<string, array<string, mixed>> hoja => [celda => valor] */
    private function celdas(StreamedResponse $response): array
    {
        ob_start();
        $response->sendContent();
        $binario = ob_get_clean();

        $ruta = tempnam(sys_get_temp_dir(), 'odc');
        file_put_contents($ruta, $binario);
        $libro = IOFactory::load($ruta);
        unlink($ruta);

        $salida = [];
        foreach ($libro->getAllSheets() as $hoja) {
            foreach ($hoja->getCoordinates() as $coord) {
                $valor = $hoja->getCell($coord)->getValue();
                if ($valor === null || $valor === '') {
                    continue;
                }
                // La hora de impresión sale de date(): no es parte del contrato.
                if (is_string($valor) && preg_match('/^\d{1,2}:\d{2} [ap]\. m\.$/', $valor)) {
                    $valor = '<hora>';
                }
                $salida[$hoja->getTitle()][$coord] = is_object($valor) ? (string) $valor : $valor;
            }
        }

        return $salida;
    }
}
