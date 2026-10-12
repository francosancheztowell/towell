<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El JS del modulo es un solo bundle con un solo scope (antes eran 8 blade dentro
 * de un unico <script>): un `const` repetido revienta TODO el modulo con un
 * SyntaxError. Desde que es index.ts eso lo atrapan `npm run typecheck` y
 * tests/Js/programa-tejido-bundle.test.ts, que evalua el bundle (`node --check`
 * no entiende TypeScript).
 */
class ProgramaTejidoJsSyntaxTest extends TestCase
{
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
        $this->assertMatchesRegularExpression('/programa-tejido\/index\.ts|assets\/index-[A-Za-z0-9_-]+\.js/', $html);
        $this->assertLessThan(20000, strlen($html), 'el JS volvio a colarse inline en la vista');
    }
}
