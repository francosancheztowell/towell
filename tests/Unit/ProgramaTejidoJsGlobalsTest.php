<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * El bundle se sirve como <script type="module">. En un modulo, un
 * `function foo()` de nivel superior NO crea window.foo — en el <script> clasico
 * que esto era antes, si. El bundle se llama a si mismo por window. en decenas de
 * sitios, asi que cualquier funcion que se lea por window y no se publique:
 *
 *   - queda undefined en tiempo de ejecucion (asi dejo de abrir el modal de duplicar), y
 *   - esbuild la borra por tree-shaking al no verla referenciada (el primer build
 *     salio en 124 KB en vez de 273 KB por esto).
 *
 * Este test compara los window.X que el bundle lee contra los que publica.
 */
class ProgramaTejidoJsGlobalsTest extends TestCase
{
    /** Globales del navegador: se leen por window. pero no las define el bundle. */
    private const DEL_NAVEGADOR = [
        'addEventListener', 'removeEventListener', 'getComputedStyle', 'history',
        'innerHeight', 'innerWidth', 'location', 'open', 'scrollTo', 'scrollBy',
        'document', 'setTimeout', 'clearTimeout', 'fetch', 'matchMedia', 'origin',
        'requestAnimationFrame', 'localStorage', 'sessionStorage', 'alert', 'console',
    ];

    /** Las aporta otro script de la pagina, no este bundle. */
    private const DE_OTROS_SCRIPTS = [
        'PTFilterEngine', 'PT_BOOT', 'Swal', 'showToast', 'toast',
        'abrirModalActCalendarios', 'abrirModalMarbetes', 'abrirModalRepaso',
        'abrirModalRedboothProgramaTejido', 'openLinesModal', 'verDetallesGrupoBalanceo',
        'applyFilters', 'onFiltersApplied', 'columns', 'pinnedColumns',
    ];

    public function test_todo_lo_que_se_lee_por_window_esta_publicado(): void
    {
        $js = file_get_contents(base_path('resources/js/programa-tejido/index.js'));
        $this->assertNotEmpty($js);

        preg_match_all('/window\.([A-Za-z_$][\w$]*)/', $js, $leidos);
        // `=` que no sea `==`/`===`: si no, `window.foo === "function"` cuenta como asignacion.
        preg_match_all('/window\.([A-Za-z_$][\w$]*)\s*=(?!=)/', $js, $asignados);

        $publicados = array_merge($asignados[1], self::DEL_NAVEGADOR, self::DE_OTROS_SCRIPTS);

        $huerfanos = array_values(array_unique(array_diff($leidos[1], $publicados)));
        sort($huerfanos);

        $this->assertSame([], $huerfanos, implode('
', [
            'Estas se leen por window. pero el bundle no las publica.',
            'Si las declara el bundle, pon `window.X = X;` en el mismo scope que la declaracion',
            '(pegado a ella si vive dentro de un bloque anidado: al final del archivo no esta en scope).',
            'Si las trae otro script de la pagina, agregalas a DE_OTROS_SCRIPTS.',
            'Huerfanas: '.implode(', ', $huerfanos),
        ]));
    }
}
