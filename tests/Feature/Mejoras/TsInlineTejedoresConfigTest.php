<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Rama claude/mejora-ts-inline-tejedores-config: los <script> inline de Departamentos,
 * Secuencia de folios, Cargar planeación, Cortado de rollo y los reportes Programa de
 * Tejedores/Desarrolladores pasaron a TS. El TS lee todo de la vista (data-*, ids fijos con
 * `getElementById(...)!`), así que el contrato que se fija aquí es Blade → TS:
 *   - el JSON de data-* se decodifica y sus URLs con :id/:telar dan la ruta real;
 *   - existen todos los ids que el TS asume no nulos;
 *   - los campos del catálogo coinciden con los name del formulario, con los data-<campo>
 *     de las filas y con las llaves del item que devuelve el controlador;
 *   - no queda JS inline (onclick, funciones globales del script viejo).
 */
class TsInlineTejedoresConfigTest extends TestCase
{
    use ModuloUrdEng;
    use UsesSqlsrvSqlite {
        ModuloUrdEng::usuarioCon insteadof UsesSqlsrvSqlite;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', 'America/Mexico_City'));
        $this->actingAs($this->usuarioCon([], 'Sistemas'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    private function porId(DOMXPath $x, string $id): ?DOMElement
    {
        $n = $x->query('//*[@id="'.$id.'"]')->item(0);

        return $n instanceof DOMElement ? $n : null;
    }

    /** @param array<int, string> $ids */
    private function assertIds(DOMXPath $x, array $ids): void
    {
        foreach ($ids as $id) {
            $this->assertNotNull($this->porId($x, $id), "Falta #{$id}: el TS hace getElementById('{$id}')!");
        }
    }

    /**
     * Sin JS inline en la fuente Blade de la vista (el layout tiene sus propios onclick) y sin
     * las funciones globales del script viejo en el HTML final.
     *
     * @param  array<int, string>  $globalesViejas
     */
    private function assertSinJsViejo(string $vista, string $html, array $globalesViejas): void
    {
        $fuente = (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $vista).'.blade.php'));
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit)\s*=/i', $fuente, 'quedó un handler inline');
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*[\s"\']src=)[^>]*>/i', $fuente, 'quedó un <script> inline');
        foreach ($globalesViejas as $g) {
            $this->assertStringNotContainsString($g, $html);
        }
    }

    // ------------------------------------------------------------------ catálogo simple

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function catalogos(): array
    {
        return [
            'departamentos' => [
                'modulos.configuracion.departamentos',
                'configuracion.departamentos',
                ['campos' => ['Depto', 'Descripcion'], 'llaveId' => 'id', 'vista' => 'departamentos'],
            ],
            'secuencia' => [
                'modulos.configuracion.secuencia-de-folios',
                'configuracion.secuencia-folios',
                ['campos' => ['Modulo', 'Prefijo', 'Consecutivo'], 'llaveId' => 'Id', 'vista' => 'items'],
            ],
        ];
    }

    private function filasDe(string $vista): array
    {
        return $vista === 'departamentos'
            ? ['departamentos' => collect([
                (object) ['id' => 7, 'Depto' => "O'Hara & <Co>", 'Descripcion' => null],
                (object) ['id' => 8, 'Depto' => 'Tejido', 'Descripcion' => 'Telares'],
            ])]
            : ['items' => collect([
                (object) ['Id' => 7, 'Modulo' => "O'Hara & <Co>", 'Prefijo' => 'TB', 'Consecutivo' => 12],
                (object) ['Id' => 8, 'Modulo' => 'Urdido', 'Prefijo' => null, 'Consecutivo' => 0],
            ])];
    }

    #[DataProvider('catalogos')]
    public function test_catalogo_simple_data_crud_rutas_campos_e_ids(string $vista, string $ruta, array $esperado): void
    {
        $html = view($vista, $this->filasDe($esperado['vista']))->render();
        $x = $this->dom($html);

        $tabla = $x->query('//table[@data-crud]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $tabla, 'el TS busca table[data-crud]');
        $cfg = json_decode($tabla->getAttribute('data-crud'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(route($ruta.'.store'), $cfg['store']);
        $this->assertSame(route($ruta.'.update', ['id' => 7]), str_replace(':id', '7', $cfg['update']));
        $this->assertSame(route($ruta.'.destroy', ['id' => 7]), str_replace(':id', '7', $cfg['destroy']));
        $this->assertSame($esperado['campos'], $cfg['campos']);
        $this->assertSame($esperado['llaveId'], $cfg['llaveId']);
        $this->assertContains($cfg['campoNombre'], $cfg['campos']);
        foreach (['tituloNuevo', 'tituloEditar', 'tituloEliminar', 'nombrePorDefecto', 'vacio'] as $k) {
            $this->assertNotSame('', (string) ($cfg[$k] ?? ''), "falta {$k} en data-crud");
        }

        // El TS toma tBodies[0] y modal.querySelector('form').
        $this->assertSame(1, $x->query('//table[@data-crud]/tbody')->length);
        $this->assertIds($x, ['modal-form', 'modal-title', 'form-method', 'btn-nuevo', 'btn-editar', 'btn-eliminar',
            'form-submit', 'modal-cerrar', 'form-cancelar']);
        $this->assertSame(1, $x->query('//*[@id="modal-form"]//form')->length);
        $this->assertSame('_method', $this->porId($x, 'form-method')?->getAttribute('name'), 'el spoof PUT viaja por FormData(form)');

        // Cada campo tiene su input en el form y su data-<campo en minúsculas> en las filas.
        $filas = $x->query('//table[@data-crud]/tbody/tr[@data-id]');
        $this->assertSame(2, $filas->length);
        foreach ($cfg['campos'] as $campo) {
            $this->assertSame(1, $x->query('//*[@id="modal-form"]//form//input[@name="'.$campo.'"]')->length, "input name={$campo}");
            foreach ($filas as $fila) {
                $this->assertTrue($fila->hasAttribute('data-'.strtolower($campo)), 'fila sin data-'.strtolower($campo));
            }
        }
        // Celdas = Id + una por campo (pintarFila escribe por índice).
        $this->assertSame(count($cfg['campos']) + 1, $x->query('td', $filas->item(0))->length);
        $this->assertSame(count($cfg['campos']) + 1, $x->query('//table[@data-crud]/thead/tr/th')->length);

        $this->assertSinJsViejo($vista, $html, ['selectRow(', 'clearSelection(', 'storeUrl', 'updateUrlTpl']);
    }

    /**
     * Bug previo a la rama (data-depto="{{ e($d->Depto) }}" = doble escape), pero el TS nuevo
     * sigue leyendo de ahí el valor que precarga Editar y el nombre del confirm de borrado:
     * "O'Hara & <Co>" aparece como "O&#039;Hara &amp; &lt;Co&gt;" y al guardar se persiste así.
     */
    #[DataProvider('catalogos')]
    public function test_catalogo_simple_dataset_llega_sin_doble_escape(string $vista, string $ruta, array $esperado): void
    {
        $x = $this->dom(view($vista, $this->filasDe($esperado['vista']))->render());
        $campoNombre = $esperado['campos'][0];

        $fila = $x->query('//table[@data-crud]/tbody/tr[@data-id]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $fila);
        $this->assertSame("O'Hara & <Co>", $fila->getAttribute('data-'.strtolower($campoNombre)));
    }

    #[DataProvider('catalogos')]
    public function test_catalogo_simple_vacio_pinta_tr_empty_con_colspan(string $vista, string $ruta, array $esperado): void
    {
        $html = view($vista, [$esperado['vista'] => collect()])->render();
        $x = $this->dom($html);

        $vacia = $this->porId($x, 'tr-empty');
        $this->assertNotNull($vacia, 'crear quita #tr-empty');
        $this->assertSame((string) (count($esperado['campos']) + 1), $x->query('td', $vacia)->item(0)?->getAttribute('colspan'));
        $this->assertFalse($vacia->hasAttribute('data-id'), 'tr-empty no cuenta como fila seleccionable');
    }

    public function test_secuencia_consecutivo_arranca_en_cero_para_form_reset(): void
    {
        $x = $this->dom(view('modulos.configuracion.secuencia-de-folios', ['items' => collect()])->render());

        // Antes el script ponía Consecutivo = '0' al abrir Nuevo; ahora lo hace form.reset().
        $this->assertSame('0', $this->porId($x, 'Consecutivo')?->getAttribute('value'));
    }

    /**
     * El item del controlador lleva la llave llaveId y una llave por campo: pintarFila hace
     * item[llaveId] e item[campo] (con otra capitalización la fila nueva sale vacía).
     */
    public function test_item_json_del_controlador_trae_llave_id_y_campos_del_data_crud(): void
    {
        config()->set('database.default', 'sqlsrv');
        $this->createTablaDbo('SysDepartamentos', ['id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'Depto' => 'TEXT', 'Descripcion' => 'TEXT']);
        $this->createTablaDbo('SSYSFoliosSecuencias', ['Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'modulo' => 'TEXT', 'prefijo' => 'TEXT', 'consecutivo' => 'INTEGER']);
        $this->withoutMiddleware();

        $depto = $this->postJson(route('configuracion.departamentos.store'), ['Depto' => 'Calidad'])
            ->assertOk()->assertJsonPath('ok', true)->json('item');
        $this->assertSame(['id', 'Depto', 'Descripcion'], array_keys($depto));
        $this->assertNull($depto['Descripcion'], 'sin descripción llega null: el TS lo pinta —');

        $edit = $this->putJson(route('configuracion.departamentos.update', ['id' => $depto['id']]), ['Depto' => 'Calidad 2'])
            ->assertOk()->json('item');
        $this->assertSame($depto['id'], $edit['id']);
        $this->assertSame('Calidad 2', $edit['Depto']);

        $seq = $this->postJson(route('configuracion.secuencia-folios.store'), ['Modulo' => 'Urdido', 'Consecutivo' => '5'])
            ->assertOk()->json('item');
        $this->assertSame(['Id', 'Modulo', 'Prefijo', 'Consecutivo'], array_keys($seq));
        $this->assertSame(5, $seq['Consecutivo']);

        // Borrado: el TS manda POST + _method=DELETE y lee { ok, message }.
        $this->postJson(route('configuracion.departamentos.destroy', ['id' => $depto['id']]), ['_method' => 'DELETE'])
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(0, DB::table('dbo.SysDepartamentos')->count());

        // 422: el TS junta errors[*] y deja el modal abierto.
        $this->postJson(route('configuracion.departamentos.store'), ['Depto' => ''])
            ->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['Depto']]);
    }

    // ------------------------------------------------------------------ cortado de rollo

    public function test_cortado_rutas_data_e_ids_que_el_ts_asume(): void
    {
        $html = view('modulos.tejedores.notificar-mont-rollos.index', [
            'telaresUsuario' => collect([(object) ['NoTelarId' => '201'], (object) ['NoTelarId' => '305']]),
            'telarSeleccionado' => null,
        ])->render();
        $x = $this->dom($html);

        $raiz = $this->porId($x, 'cortadoRollo');
        $this->assertNotNull($raiz);
        $this->assertSame(
            route('notificar.cortado.rollo.ordenes.enproceso', ['telarId' => '201']),
            str_replace(':telar', '201', $raiz->getAttribute('data-ruta-ordenes'))
        );
        $this->assertSame(1, substr_count($raiz->getAttribute('data-ruta-ordenes'), ':telar'), 'el TS reemplaza la primera ocurrencia');
        $this->assertSame(route('notificar.cortado.rollo.datos.produccion'), $raiz->getAttribute('data-ruta-marbetes'));
        $this->assertSame(route('notificar.cortado.rollo.insertar'), $raiz->getAttribute('data-ruta-insertar'));

        $this->assertIds($x, ['selectTelarCortado', 'tablaProduccionesCortadoContainer', 'tablaProduccionesCortadoBody',
            'noDataProduccionesCortado', 'tablaProduccionCortadoContainer', 'tablaProduccionCortadoBody',
            'mensajeEstadoCortado', 'btnNotificarCortado', 'mensajeSeleccionaTelar',
            'btnDropdownTelaresCortado', 'menuDropdownTelaresCortado', 'iconDropdownCortado', 'labelTelarSeleccionado']);

        // Menú: "Todos" (data-telar="") + uno por telar, y el select oculto con las mismas opciones.
        $telares = [];
        foreach ($x->query('//*[@id="menuDropdownTelaresCortado"]//button[@data-telar]') as $b) {
            $telares[] = $b->getAttribute('data-telar');
        }
        $this->assertSame(['', '201', '305'], $telares);
        $opciones = [];
        foreach ($x->query('//*[@id="selectTelarCortado"]/option') as $o) {
            $opciones[] = $o->getAttribute('value');
        }
        $this->assertSame(['', '201', '305'], $opciones, 'select.value = data-telar debe existir como opción');

        $this->assertTrue((bool) $this->porId($x, 'btnNotificarCortado')?->hasAttribute('disabled'));
        $this->assertSinJsViejo('modulos.tejedores.notificar-mont-rollos.index', $html, ['seleccionarTelarDropdown', 'seleccionarProduccionCortado']);
    }

    public function test_cortado_sin_telares_no_pinta_dropdown_y_no_rompe(): void
    {
        $x = $this->dom(view('modulos.tejedores.notificar-mont-rollos.index', [
            'telaresUsuario' => collect(),
            'telarSeleccionado' => null,
        ])->render());

        // iniciarDropdown sale si falta el botón; el resto de ids no nulos sigue ahí.
        $this->assertNull($this->porId($x, 'btnDropdownTelaresCortado'));
        $this->assertIds($x, ['cortadoRollo', 'selectTelarCortado', 'btnNotificarCortado', 'mensajeSeleccionaTelar']);
    }

    // ------------------------------------------------------------------ cargar planeación

    public function test_cargar_planeacion_rutas_en_el_form_e_ids(): void
    {
        $html = view('modulos.configuracion.cargar-planeacion')->render();
        $x = $this->dom($html);

        $form = $this->porId($x, 'excelForm');
        $this->assertNotNull($form);
        // Antes iban escritas a mano en el script: /configuracion/cargar-planeacion/upload,
        // /configuracion/utileria/cargarplaneacion/upload-update y /planeacion/programa-tejido.
        $this->assertSame(url('/configuracion/cargar-planeacion/upload'), $form->getAttribute('data-ruta-upload'));
        $this->assertSame(url('/configuracion/utileria/cargarplaneacion/upload-update'), $form->getAttribute('data-ruta-update'));
        $this->assertSame(url('/planeacion/programa-tejido'), $form->getAttribute('data-ruta-programa'));

        $this->assertIds($x, ['excel_file', 'modoActualizacion', 'uploadBtn', 'progress-section', 'progress-bar',
            'progress-text', 'modo-texto', 'file-info', 'file-name', 'file-size']);
        $this->assertSame('excel_file', $this->porId($x, 'excel_file')?->getAttribute('name'));
        $this->assertSame(1, $x->query('//*[@data-volver]')->length, 'botón Volver sin onclick');
        $this->assertStringContainsString('bg-blue-600', (string) $this->porId($x, 'progress-bar')?->getAttribute('class'),
            'el TS hace classList.replace("bg-blue-600", ...)');
        $this->assertSinJsViejo('modulos.configuracion.cargar-planeacion', $html, ['progressInterval', 'fetch(ruta']);
    }

    // ------------------------------------------------------------------ reportes programa

    /** @return array<string, array{string, string, string}> */
    public static function reportes(): array
    {
        return [
            'tejedores' => ['modulos.tejedores.reportes.programa', 'tejedores.reportes-tejedores', 'mostrarModalConsultarReportesTejedores'],
            'desarrolladores' => ['modulos.desarrolladores.reportes.programa', 'tejedores.reportes-desarrolladores', 'mostrarModalConsultarReportesDesarrolladores'],
        ];
    }

    private function configRango(DOMXPath $x): array
    {
        $form = $x->query('//form[@data-rango-tejido]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);

        return json_decode($form->getAttribute('data-rango-tejido'), true, 512, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('reportes')]
    public function test_reporte_programa_sin_fechas_abre_el_modal_compartido_con_hoy(string $vista, string $ruta, string $vieja): void
    {
        $html = view($vista, ['fechaIni' => null, 'fechaFin' => null, 'datos' => []])->render();
        $x = $this->dom($html);

        $cfg = $this->configRango($x);
        $this->assertSame('modalRangoTejido', $cfg['modal']);
        $this->assertSame(route($ruta.'.programa'), $cfg['ruta'], 'consultar navega al programa de ESTE reporte');
        $this->assertTrue($cfg['abrirAlCargar']);
        $this->assertNotNull($this->porId($x, 'modalRangoTejido'));
        $this->assertSame('2026-10-08', $this->porId($x, 'rt_fecha_ini')?->getAttribute('value'));
        $this->assertSame('2026-10-08', $this->porId($x, 'rt_fecha_fin')?->getAttribute('value'));
        $this->assertGreaterThanOrEqual(2, $x->query('//*[@data-ui-modal-open="modalRangoTejido"]')->length,
            'navbar + botón "Seleccionar Fechas"');

        $this->assertSame(route($ruta.'.index'), $this->porId($x, 'reporte-programa')?->getAttribute('data-indice'),
            'volverAlIndice (botón atrás) sale de data-indice');
        $this->assertSinJsViejo($vista, $html, [$vieja, 'window.volverAlIndice = function']);
    }

    #[DataProvider('reportes')]
    public function test_reporte_programa_con_fechas_precarga_y_no_abre_solo(string $vista, string $ruta, string $vieja): void
    {
        $x = $this->dom(view($vista, ['fechaIni' => '2026-09-01', 'fechaFin' => '2026-09-30', 'datos' => []])->render());

        $cfg = $this->configRango($x);
        $this->assertFalse($cfg['abrirAlCargar']);
        $this->assertSame('2026-09-01', $this->porId($x, 'rt_fecha_ini')?->getAttribute('value'));
        $this->assertSame('2026-09-30', $this->porId($x, 'rt_fecha_fin')?->getAttribute('value'));
    }
}
