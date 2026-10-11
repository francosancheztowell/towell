<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-xui-blade-modulos: vistas migradas a x-ui.table / x-ui.table-empty /
 * x-ui.spinner / x-ui.button / x-empty.empty-state, y flash propio retirado en favor de x-ui.flash.
 * Se renderizan con el layout real (rutas de prueba que devuelven la vista con los mismos datos
 * que pasa el controller) y se fija el contrato que el TS de cada pantalla lee.
 */
class XuiBladeModulosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $rutas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        $schema = Schema::connection('sqlsrv');
        $schema->create('SYSRoles', function (Blueprint $table) {
            $table->integer('idrol')->primary();
            $table->string('orden');
            $table->string('modulo');
            $table->string('imagen')->nullable();
            $table->string('Ruta')->nullable();
            $table->integer('Nivel');
            $table->string('Dependencia')->nullable();
        });
        $schema->create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->integer('idusuario');
            $table->integer('idrol');
            $table->integer('acceso')->default(0);
            $table->integer('crear')->default(0);
            $table->integer('modificar')->default(0);
            $table->integer('eliminar')->default(0);
            $table->integer('registrar')->default(0);
        });
    }

    /** Pinta la vista real con el layout (ruta de prueba autenticada) y devuelve el HTML. */
    private function vista(string $vista, array $datos, array $sesion = []): string
    {
        $url = '/xui-prueba/'.(++$this->rutas);
        Route::middleware(['web', 'auth'])->get($url, fn () => view($vista, $datos));
        Route::getRoutes()->refreshNameLookups();

        $peticion = $this->actingAs($this->createUsuario(), 'web');
        if ($sesion !== []) {
            $peticion = $peticion->withSession($sesion);
        }

        /** @var TestResponse $r */
        $r = $peticion->get($url)->assertOk();

        return (string) $r->getContent();
    }

    /** Datos de la rama "sin rango" de cada controller. */
    private static function reportesTejido(): array
    {
        return [
            'modulos.tejido.reportes.rpm-semanal' => [
                'secciones' => [], 'filasOrdenTelar' => [], 'totalGeneral' => null,
                'lunes' => null, 'domingo' => null, 'semanaParam' => null,
            ],
            'modulos.tejido.reportes.promedio-paros-eficiencia' => ['fechaIni' => null, 'fechaFin' => null],
            'modulos.tejido.reportes.reporte-marcas-finales' => [
                'fechaIni' => null, 'fechaFin' => null, 'preview' => collect(), 'diasDisponibles' => collect(),
                'diaActual' => null, 'velocidadesPorTelar' => collect(),
            ],
            'modulos.tejido.reportes.inv-telas' => [
                'fechaIni' => null, 'fechaFin' => null, 'secciones' => [], 'dias' => [], 'leyendaColores' => [],
            ],
        ];
    }

    /**
     * Los reportes de Tejido quitaron su <div> de session('error'): el redirect con error
     * (fechas inválidas) debe seguir viéndose, una sola vez y desde x-ui.flash.
     */
    public function test_reportes_de_tejido_muestran_el_error_de_sesion_una_vez_desde_el_flash(): void
    {
        $mensaje = 'La fecha inicial no puede ser mayor que la final.';

        foreach (self::reportesTejido() as $vista => $datos) {
            $html = $this->vista($vista, $datos, ['error' => $mensaje]);

            $this->assertStringContainsString('data-ui-flash', $html, "$vista: el layout debe pintar el flash");
            $this->assertSame(1, substr_count($html, e($mensaje)), "$vista: el error debe verse exactamente una vez");
        }
    }

    public function test_oee_atadores_sin_rango_estado_vacio_con_boton_al_modal_y_flash_unico(): void
    {
        $mensaje = 'La fecha inicio no puede ser mayor que la fecha fin.';
        $html = $this->vista('modulos.atadores.reportes.atadores', [
            'fechaIni' => null, 'fechaFin' => null, 'lunesIni' => null, 'domingoFin' => null,
        ], ['error' => $mensaje]);

        $this->assertSame(1, substr_count($html, e($mensaje)), 'el error se ve una vez (x-ui.flash)');
        $this->assertStringContainsString('Seleccione un rango de fechas', $html);
        $this->assertStringContainsString('OEE_ATADORES.xlsx', $html);

        // Navbar + estado vacío: dos <button type="button"> que abren el modal por data-ui-modal-open.
        preg_match_all('/<button\b[^>]*data-ui-modal-open="modalRangoTejido"[^>]*>/', $html, $botones);
        $this->assertCount(2, $botones[0], 'navbar y estado vacío abren modalRangoTejido');
        foreach ($botones[0] as $boton) {
            $this->assertStringContainsString('type="button"', $boton);
        }
        $this->assertStringContainsString('id="modalRangoTejido"', $html);
    }

    public function test_catalogo_ubicaciones_conserva_el_tbody_que_lee_el_ts_sin_anidar(): void
    {
        $html = $this->vista('modulos.engomado.configuracion.catalogo-ubicaciones', [
            'ubicaciones' => collect([
                (object) ['Id' => 7, 'Codigo' => 'A-01'],
                (object) ['Id' => 8, 'Codigo' => 'B "02"'],
            ]),
            'noResults' => false,
        ]);

        // x-ui.table usa el <tbody id="ubicaciones-body"> tal cual (no lo envuelve en otro).
        $this->assertMatchesRegularExpression('/<table[^>]*ui-table[^>]*>.*?<\/thead>\s*<tbody id="ubicaciones-body">/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<tbody>\s*<tbody/', $html);
        $this->assertSame(2, preg_match_all('/<tr[^>]*\sdata-fila[\s>]/', $html));
        $this->assertStringContainsString('data-id="7"', $html);
        $this->assertStringContainsString('data-codigo="B &quot;02&quot;"', $html);
        $this->assertStringNotContainsString('No hay ubicaciones registradas', $html);
    }

    public function test_catalogo_ubicaciones_vacio_pinta_la_fila_table_empty_dentro_del_tbody(): void
    {
        $html = $this->vista('modulos.engomado.configuracion.catalogo-ubicaciones', [
            'ubicaciones' => collect(), 'noResults' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/<tbody id="ubicaciones-body">\s*<tr\s*>\s*<td colspan="2"[^>]*>.*?No hay ubicaciones registradas.*?<\/td>\s*<\/tr>\s*<\/tbody>/s',
            $html,
        );
        // Sin data-fila: crearSeleccion('tr[data-fila]') no debe poder seleccionar la fila vacía.
        $this->assertSame(0, preg_match_all('/<tr[^>]*\sdata-fila[\s>]/', $html));
    }

    /**
     * La selección de fila (catalogo-ubicaciones/index.ts → crearSeleccion) pone text-white en el <tr>.
     * Con x-ui.table, app.css fija `.ui-table--primary :where(td) { color: slate-700 }` en cada <td>,
     * y un color declarado en la celda gana a lo heredado del <tr>: la fila seleccionada queda con texto
     * gris oscuro sobre bg-blue-500. Antes (tabla propia, sin ui-table) el td heredaba el blanco.
     */
    public function test_fila_seleccionada_de_ubicaciones_conserva_el_texto_blanco(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $ts = (string) file_get_contents(resource_path('js/modulos/engomado/catalogo-ubicaciones/index.ts'));
        $this->assertStringContainsString("seleccionada: ['bg-blue-500', 'text-white'", $ts);

        $tdConColor = preg_match('/\.ui-table--primary\s*:where\(td\)\s*\{[^}]*\bcolor:/', $css) === 1;

        $html = $this->vista('modulos.engomado.configuracion.catalogo-ubicaciones', [
            'ubicaciones' => collect([(object) ['Id' => 1, 'Codigo' => 'X']]), 'noResults' => false,
        ]);
        preg_match('/<tr[^>]*\sdata-fila[\s>].*?<\/tr>/s', $html, $fila);
        preg_match_all('/<td class="([^"]*)"/', $fila[0] ?? '', $tds);
        $celdasHeredan = $tds[1] !== [] && collect($tds[1])->every(
            fn (string $c) => str_contains($c, 'text-inherit') || str_contains($c, '[color:inherit]')
        );

        $this->assertTrue(
            ! $tdConColor || $celdasHeredan,
            'ui-table--primary fija color en <td>: las celdas de ubicaciones deben heredar (text-inherit) '
            .'o la fila seleccionada queda gris oscuro sobre azul',
        );
    }

    public function test_nucleos_busqueda_sin_resultados_muestra_la_pista_dentro_de_la_fila_vacia(): void
    {
        $vacio = new LengthAwarePaginator([], 0, 15, 1, ['path' => '/urd-eng-nucleos']);

        $conQ = $this->vista('modulos.engomado.urd-eng-nucleos.index', ['items' => $vacio, 'q' => 'zzz']);
        $this->assertMatchesRegularExpression(
            '/<td colspan="2"[^>]*>.*?No se encontraron núcleos.*?Intenta con otro término de búsqueda.*?<\/td>/su',
            $conQ,
        );
        $this->assertDoesNotMatchRegularExpression('/<tbody>\s*<tbody/', $conQ);

        $sinQ = $this->vista('modulos.engomado.urd-eng-nucleos.index', ['items' => $vacio, 'q' => '']);
        $this->assertStringContainsString('No se encontraron núcleos', $sinQ);
        $this->assertStringNotContainsString('Intenta con otro término de búsqueda', $sinQ);
    }

    public function test_nucleos_con_filas_conserva_los_data_que_lee_el_ts(): void
    {
        $item = new \stdClass;
        $item->Id = 3;
        $item->Salon = 'JACQUARD';
        $item->Nombre = 'Núcleo <1>';
        $items = new LengthAwarePaginator([$item], 1, 15, 1, ['path' => '/urd-eng-nucleos']);

        $html = $this->vista('modulos.engomado.urd-eng-nucleos.index', ['items' => $items, 'q' => '']);

        $this->assertMatchesRegularExpression('/<\/thead>\s*<tbody>\s*<tr[^>]*data-key="3"/s', $html);
        $this->assertStringContainsString('data-salon="JACQUARD"', $html);
        $this->assertStringNotContainsString('Núcleo <1>', $html, 'el nombre se escapa');
        $this->assertStringNotContainsString('No se encontraron núcleos', $html);
    }

    /** reporte-resumen/index.ts delega en [data-accion-resumen]; el form GET envía con el submit. */
    public function test_resumen_urdido_cancelar_no_envia_y_consultar_si(): void
    {
        $html = $this->vista('modulos.urdido.reportes-resumen-urdido', [
            'fechaIni' => null, 'fechaFin' => null, 'datosSemanales' => [],
        ]);

        preg_match('/<form method="GET".*?<\/form>/s', $html, $form);
        $this->assertNotEmpty($form);
        $this->assertMatchesRegularExpression('/<button\s+type="button"[^>]*data-accion-resumen="cerrar"[^>]*>/', $form[0]);
        $this->assertMatchesRegularExpression('/<button\s+type="submit"[^>]*>\s*<span>\s*Consultar\s*<\/span>/', $form[0]);
        // Botón del navbar: abre el modal y conserva aria-haspopup.
        $this->assertMatchesRegularExpression('/<button\s+type="button"[^>]*data-accion-resumen="abrir"[^>]*aria-haspopup="dialog"|<button\s+type="button"[^>]*aria-haspopup="dialog"[^>]*data-accion-resumen="abrir"/', $html);
    }

    /** mantenimiento/reportes/index.ts usa data-accion (abrir/confirmar/cerrar-rango) por delegación. */
    public function test_fallas_paros_botones_del_rango_conservan_ids_y_data_accion(): void
    {
        $vacio = new LengthAwarePaginator([], 0, 50, 1, ['path' => '/mantenimiento/reportes/fallas-paros']);
        $html = $this->vista('modulos.mantenimiento.reportes-mantenimiento-fallas-paros', [
            'registros' => $vacio, 'fechaIni' => null, 'fechaFin' => null,
        ]);

        foreach ([
            'abrir-rango' => null,
            'confirmar-rango' => 'btn-confirmar-fechas-reporte',
            'cerrar-rango' => 'btn-cerrar-modal-fechas-reporte',
        ] as $accion => $id) {
            $this->assertSame(1, preg_match('/<button\s+type="button"[^>]*data-accion="'.$accion.'"[^>]*>/', $html, $m), "falta $accion");
            if ($id) {
                $this->assertStringContainsString('id="'.$id.'"', $m[0]);
            }
        }
    }

    /** calificar-julios/index.ts alterna [data-cj="cargando"]; el texto debe seguir visible (inline). */
    public function test_spinner_de_calificar_julios_es_visible_y_anuncia_estado(): void
    {
        $vista = (string) file_get_contents(resource_path('views/modulos/urdido/comun/calificar-julios.blade.php'));
        $this->assertMatchesRegularExpression('/data-cj="cargando"[^>]*>\s*<x-ui\.spinner[^>]*:inline="true"/s', $vista);

        $html = (string) $this->blade('<x-ui.spinner :label="$l" :inline="true" />', ['l' => 'Cargando julios…']);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertMatchesRegularExpression('/<span\s+class="">\s*Cargando julios…\s*<\/span>|<span>\s*Cargando julios…\s*<\/span>/u', $html);
        $this->assertStringNotContainsString('sr-only', $html);
    }
}
