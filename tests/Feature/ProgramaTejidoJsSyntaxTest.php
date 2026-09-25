<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * El JS del modulo es un solo bundle con un solo scope (antes eran 8 blade dentro
 * de un unico <script>): un `const` repetido revienta TODO el modulo con un
 * SyntaxError, y ningun test de strings lo ve. Esto lo pasa por `node --check`.
 */
class ProgramaTejidoJsSyntaxTest extends TestCase
{
    public function test_el_js_del_modulo_compila(): void
    {
        if (! Process::run('node --version')->successful()) {
            $this->markTestSkipped('node no esta disponible');
        }

        $bundle = base_path('resources/js/programa-tejido/index.js');
        $this->assertFileExists($bundle);

        $check = Process::run(['node', '--check', $bundle]);

        $this->assertTrue(
            $check->successful(),
            'El bundle de Programa Tejido no compila:
'.$check->errorOutput()
        );
    }

    public function test_la_vista_solo_imprime_el_bootstrap(): void
    {
        $html = view('modulos.programa-tejido.scripts.main', [
            'columns' => [['field' => 'NoTelarId', 'label' => 'Telar']],
            'basePath' => '/planeacion/programa-tejido',
            'apiPath' => '/programa-tejido',
            'linePath' => '/planeacion/req-programa-tejido-line',
            'hiddenFields' => ['Observaciones'],
        ])->render();

        // Los valores que solo conoce el servidor viajan como datos en #pt-boot (04-perf)...
        $this->assertStringContainsString('<script type="application/json" id="pt-boot">', $html);
        $this->assertStringContainsString('"NoTelarId"', $html);
        $this->assertStringContainsString('"Observaciones"', $html);
        // ...y el resto lo sirve Vite, no un <script> inline de 527 KB.
        $this->assertMatchesRegularExpression('/programa-tejido\/index\.js|assets\/index-[A-Za-z0-9_-]+\.js/', $html);
        $this->assertLessThan(20000, strlen($html), 'el JS volvio a colarse inline en la vista');
    }
}
