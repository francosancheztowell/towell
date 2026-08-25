<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * buildPasadasPayload arma el arreglo que despues escribe CatCodificados via
 * setAttribute(), que se salta $fillable. Sin lista blanca, cualquier clave del
 * request se convierte en una columna escrita.
 */
class ProcesarMuestrasPasadasTest extends TestCase
{
    /** @param array<string, mixed> $pasadas */
    private function construirPayload(array $pasadas, $ordenData = null): array
    {
        $clase = new ReflectionClass(ProcesarMuestrasDesarrolladorService::class);
        $servicio = $clase->newInstanceWithoutConstructor();
        $metodo = $clase->getMethod('buildPasadasPayload');
        $metodo->setAccessible(true);

        return $metodo->invoke($servicio, $pasadas, $ordenData);
    }

    public function test_descarta_claves_ajenas_a_la_lista_blanca(): void
    {
        $payload = $this->construirPayload([
            'PasadasComb1' => '12',
            'Pedido' => '1',          // columna real de CatCodificados
            'TelarId' => '999',       // idem
            'TotalRollos' => '0',
        ]);

        $this->assertSame(['PasadasComb1' => 12], $payload);
        $this->assertArrayNotHasKey('Pedido', $payload);
        $this->assertArrayNotHasKey('TelarId', $payload);
        $this->assertArrayNotHasKey('TotalRollos', $payload);
    }

    public function test_conserva_las_claves_legitimas(): void
    {
        $payload = $this->construirPayload([
            'PasadasComb1' => '1',
            'PasadasComb2' => '2',
            'PasadasComb3' => '3',
            'PasadasComb4' => '4',
            'PasadasComb5' => '5',
        ]);

        $this->assertSame(
            ['PasadasComb1' => 1, 'PasadasComb2' => 2, 'PasadasComb3' => 3, 'PasadasComb4' => 4, 'PasadasComb5' => 5],
            $payload
        );
    }

    public function test_pasadas_trama_sigue_mapeando_a_su_columna_real(): void
    {
        $payload = $this->construirPayload(['PasadasTrama' => '40']);

        $this->assertSame(['PasadasTramaFondoC1' => 40], $payload);
    }

    public function test_un_request_solo_con_claves_ajenas_no_escribe_nada(): void
    {
        $this->assertSame([], $this->construirPayload(['Pedido' => '1', 'Saldos' => '2']));
    }
}
