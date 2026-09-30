<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MIG-ATA-01 (19-03): las vistas de Atadores ya no llevan JS inline ni manejadores on*; cargan su
 * módulo de resources/js/modulos/atadores/** con @vite. Se revisa el fuente Blade (el HTML
 * renderizado trae el onclick del × de x-ui.modal-base: HANDOFF 19-01 U4).
 */
class VistasAtadoresSinJsInlineTest extends TestCase
{
    /** vista => módulo que carga (null = sin JS propio) */
    private const VISTAS = [
        'atadores/calificar-atadores/index.blade.php' => 'resources/js/modulos/atadores/calificar/index.ts',
        'atadores/calificar-atadores/_proceso-km.blade.php' => null,
        'atadores/calificar-atadores/proceso-km.blade.php' => 'resources/js/modulos/atadores/proceso-km/index.ts',
        'atadores/programaAtadores/index.blade.php' => 'resources/js/modulos/atadores/programa/index.ts',
        'atadores/reportes/atadores.blade.php' => 'resources/js/modulos/atadores/reportes/oee/index.ts',
        'atadores/reportes/km.blade.php' => 'resources/js/modulos/atadores/reportes/km/index.ts',
        'atadores/reportes/programa.blade.php' => 'resources/js/modulos/atadores/reportes/programa/index.ts',
        'atadores/reportes/index.blade.php' => null,
        'catalogos-atadores/index.blade.php' => 'resources/js/modulos/catalogos-atadores/index.ts',
    ];

    /** @return array<string, array{string, string|null}> */
    public static function vistas(): array
    {
        $casos = [];
        foreach (self::VISTAS as $vista => $modulo) {
            $casos[$vista] = [$vista, $modulo];
        }

        return $casos;
    }

    #[DataProvider('vistas')]
    public function test_sin_script_inline_ni_manejadores_on(string $vista, ?string $modulo): void
    {
        $fuente = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/modulos/'.$vista);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)/i', $fuente, 'JS inline');
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|input|blur|focus|submit|keyup|keydown)\s*=/i', $fuente, 'manejador on*');
        $this->assertStringNotContainsString('Swal.', $fuente);
        $this->assertStringNotContainsString('fetch(', $fuente);

        if ($modulo !== null) {
            $this->assertStringContainsString("@vite('{$modulo}')", $fuente);
            $this->assertFileExists(dirname(__DIR__, 3).'/'.$modulo);
        }
    }

    public function test_los_modulos_de_atadores_no_usan_fetch_ni_swal_directo(): void
    {
        $raiz = dirname(__DIR__, 3).'/resources/js/modulos/atadores';
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        $revisados = 0;
        foreach ($archivos as $archivo) {
            $codigo = (string) file_get_contents($archivo->getPathname());
            $this->assertStringNotContainsString('fetch(', $codigo, $archivo->getFilename());
            $this->assertStringNotContainsString('Swal.fire', $codigo, $archivo->getFilename());
            $this->assertStringNotContainsString('X-CSRF-TOKEN', $codigo, $archivo->getFilename());
            $this->assertDoesNotMatchRegularExpression('/\.innerHTML\s*\+?=(?!=)/', $codigo, $archivo->getFilename());
            $revisados++;
        }
        $this->assertGreaterThan(10, $revisados);
    }
}
