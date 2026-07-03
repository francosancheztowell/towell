<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Imports\ReqProgramaTejidoSimpleImport;
use App\Imports\ReqProgramaTejidoUpdateImport;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verifica que parseFloat() de los imports de programa tejido trate la coma como
 * separador de MILES (misma regla que TejidoHelpers::sanitizeNumber) y no como decimal.
 * Antes, "1,234" (mil doscientos treinta y cuatro) se convertía en 1.234 y corrompía
 * PesoCrudo/pedidos (Repeticiones = (PesoRollo/PesoCrudo)/Tiras*1000 explotaba).
 */
class ProgramaTejidoImportParseFloatTest extends TestCase
{
    /** @return array<string, array{mixed, ?float}> */
    public static function casosParseFloat(): array
    {
        return [
            'coma como separador de miles' => ['1,234', 1234.0],
            'decimal con punto'            => ['1234.5', 1234.5],
            'formato europeo (regla sanitizeNumber: coma se elimina)' => ['1.234,56', 1.23456],
            'cadena vacia retorna null'    => ['', null],
        ];
    }

    #[DataProvider('casosParseFloat')]
    public function test_update_import_parse_float_trata_coma_como_miles($input, ?float $esperado): void
    {
        $this->assertSame($esperado, $this->parseFloat(ReqProgramaTejidoUpdateImport::class, $input));
    }

    #[DataProvider('casosParseFloat')]
    public function test_simple_import_parse_float_trata_coma_como_miles($input, ?float $esperado): void
    {
        $this->assertSame($esperado, $this->parseFloat(ReqProgramaTejidoSimpleImport::class, $input));
    }

    public function test_parse_float_coincide_con_sanitize_number_para_miles(): void
    {
        $valor = '1,234';

        $this->assertSame(
            TejidoHelpers::sanitizeNumber($valor),
            $this->parseFloat(ReqProgramaTejidoUpdateImport::class, $valor)
        );
    }

    private function parseFloat(string $clase, $valor): ?float
    {
        $method = new ReflectionMethod($clase, 'parseFloat');
        $method->setAccessible(true);

        return $method->invoke(new $clase(), $valor);
    }
}
