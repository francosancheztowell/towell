<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Support\Planeacion\TelarSalonResolver;
use PHPUnit\Framework\TestCase;

/**
 * Vocabulario de salones: el programa dice 'KARL MAYER', AX escribe 'KM', y los telares
 * 401-402 son Karl Mayer aunque caigan en el rango que antes se daba por SMIT.
 */
class TelarSalonResolverTest extends TestCase
{
    public function test_el_salon_capturado_manda_sobre_el_numero_de_telar(): void
    {
        // El bug: 401 >= 299 devolvía SMIT y los catálogos no encontraban la orden.
        $this->assertSame('KARL MAYER', TelarSalonResolver::normalizeSalon('KARL MAYER', '401'));
        $this->assertSame('JACQUARD', TelarSalonResolver::normalizeSalon('JACQUARD', '304'));
    }

    public function test_sin_salon_capturado_el_numero_de_telar_decide(): void
    {
        $this->assertSame('KARL MAYER', TelarSalonResolver::normalizeSalon(null, '402'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('', '320'));
        $this->assertSame('JACQUARD', TelarSalonResolver::normalizeSalon('', '201'));
        // Un telar fuera de todo rango no inventa salón: sin filtro es mejor que con uno malo.
        $this->assertSame('', TelarSalonResolver::normalizeSalon('', '999'));
    }

    public function test_variantes_del_programa_y_de_ax(): void
    {
        $this->assertSame('KARL MAYER', TelarSalonResolver::normalizeSalon('KM'));
        $this->assertSame('KARL MAYER', TelarSalonResolver::normalizeSalon('karlmayer'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('ITEMA'));
        // 'JACUARD' es el dato sucio de BOMTABLE.TWSALON en AX.
        $this->assertSame('JACQUARD', TelarSalonResolver::normalizeSalon('JACUARD'));
    }

    public function test_los_alias_de_ax_no_son_los_del_programa(): void
    {
        $this->assertSame(['KM', 'KARL MAYER', 'KARLMAYER'], TelarSalonResolver::salonAliasesAx('KARL MAYER'));
        $this->assertSame(['SMIT', 'ITEMA'], TelarSalonResolver::salonAliasesAx('SMIT'));
        $this->assertSame(['JACQUARD', 'JACUARD'], TelarSalonResolver::salonAliasesAx('JACQUARD'));

        // Los del programa siguen siendo los de SalonTejidoId, sin las formas de AX.
        $this->assertSame(['KARL MAYER', 'KARLMAYER', 'KM'], TelarSalonResolver::salonAliases('KM'));
    }

    public function test_un_salon_desconocido_se_respeta_tal_cual(): void
    {
        $this->assertSame('SALON NUEVO', TelarSalonResolver::normalizeSalon('SALON NUEVO'));
        $this->assertSame(['SALON NUEVO'], TelarSalonResolver::salonAliases('SALON NUEVO'));
    }

    public function test_la_maquina_de_karl_mayer_no_hereda_el_prefijo_del_salon_anterior(): void
    {
        // Al mover una orden de SMIT al telar 401, la maquina traia 'SMI 304' y el prefijo
        // viejo mandaba a buscar los STD de SMITH.
        $maquina = TejidoHelpers::construirMaquinaConSalon('SMI 304', 'KARL MAYER', '401');

        $this->assertStringStartsWith('KM', $maquina);
        $this->assertSame('KM', TejidoHelpers::resolverTipoTelarStd($maquina, 'KARL MAYER'));
    }

    public function test_el_tipo_std_de_karl_mayer_sale_del_salon_cuando_no_hay_maquina(): void
    {
        $this->assertSame('KM', TejidoHelpers::resolverTipoTelarStd(null, 'KARL MAYER'));
        $this->assertSame('KM', TejidoHelpers::resolverTipoTelarStd('', 'KM'));
        $this->assertSame('SMITH', TejidoHelpers::resolverTipoTelarStd('SMI 304', 'SMIT'));
    }

    public function test_normalize_salon_collapses_itema_and_smith_to_smit(): void
    {
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('ITEMA', '299'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('SMITH', '300'));
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('SMIT', '320'));
    }

    /**
     * Antes el numero de telar pisaba al salon (todo >= 299 era SMIT). Ahora manda el salon
     * capturado, que es lo que permite que Karl Mayer exista en los telares 401-402.
     */
    public function test_numeric_telares_299_and_above_no_longer_override_the_salon(): void
    {
        $this->assertSame('SMIT', TelarSalonResolver::normalizeSalon('ITEMA', '299'));
        $this->assertSame('JACQUARD', TelarSalonResolver::normalizeSalon('JACQUARD', '305'));
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
