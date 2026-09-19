<?php

declare(strict_types=1);

namespace Tests\Unit\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\InventarioReservasService;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Reservar dos veces la misma pieza debe responder "ya estaba reservada",
 * no un 500. La deteccion miraba QueryException::getCode(), que devuelve el
 * SQLSTATE ('23000') y nunca iguala a 2601/2627: el codigo del driver de SQL
 * Server esta en errorInfo[1].
 */
class ReservaDuplicadaTest extends TestCase
{
    public function test_reconoce_la_violacion_de_indice_unico_de_sql_server(): void
    {
        foreach ([2601, 2627] as $codigoDriver) {
            $this->assertTrue(
                InventarioReservasService::esViolacionDeUnico($this->excepcion('23000', $codigoDriver)),
                "El codigo de driver {$codigoDriver} es un duplicado."
            );
        }
    }

    public function test_el_sqlstate_por_si_solo_no_decide(): void
    {
        // Es justo el valor que devolvia getCode() y por el que nunca entraba.
        $this->assertFalse(InventarioReservasService::esViolacionDeUnico($this->excepcion('23000', 515)));
    }

    public function test_otros_errores_de_bd_no_se_confunden_con_duplicados(): void
    {
        $this->assertFalse(
            InventarioReservasService::esViolacionDeUnico($this->excepcion('42S02', 208)),
            'Tabla inexistente no es un duplicado: tiene que relanzarse.'
        );
        $this->assertFalse(InventarioReservasService::esViolacionDeUnico($this->excepcion('HY000', 0)));
    }

    private function excepcion(string $sqlState, int $codigoDriver): QueryException
    {
        $previa = new \PDOException('SQLSTATE['.$sqlState.']', 0);
        $previa->errorInfo = [$sqlState, $codigoDriver, 'mensaje del driver'];

        return new QueryException('sqlsrv', 'insert into [InvTelasReservadas] ...', [], $previa);
    }
}
