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
            'articulo' => ' ART-1 , art-1,ART-2 ',
            'tamano' => ['GRANDE', ' CH* '],
            'cliente' => [' WALMART, S.A. ', 'WALMART, S.A.', ''],
            'color' => ' AZUL ',
            'anio' => '2026',
            'mes' => '03',
        ]);

        $this->assertSame([
            'flog' => 'F-100',
            'articulo' => 'ART-1,ART-2',
            'tamano' => 'GRANDE,CH*',
            'cliente' => ['WALMART, S.A.'],
            'anio' => '2026',
            'mes' => '3',
        ], $filters->toArray());
        $this->assertTrue($filters->hasAny());
        $this->assertSame('F-100', $filters->flogUnico());
    }

    public function test_it_uses_safe_defaults_for_invalid_values(): void
    {
        $filters = TrazabilidadFilters::fromArray([
            'flog' => [['invalid']],
            'articulo' => null,
            'anio' => '26',
            'mes' => '13',
        ]);

        $this->assertSame('', $filters->flog);
        $this->assertSame('', $filters->articulo);
        $this->assertSame('', $filters->anio);
        $this->assertFalse($filters->hasAny());
        $this->assertNull($filters->flogUnico());

        // Mes sin año no aplica.
        $this->assertSame('', TrazabilidadFilters::fromArray(['mes' => '5'])->mes);
        // Lista, rango o comodín: no hay un Flog único.
        foreach (['F-1,F-2', 'F-1..F-9', 'F-*'] as $flog) {
            $this->assertNull(TrazabilidadFilters::fromArray(['flog' => $flog])->flogUnico(), $flog);
        }
    }

    public function test_summary_values_use_the_resolved_facets(): void
    {
        $summary = (new TrazabilidadFilterOptionsService)->summaryValues(new TrazabilidadFilters(flog: 'F-1..F-2'), [
            'flog' => collect(['F-100', ' F-200 ', '']),
            'articulo' => collect([
                ['codigo' => 'ART-1', 'label' => 'ART-1 / Toalla'],
                ['codigo' => 'ART-2', 'label' => 'ART-2'],
            ]),
            'tamano' => collect(['CHICO']),
        ]);

        $this->assertSame(['F-100', 'F-200'], $summary['flogs']->all());
        $this->assertSame(['ART-1 · Toalla', 'ART-2'], $summary['articulos']->all());
        $this->assertSame(['CHICO'], $summary['tamanos']->all());
    }
}
