<?php

namespace Tests\Unit\Planeacion;

use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Services\Planeacion\ProgramaTejido\EdicionProgramaTejido;
use Carbon\Carbon;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Caracterización de la edición inline (aplicarCambios + recalcularDerivados): qué columnas
 * escribe cada campo, cómo copia el modelo codificado (tipos, recortes, guardas, Karl Mayer)
 * y los 422 de negocio. Los golden (tests/fixtures/planeacion/edicion-programa-tejido) se
 * generaron contra UpdateTejido (controller) antes de mover la lógica a EdicionProgramaTejido;
 * GOLDEN_UPDATE=1 los reescribe.
 */
class EdicionProgramaTejidoCharacterizationTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const CLASE = EdicionProgramaTejido::class;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 10:00:00');
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDesdeModelo(CatCodificados::class, ['cierre_ax']);
        $this->createTablaDesdeModelo(ReqProgramaTejidoLine::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Un valor distinto por columna del modelo: numérico según el cast, texto largo numérico si no. */
    private function crearModelo(string $salon, string $clave): void
    {
        $modelo = new ReqModelosCodificados;
        $casts = $modelo->getCasts();
        $valores = [];
        foreach (array_values($modelo->getFillable()) as $i => $col) {
            $tipo = strtok((string) ($casts[$col] ?? 'string'), ':');
            $valores[$col] = match ($tipo) {
                'integer', 'int' => $i + 1,
                'float' => $i + 0.25,
                'datetime' => null,
                default => ($i + 1).'.'.str_repeat('7', 70),
            };
        }
        $valores['TamanoClave'] = $clave;
        $valores['SalonTejidoId'] = $salon;
        $valores['FlogsId'] = 'RS-1234';
        $valores['Nombre'] = str_repeat('NOMBRE LARGO ', 8);
        $valores['CuentaRizo'] = '0';
        $valores['ColorTrama'] = '';
        $valores['CalibrePie2'] = null;
        ReqModelosCodificados::query()->insert($valores);
    }

    private function registro(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->forceFill(array_merge([
            'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'TamanoClave' => 'VIEJA',
            'CalibrePie2' => 9.5, 'CuentaBarra1' => 'B1', 'CuentaRizo' => 'R1', 'NoProduccion' => '500',
            'AplicacionId' => 'AP1', 'Produccion' => 100, 'TotalPedido' => 1000, 'SaldoPedido' => 900,
            'VelocidadSTD' => 300, 'EficienciaSTD' => 0.8, 'NoTiras' => 2, 'Luchaje' => 20,
            'PesoCrudo' => 450, 'LargoCrudo' => 70, 'AnchoToalla' => 50, 'Ancho' => 50,
        ], $attrs));
        $r->Id = 7;
        $r->syncOriginal();

        return $r;
    }

    /** @return array{flags: mixed, cambios: array<string, mixed>} */
    private function aplicar(ReqProgramaTejido $r, array $data): array
    {
        $flags = (self::CLASE)::aplicarCambios($r, $data);

        return ['flags' => $flags, 'cambios' => $r->getDirty()];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function rechazo(ReqProgramaTejido $r, array $data): array
    {
        try {
            (self::CLASE)::aplicarCambios($r, $data);
        } catch (MutacionRechazada $e) {
            return ['status' => $e->status, 'body' => $e->cuerpo];
        }

        $this->fail('Se esperaba MutacionRechazada para '.json_encode($data));
    }

    private function assertGolden(string $nombre, array $actual): void
    {
        $ruta = base_path("tests/fixtures/planeacion/edicion-programa-tejido/{$nombre}.json");
        $json = json_decode(json_encode($actual, JSON_PRESERVE_ZERO_FRACTION), true);
        if (getenv('GOLDEN_UPDATE') === '1') {
            @mkdir(dirname($ruta), 0777, true);
            file_put_contents($ruta, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n");
        }

        $this->assertSame(json_decode((string) file_get_contents($ruta), true), $json);
    }

    public function test_clave_modelo_copia_el_modelo_codificado(): void
    {
        $this->crearModelo('JACQUARD', 'CLAVE-1');

        $this->assertGolden('clave-jacquard', $this->aplicar($this->registro(), ['tamano_clave' => 'CLAVE-1']));
    }

    public function test_clave_modelo_respeta_los_campos_editados_en_el_mismo_put(): void
    {
        $this->crearModelo('JACQUARD', 'CLAVE-1');
        $data = [
            'velocidad_std' => '410', 'hilo' => 'H-PROPIO', 'tamano_clave' => 'CLAVE-1', 'no_tiras' => '3',
            'luchaje' => '25', 'peso_crudo' => '480', 'peine' => '60', 'largo_crudo' => '80', 'ancho' => '55',
            'rasurado' => 'SI', 'descripcion' => 'PROYECTO', 'idflog' => 'CE-0001',
        ];

        $this->assertGolden('clave-con-campos', $this->aplicar($this->registro(), $data));
    }

    public function test_clave_modelo_karl_mayer_limpia_la_construccion_estandar(): void
    {
        $this->crearModelo('KARL MAYER', 'KM-1');

        $this->assertGolden('clave-karl-mayer', $this->aplicar($this->registro(['SalonTejidoId' => 'KARL MAYER', 'NoTelarId' => '401']), ['tamano_clave' => 'KM-1']));
    }

    public function test_clave_vacia_solo_limpia(): void
    {
        $this->assertGolden('clave-vacia', $this->aplicar($this->registro(), ['tamano_clave' => null]));
    }

    public function test_campos_simples(): void
    {
        $data = [
            'calendario_id' => 'CAL-1', 'no_produccion' => ' 777 ', 'pedido' => '1500', 'programar_prod' => '2026-09-01',
            'idflog' => 'rs-55', 'descripcion' => '', 'aplicacion_id' => 'AP2', 'pt_vs_cte' => '3',
            'entrega_produc' => '2026-10-01', 'entrega_pt' => '2026-10-02 08:30', 'entrega_cte' => null,
            'fecha_final' => '2026-10-05 12:00', 'ancho_toalla' => '48', 'eficiencia_std' => null,
        ];

        $this->assertGolden('campos-simples', $this->aplicar($this->registro(), $data));
    }

    public function test_campos_nulos_y_aplicacion_igual(): void
    {
        $data = [
            'pedido' => null, 'no_produccion' => '', 'aplicacion_id' => 'AP1', 'programar_prod' => null, 'hilo' => '',
            'no_tiras' => null, 'peine' => null, 'ancho' => '', 'fecha_final' => null, 'entrega_pt' => 'no-es-fecha', 'calendario_id' => '',
        ];

        $this->assertGolden('campos-nulos', $this->aplicar($this->registro(), $data));
    }

    public function test_aplicacion_na_es_null(): void
    {
        $this->assertGolden('aplicacion-na', $this->aplicar($this->registro(), ['aplicacion_id' => 'NA']));
    }

    public function test_rechazos_de_negocio(): void
    {
        $this->crearModelo('SMIT', 'OTRA-1');
        CatCodificados::query()->insert(['OrdenTejido' => '36708', 'cierre_ax' => 1]);

        $this->assertGolden('rechazos', [
            'otro-salon' => $this->rechazo($this->registro(), ['tamano_clave' => 'OTRA-1']),
            'no-existe' => $this->rechazo($this->registro(), ['tamano_clave' => 'NADA-9']),
            'orden-cerrada' => $this->rechazo($this->registro(), ['no_produccion' => ' 36708 ']),
        ]);
        // La misma orden ya asignada no se revalida contra AX.
        $mismaOrden = $this->registro(['NoProduccion' => '36708']);
        (self::CLASE)::aplicarCambios($mismaOrden, ['no_produccion' => '36708']);
        $this->assertSame('36708', $mismaOrden->NoProduccion);
    }

    /** @return array<string, mixed> */
    private function derivados(array $attrs, array $flags, float $horasAntes = 10, float $cantidadAntes = 900): array
    {
        $r = $this->registro(array_merge(['FechaInicio' => '2026-09-01 06:00:00', 'FechaFinal' => '2026-09-03 06:00:00', 'HorasProd' => 10, 'CalendarioId' => null], $attrs));
        $flags = array_merge(['afectaCalendario' => false, 'afectaDuracion' => false, 'afectaFormulas' => false,
            'afectaAplicacion' => false, 'fechaFinalManual' => false, 'editoAncho' => false], $flags);
        (self::CLASE)::recalcularDerivados($r, $flags, $horasAntes, $cantidadAntes);

        return $r->getDirty();
    }

    public function test_recalcular_derivados(): void
    {
        $this->assertGolden('derivados', [
            'duracion' => $this->derivados([], ['afectaDuracion' => true, 'afectaFormulas' => true]),
            'duracion-en-proceso' => $this->derivados(['EnProceso' => 1], ['afectaDuracion' => true]),
            'duracion-fallback-proporcional' => $this->derivados(['VelocidadSTD' => 0, 'SaldoPedido' => 450], ['afectaDuracion' => true]),
            'solo-calendario' => $this->derivados([], ['afectaCalendario' => true]),
            'fecha-final-manual' => $this->derivados(['FechaFinal' => '2026-09-05 06:00:00'], ['fechaFinalManual' => true]),
            'solo-formulas-sin-inicio' => $this->derivados(['FechaInicio' => null], ['afectaFormulas' => true]),
            'ancho' => $this->derivados(['AnchoToalla' => 40, 'LargoCrudo' => 60, 'PesoCrudo' => 300], ['editoAncho' => true]),
            'nada' => $this->derivados([], []),
        ]);
    }
}
