<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use PHPUnit\Framework\TestCase;

class TrazabilidadFiltersTest extends TestCase
{
    public function test_it_trims_filter_values_and_ignores_unknown_keys(): void
    {
        $filters = TrazabilidadFilters::fromArray([
            'flog' => ' F-100 ',
            'articulo' => ' ART-1 ',
            'tamano' => ' GRANDE ',
            'color' => ' AZUL ',
            'mes' => '12',
        ]);

        $this->assertSame('F-100', $filters->flog);
        $this->assertSame('ART-1', $filters->articulo);
        $this->assertSame('GRANDE', $filters->tamano);
        $this->assertSame(['flog' => 'F-100', 'articulo' => 'ART-1', 'tamano' => 'GRANDE'], $filters->toArray());
        $this->assertTrue($filters->hasAny());
        $this->assertTrue($filters->hasFlog());
    }

    public function test_it_uses_safe_defaults_for_non_scalar_values(): void
    {
        $filters = TrazabilidadFilters::fromArray([
            'flog' => ['invalid'],
            'articulo' => null,
        ]);

        $this->assertSame('', $filters->flog);
        $this->assertSame('', $filters->articulo);
        $this->assertFalse($filters->hasAny());
        $this->assertFalse($filters->hasFlog());
    }

    public function test_summary_values_reuse_the_current_filter_options(): void
    {
        $filters = TrazabilidadFilters::fromArray([
            'flog' => 'F-100',
            'articulo' => 'ART-1',
        ]);
        $options = [
            'flog' => collect(['F-100', 'F-200']),
            'articulo' => collect([
                ['codigo' => 'ART-1', 'label' => 'ART-1 / Toalla'],
                ['codigo' => 'ART-2', 'label' => 'ART-2 / Sábana'],
            ]),
            'tamano' => collect(['CHICO', 'GRANDE']),
        ];

        $summary = (new TrazabilidadFilterOptionsService)->summaryValues($filters, $options);

        $this->assertSame(['F-100'], $summary['flogs']->all());
        $this->assertSame(['ART-1 · Toalla'], $summary['articulos']->all());
        $this->assertSame(['CHICO', 'GRANDE'], $summary['tamanos']->all());

        $invalid = (new TrazabilidadFilterOptionsService)->summaryValues(
            TrazabilidadFilters::fromArray(['flog' => 'NO-EXISTE']),
            $options
        );

        $this->assertSame([], $invalid['flogs']->all());
    }
}
