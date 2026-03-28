<?php

namespace Tests\Unit;

use App\Support\Planeacion\TelarSalonResolver;
use PHPUnit\Framework\TestCase;

class TelarSalonResolverTest extends TestCase
{
    public function test_normalize_salon_collapses_itema_and_smith_to_smit(): void
    {
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('ITEMA', '299'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('SMITH', '300'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('SMIT', '320'));
    }

    public function test_numeric_telares_299_and_above_default_to_smit(): void
    {
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('ITEMA', '299'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('JACQUARD', '305'));
        $this->assertSame('JACQUARD', TelarSalonResolver::normalizeSalon('JACQUARD', '298'));
    }

    public function test_salon_aliases_include_known_variants(): void
    {
        $this->assertSame(['SMIT', 'SMITH', 'ITEMA'], TelarSalonResolver::salonAliases('ITEMA', '299'));
        $this->assertSame(['KARL MAYER', 'KARLMAYER', 'KM'], TelarSalonResolver::salonAliases('KM', '10'));
    }

    public function test_telar_sort_key_orders_numeric_values_before_text(): void
    {
        $this->assertLessThan(
            TelarSalonResolver::telarSortKey('1000'),
            TelarSalonResolver::telarSortKey('300')
        );

        $this->assertLessThan(
            TelarSalonResolver::telarSortKey('ABC'),
            TelarSalonResolver::telarSortKey('20')
        );
    }
}
