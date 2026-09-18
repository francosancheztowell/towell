<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Support\Crudo\CrudoDefectTurnShare;
use PHPUnit\Framework\TestCase;

final class CrudoDefectTurnShareTest extends TestCase
{
    public function test_quality_matches_the_gauge_when_all_seconds_are_in_one_turn(): void
    {
        // 10 2das / 100 pzas = 10% 2das → 90% calidad, igual que el gauge.
        $percents = CrudoDefectTurnShare::percents(
            [['turns' => ['1' => 0, '2' => 0, '3' => 10, '4' => 0]]],
            ['1' => 0, '2' => 0, '3' => 100, '4' => 0],
        );

        $this->assertSame(['1' => null, '2' => null, '3' => 90, '4' => null], $percents);
    }

    public function test_quality_is_computed_per_turn_from_that_turn_pieces(): void
    {
        $percents = CrudoDefectTurnShare::percents(
            [['turns' => ['1' => 2, '2' => 0, '3' => 3, '4' => 0, 'other' => 5]]],
            ['1' => 60, '2' => 0, '3' => 40, '4' => 0],
        );

        $this->assertSame(['1' => 97, '2' => null, '3' => 93, '4' => null], $percents);
    }

    public function test_it_returns_null_when_a_turn_has_no_pieces(): void
    {
        $this->assertSame(
            ['1' => null, '2' => null, '3' => null, '4' => null],
            CrudoDefectTurnShare::percents([], []),
        );
        $this->assertSame(
            ['1' => null, '2' => null, '3' => null, '4' => null],
            CrudoDefectTurnShare::percents(
                [['turns' => ['3' => 8]]],
                ['1' => 0, '2' => 0, '3' => 0, '4' => 0],
            ),
        );
    }

    public function test_capture_turns_label_joins_turns_with_pieces(): void
    {
        $this->assertSame('1,3', CrudoDefectTurnShare::captureTurnsLabel([
            'piecesT1' => 60,
            'piecesT2' => 0,
            'piecesT3' => 40,
            'piecesT4' => 0,
        ]));
        $this->assertSame('3', CrudoDefectTurnShare::captureTurnsLabel([
            'piecesT1' => 0,
            'piecesT2' => 0,
            'piecesT3' => 90,
            'piecesT4' => 0,
        ]));
        $this->assertSame('', CrudoDefectTurnShare::captureTurnsLabel([]));
    }

    public function test_turn_without_seconds_is_not_scored(): void
    {
        // Sin 2das no hubo falla: no se pinta 100%, se ignora el turno.
        $percents = CrudoDefectTurnShare::percents(
            [['turns' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0]]],
            ['1' => 50, '2' => 0, '3' => 0, '4' => 0],
        );

        $this->assertSame(['1' => null, '2' => null, '3' => null, '4' => null], $percents);
    }
}
