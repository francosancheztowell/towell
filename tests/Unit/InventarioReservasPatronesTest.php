<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProgramaUrdEng\InventarioReservasService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * El inventario disponible de julios consulta InventSum de TI_PRO (8.9M filas).
 *
 * Con '%' inicial el LIKE no usa el indice de ItemId y escanea la tabla (2147 ms -> 130 ms
 * sin el comodin, auditoria §2.6). LTRIM/RTRIM sobre d.InventLocationId dentro del IN
 * tiene el mismo efecto sobre InventDim. ERP-F0-03.
 */
final class InventarioReservasPatronesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function patrones(): array
    {
        return [
            'rizo' => ['PATTERN_RIZO'],
            'pie' => ['PATTERN_PIE'],
            'urdido' => ['PATTERN_URDIDO'],
        ];
    }

    #[DataProvider('patrones')]
    public function test_el_patron_no_empieza_con_comodin(string $constante): void
    {
        $valor = (new ReflectionClassConstant(InventarioReservasService::class, $constante))->getValue();

        $this->assertIsString($valor);
        $this->assertStringStartsNotWith('%', $valor, "{$constante} con '%' inicial no usa el indice de ItemId.");
        $this->assertStringEndsWith('%', $valor);
    }

    public function test_el_join_no_aplica_funciones_a_inventlocationid(): void
    {
        $codigo = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Services/ProgramaUrdEng/InventarioReservasService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/whereIn\(\s*DB::raw\(\s*[\'"]LTRIM\(RTRIM\(d\.InventLocationId\)\)/',
            $codigo,
            'whereIn sobre LTRIM(RTRIM(d.InventLocationId)) impide usar el indice.'
        );
    }
}
