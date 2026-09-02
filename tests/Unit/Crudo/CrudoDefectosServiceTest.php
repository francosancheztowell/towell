<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\Services\Crudo\CrudoDefectosService;
use DateTimeImmutable;
use Tests\TestCase;

final class CrudoDefectosServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.production_cache_seconds', 0);
        config()->set('crudo.defect_columns', 2);
    }

    public function test_arma_la_matriz_por_telar_ordenada_por_total(): void
    {
        $resultado = $this->service([
            $this->fila('201', 'Error de trama', 10),
            $this->fila('201', 'Marra', 2),
            $this->fila('204', 'Error de trama', 30),
            $this->fila('204', 'Metida de mano', 5),
        ])->porTelar(new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));

        // La tabla va por número de telar: se busca un telar concreto, no un ranking.
        $this->assertSame(['201', '204'], array_column($resultado['telares'], 'telar'));
        $this->assertSame(12.0, $resultado['telares'][0]['total']);
        $this->assertSame(35.0, $resultado['telares'][1]['total']);
        $this->assertSame(35.0, $resultado['maximo']);
        $this->assertSame(47.0, $resultado['total']);
        $this->assertSame(30.0, $resultado['telares'][1]['defectos']['Error de trama']);
        $this->assertSame('Error de trama', $resultado['porDefecto'][0]['defecto']);
    }

    public function test_recorta_las_columnas_al_tope_y_suma_el_resto_en_otros(): void
    {
        $resultado = $this->service([
            $this->fila('201', 'Error de trama', 40),
            $this->fila('201', 'Marra', 20),
            $this->fila('201', 'Metida de mano', 7),
            $this->fila('201', 'Mancha', 3),
        ])->porTelar(new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));

        // defect_columns = 2 → los dos mayores con columna propia, el resto a "Otros".
        $this->assertSame(['Error de trama', 'Marra', 'Otros'], $resultado['columnas']);
        $this->assertSame(2, $resultado['recortados']);
        $this->assertSame(10.0, $resultado['telares'][0]['defectos']['Otros']);
        $this->assertSame(70.0, $resultado['telares'][0]['total']);
    }

    public function test_ignora_filas_sin_telar_o_sin_cantidad(): void
    {
        $resultado = $this->service([
            $this->fila('', 'Error de trama', 10),
            $this->fila('201', 'Marra', 0),
            $this->fila('201', 'Error de trama', 4),
        ])->porTelar(new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));

        $this->assertCount(1, $resultado['telares']);
        $this->assertSame(4.0, $resultado['total']);
    }

    /**
     * @param  list<object>  $filas
     */
    private function service(array $filas): CrudoDefectosService
    {
        $repository = new class($filas) implements CrudoReadRepository
        {
            /** @param list<object> $filas */
            public function __construct(private array $filas) {}

            public function defectTotalsForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return $this->filas;
            }

            public function aggregateHeadersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return [];
            }

            public function headersForTelarInRange(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return [];
            }

            public function defectsForHeaders(array $headerRecIds): array
            {
                return [];
            }

            public function supplierLotsByWarpingOrder(array $warpingOrders): array
            {
                return [];
            }

            public function machines(): array
            {
                return [];
            }

            public function activeParos(array $telares = []): array
            {
                return [];
            }

            public function activePrograms(array $telares): array
            {
                return [];
            }

            public function efficiencyLinesForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return [];
            }
        };

        return new CrudoDefectosService($repository);
    }

    private function fila(string $telar, string $descripcion, float $cantidad): object
    {
        return (object) [
            'TELAR' => $telar,
            'code' => '01',
            'description' => $descripcion,
            'quantity' => $cantidad,
        ];
    }
}
