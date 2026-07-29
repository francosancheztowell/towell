<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Services\Crudo\CrudoFloorLayout;
use Tests\TestCase;

final class CrudoFloorLayoutTest extends TestCase
{
    public function test_it_arranges_karl_mayer_in_one_vertical_column(): void
    {
        $layout = (new CrudoFloorLayout)->arrange(
            $this->machines('Karl Mayer', [401, 402]),
        );

        $this->assertTrue($layout['Karl Mayer']['physical']);
        $this->assertSame(
            [['401', '402']],
            $this->telars($layout['Karl Mayer']['columns']),
        );
    }

    public function test_it_arranges_jacquard_in_three_columns_of_five(): void
    {
        $layout = (new CrudoFloorLayout)->arrange(
            $this->machines('Jacquard', range(201, 215)),
        );

        $this->assertTrue($layout['Jacquard']['physical']);
        $this->assertSame(
            [
                ['201', '203', '205', '207', '209'],
                ['202', '204', '206', '208', '210'],
                ['215', '214', '213', '212', '211'],
            ],
            $this->telars($layout['Jacquard']['columns']),
        );
    }

    public function test_it_arranges_smith_in_columns_of_six_six_five_and_five(): void
    {
        $layout = (new CrudoFloorLayout)->arrange(
            $this->machines('Smith', range(299, 320)),
        );

        $this->assertSame([6, 6, 5, 5], array_map('count', $layout['Smith']['columns']));
        $this->assertSame(
            [
                ['299', '301', '303', '305', '307', '309'],
                ['300', '302', '304', '306', '308', '310'],
                ['319', '317', '315', '313', '311'],
                ['320', '318', '316', '314', '312'],
            ],
            $this->telars($layout['Smith']['columns']),
        );
    }

    public function test_complete_floor_contains_all_thirty_nine_catalog_machines(): void
    {
        $layout = (new CrudoFloorLayout)->arrange([
            ...$this->machines('Karl Mayer', [401, 402]),
            ...$this->machines('Jacquard', range(201, 215)),
            ...$this->machines('Smith', range(299, 320)),
        ]);

        $this->assertSame(39, array_sum(array_column($layout, 'count')));
        $this->assertSame(['209', '210', '211'], array_map(
            static fn (array $column): string => (string) end($column)['telar'],
            $layout['Jacquard']['columns'],
        ));
        $this->assertSame(['309', '310', '311', '312'], array_map(
            static fn (array $column): string => (string) end($column)['telar'],
            $layout['Smith']['columns'],
        ));
    }

    /**
     * @param  list<int>  $telars
     * @return list<array<string, string>>
     */
    private function machines(string $salon, array $telars): array
    {
        return array_map(
            static fn (int $telar): array => [
                'telar' => (string) $telar,
                'salon' => $salon,
            ],
            $telars,
        );
    }

    /**
     * @param  list<list<array<string, mixed>>>  $columns
     * @return list<list<string>>
     */
    private function telars(array $columns): array
    {
        return array_map(
            static fn (array $column): array => array_column($column, 'telar'),
            $columns,
        );
    }
}
