<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Contrato del markup de la grilla, sobre el fuente: la vista extiende layouts.app,
 * que necesita usuario y BD, y en tests no hay ninguno de los dos.
 *
 * Protege los recortes medidos en .planning/phases/04-ux-grid/04-PERF-MEDIDO.md:
 * 3 495 KB -> 1 436 KB de HTML y 5 074 escrituras de display:none eliminadas.
 */
class ProgramaTejidoGridMarkupTest extends TestCase
{
    private function src(string $rel): string
    {
        $path = base_path($rel);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_la_celda_se_arma_en_php_y_en_una_sola_linea(): void
    {
        $vista = $this->src('resources/views/modulos/programa-tejido/req-programa-tejido.blade.php');

        // El <td> multilinea dejaba ~150 bytes de sangria por celda (x7 820).
        $this->assertStringContainsString(
            '@foreach($columns as $colIndex => $col){!! $celda($registro, $colIndex, $col) !!}@endforeach',
            $vista,
            'el loop de celdas volvio a partirse en varias lineas'
        );
        $this->assertSame(0, preg_match('/^\s*<td\s*$/m', $vista), 'reapareció un <td> multilinea');

        // Las utilidades repetidas (371 KB) viven ahora en main.css...
        $this->assertStringNotContainsString("'column-'.\$colIndex.' px-3", $vista);
        $this->assertStringContainsString("\$class = 'column-'.\$colIndex", $vista);
        // ...pero lo que el JS lee sigue emitiéndose.
        $this->assertStringContainsString('data-column="', $vista);
        $this->assertStringContainsString('data-value="', $vista);
        $this->assertStringContainsString("' pt-wrap'", $vista);
        $this->assertStringContainsString("' valor-negativo'", $vista);
        $this->assertStringContainsString('data-es-negativo="1"', $vista);
        // Texto libre de BD: sigue escapado.
        $this->assertStringContainsString('e($value)', $vista);
    }

    public function test_las_columnas_ocultas_se_resuelven_en_el_servidor(): void
    {
        $vista = $this->src('resources/views/modulos/programa-tejido/req-programa-tejido.blade.php');
        $this->assertStringContainsString('$ocultas = array_fill_keys($hiddenFields ?? [], true);', $vista);
        $this->assertStringContainsString("isset(\$ocultas[\$field]) ? ' style=\"display:none\"' : ''", $vista);
        // El th tambien, o queda una columna visible sin celdas debajo.
        $this->assertStringContainsString("isset(\$ocultas[\$col['field']]) ? 'display:none;' : ''", $vista);

        $ctrl = $this->src('app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php');
        $this->assertStringContainsString('columnasOcultasDelUsuario', $ctrl);
        $this->assertStringContainsString("->where('Estado', 1)", $ctrl);

        // Y el front tiene que recibir la lista para no volver a pedirla por HTTP
        // ni reescribir el DOM celda por celda. Va sobre el bundle: apuntar esto a
        // scripts/columns.blade.php era mirar una copia muerta que ya no se carga.
        $js = $this->src('resources/js/programa-tejido/index.js');
        $this->assertStringContainsString('const SSR_HIDDEN_FIELDS = PT_BOOT.hiddenFields', $js);
        $this->assertStringContainsString('if (Array.isArray(SSR_HIDDEN_FIELDS) && SSR_HIDDEN_FIELDS.length > 0) {', $js);
    }

    public function test_el_builder_de_filas_en_js_emite_el_mismo_markup(): void
    {
        // Si _shared-helpers se desincroniza, las filas insertadas tras duplicar/dividir
        // se ven distintas a las del SSR.
        $js = $this->src('resources/js/programa-tejido/index.js');
        $this->assertStringContainsString("let clases = 'column-' + colIndex;", $js);
        $this->assertStringContainsString("clases += ' pt-wrap'", $js);
        $this->assertStringNotContainsString("'px-3 py-2 text-sm text-gray-700 column-'", $js);
    }

    public function test_ya_no_se_togglean_clases_de_color_por_celda(): void
    {
        // main.css decide el color desde la clase de la fila, con !important en ambos
        // lados (tbody td:not(.pinned-column) y .selectable-row.bg-blue-700 td), asi
        // que el loop por celda eran ~15 600 classList.toggle por clic sin efecto.
        $js = $this->src('resources/js/programa-tejido/index.js');
        $this->assertStringNotContainsString("cell.classList.add('text-white')", $js);
        $this->assertStringNotContainsString("td.classList.toggle('text-white'", $js);

        // Un solo listener delegado en tbody, no uno por fila x3 reintentos.
        $this->assertStringContainsString('function bindRowSelectionOnce()', $js);
        $this->assertStringContainsString('rows.indexOf(row)', $js, 'el indice debe resolverse en el click, no congelarse al enganchar');
        $this->assertStringNotContainsString('_selectionHandler', $js);
        $this->assertStringNotContainsString('assignClickEvents', $js);

        // Un reflow sincronico por elemento eran 86 por columna despinneada.
        // Se busca la sentencia, no la palabra: el comentario que la explica la nombra.
        $this->assertSame(0, preg_match('/^\s*void el\.offsetHeight;/m', $js));

        $css = $this->src('public/css/programa-tejido/main.css');
        $this->assertStringNotContainsString('td { transition: background-color', $css);
        $this->assertStringContainsString('#mainTable tbody td {', $css);
        $this->assertStringContainsString('#mainTable tbody td.pt-wrap { white-space: normal; }', $css);
    }
}
