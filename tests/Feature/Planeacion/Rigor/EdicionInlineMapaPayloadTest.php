<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Edición inline: cada clave de inlineFieldPayloadMap (resources/js/programa-tejido/index.ts)
 * contra el PUT /planeacion/programa-tejido/{id}, en legacy y en v2.
 *
 * El mapa se lee del propio index.ts: si alguien agrega una columna editable sin caso aquí,
 * test_cada_clave_del_mapa_js_tiene_caso_en_el_proveedor falla. Cada caso manda el valor
 * como lo manda saveInlineField() ({[payloadField]: value}) y fija:
 *  - 200 + {success: true, message, data} con el valor en el resumen;
 *  - la columna cambia en BD (no se ignora en silencio);
 *  - qué columnas de la fila editada cambian (las derivadas documentadas, nada más);
 *  - ningún otro renglón, telar, línea ajena ni la superficie Muestras cambia.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") Un caso por campo editable del mapa del
 * front: la complejidad es la tabla de casos, partirla separaría el proveedor de su verificación.
 */
class EdicionInlineMapaPayloadTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    /**
     * Columna de la grilla => [campo del payload, valor como lo manda el JS, columnas de BD => valor esperado].
     * Las fechas esperadas se comparan por prefijo (sqlite guarda texto).
     */
    private const CASOS = [
        'FibraRizo' => ['hilo', 'H-30/1', ['FibraRizo' => 'H-30/1']],
        'CalendarioId' => ['calendario_id', 'CAL2', ['CalendarioId' => 'CAL2']],
        'TamanoClave' => ['tamano_clave', 'MOD2', ['TamanoClave' => 'MOD2']],
        'Rasurado' => ['rasurado', 'SI', ['Rasurado' => 'SI']],
        'TotalPedido' => ['pedido', 900, ['TotalPedido' => 900.0, 'SaldoPedido' => 900.0]],
        'ProgramarProd' => ['programar_prod', '2026-09-20', ['ProgramarProd' => '2026-09-20']],
        'FlogsId' => ['idflog', 'RS-12345', ['FlogsId' => 'RS-12345', 'TipoPedido' => 'RS']],
        'NombreProyecto' => ['descripcion', 'PROYECTO RIGOR', ['NombreProyecto' => 'PROYECTO RIGOR']],
        'AplicacionId' => ['aplicacion_id', 'APX', ['AplicacionId' => 'APX']],
        'NoTiras' => ['no_tiras', 3, ['NoTiras' => 3.0]],
        'Peine' => ['peine', 52, ['Peine' => 52.0]],
        'LargoCrudo' => ['largo_crudo', 80, ['LargoCrudo' => 80.0]],
        'Luchaje' => ['luchaje', 210, ['Luchaje' => 210.0]],
        'PesoCrudo' => ['peso_crudo', 500, ['PesoCrudo' => 500.0]],
        'Ancho' => ['ancho', 40, ['Ancho' => 40.0, 'AnchoToalla' => 40.0]],
        'AnchoToalla' => ['ancho_toalla', 41, ['AnchoToalla' => 41.0, 'Ancho' => 41.0]],
        'EntregaProduc' => ['entrega_produc', '2026-09-20', ['EntregaProduc' => '2026-09-20']],
        'EntregaPT' => ['entrega_pt', '2026-09-21', ['EntregaPT' => '2026-09-21']],
        'EntregaCte' => ['entrega_cte', '2026-09-25T10:30', ['EntregaCte' => '2026-09-25 10:30']],
        'PTvsCte' => ['pt_vs_cte', 5, ['PTvsCte' => 5.0]],
        'NoProduccion' => ['no_produccion', '30099', ['NoProduccion' => '30099']],
    ];

    /**
     * Columnas de la fila 2 que, además de las del caso, cambia cada edición (derivados
     * documentados en Derivados/Persistencia). UpdatedAt se excluye siempre.
     */
    private const DERIVADAS = [
        'FibraRizo' => [],
        // Re-acomoda FechaFinal con el HorasProd previo, pero las líneas regeneradas reescriben
        // HorasProd y las fórmulas de eficiencia (ver test_cambiar_calendario_...).
        'CalendarioId' => ['FechaFinal', 'DiasEficiencia', 'StdHrsEfect', 'ProdKgDia2', 'DiasJornada', 'HorasProd', 'ProdKgDia', 'StdDia', 'StdToaHra'],
        'TamanoClave' => '*',
        'Rasurado' => [],
        'TotalPedido' => '*',
        'ProgramarProd' => [],
        'FlogsId' => [],
        'NombreProyecto' => [],
        'AplicacionId' => [],
        'NoTiras' => '*',
        'Peine' => [],
        'LargoCrudo' => '*',
        'Luchaje' => '*',
        'PesoCrudo' => '*',
        'Ancho' => ['PesoGRM2'],
        'AnchoToalla' => ['PesoGRM2'],
        'EntregaProduc' => [],
        'EntregaPT' => [],
        'EntregaCte' => [],
        'PTvsCte' => [],
        'NoProduccion' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();

        // NoProduccionCierreAxService consulta cierre_ax (no está en $fillable de CatCodificados).
        Schema::connection('sqlsrv')->table('CatCodificados', fn (Blueprint $t) => $t->boolean('cierre_ax')->nullable());
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        $this->createTablaDbo('ReqAplicaciones', ['Id' => 'integer', 'AplicacionId' => 'text', 'Factor' => 'real']);

        foreach ([
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'Total' => 50],
            ['TamanoClave' => 'MOD2', 'SalonTejidoId' => 'SMIT', 'Total' => 60, 'NoTiras' => 4, 'Luchaje' => 180],
            ['TamanoClave' => 'MODJ', 'SalonTejidoId' => 'JACQUARD', 'Total' => 60],
        ] as $modelo) {
            DB::table('ReqModelosCodificados')->insert($modelo);
        }
        DB::table('dbo.ReqAplicaciones')->insert(['Id' => 1, 'AplicacionId' => 'APX', 'Factor' => 2]);

        $filas = [];
        for ($d = 0; $d < 60; $d++) {
            $dia = Carbon::parse('2026-08-25')->addDays($d);
            $filas[] = ['CalendarioId' => 'CAL2', 'FechaInicio' => $dia->copy()->setTime(6, 0)->format('Y-m-d H:i:s'), 'FechaFin' => $dia->copy()->setTime(22, 0)->format('Y-m-d H:i:s')];
        }
        DB::table('ReqCalendarioLine')->insert($filas);

        // Fila 2 (SMIT 201, Ultimo, sin orden) con lo que calcularHorasProd necesita.
        DB::table('ReqProgramaTejido')->where('Id', 2)->update([
            'TamanoClave' => 'MOD1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'NoTiras' => 2, 'Luchaje' => 200,
            'Repeticiones' => 16, 'HorasProd' => 10, 'PesoCrudo' => 450, 'LargoCrudo' => 70,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string, string> columna de la grilla => campo del payload, leído de index.ts */
    private static function mapaJs(): array
    {
        $fuente = (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/programa-tejido/index.ts');
        preg_match('/const inlineFieldPayloadMap: Record<string, string> = \{([\s\S]*?)\};/', $fuente, $m);
        preg_match_all("/(\\w+)\\s*:\\s*'([\\w]+)'/", $m[1] ?? '', $pares, PREG_SET_ORDER);

        return array_column($pares, 2, 1);
    }

    /** @return iterable<string, array{string, string}> */
    public static function clavesDelMapa(): iterable
    {
        foreach (array_keys(self::CASOS) as $columna) {
            foreach (['legacy' => 'off', 'v2' => 'on'] as $version => $modo) {
                yield "{$columna} ({$version})" => [$columna, $modo];
            }
        }
    }

    private function como(array $permisos = [2 => ['acceso', 'modificar'], 5 => ['acceso', 'modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function modo(string $modo): void
    {
        config()->set('planeacion.mutaciones_v2.actualizar', $modo);
        config()->set('planeacion.mutaciones_v2.usuarios_canary', []);
    }

    /** @return array<string, mixed> */
    private function fila(string $tabla, int $id): array
    {
        return (array) DB::table($tabla)->where('Id', $id)->first();
    }

    /** Foto sin la fila editada, sus líneas ni UpdatedAt. */
    private function fotoAjena(string $tabla, string $tablaLineas, int $id): array
    {
        $foto = $this->fotoSuperficies();
        $foto[$tabla] = array_values(array_filter($foto[$tabla], fn ($f) => $f['Id'] !== $id));
        $foto[$tablaLineas] = array_values(array_filter($foto[$tablaLineas], fn ($f) => (int) $f['ProgramaId'] !== $id));

        return collect($foto)->map(fn ($filas) => array_map(fn ($f) => array_diff_key($f, ['UpdatedAt' => 1]), $filas))->all();
    }

    private function assertValorColumna(mixed $esperado, mixed $real, string $msg): void
    {
        if (is_string($esperado) && preg_match('/^\d{4}-\d{2}-\d{2}/', $esperado)) {
            $this->assertStringStartsWith($esperado, (string) $real, $msg);

            return;
        }
        $this->assertEquals($esperado, $real, $msg);
    }

    // ------------------------------------------------------------------ cobertura del mapa

    public function test_cada_clave_del_mapa_js_tiene_caso_en_el_proveedor(): void
    {
        $mapa = self::mapaJs();

        $this->assertCount(21, $mapa, 'inlineFieldPayloadMap cambió de tamaño: agrega su caso a CASOS');
        $this->assertSame(array_keys($mapa), array_keys(self::CASOS));
        foreach ($mapa as $columna => $campo) {
            $this->assertSame($campo, self::CASOS[$columna][0], "{$columna} cambió de campo de payload");
        }
    }

    // ------------------------------------------------------------------ cada clave

    #[DataProvider('clavesDelMapa')]
    public function test_cada_clave_del_mapa_cambia_su_columna_y_solo_su_fila(string $columna, string $modo): void
    {
        $this->modo($modo);
        [$campo, $valor, $esperadas] = self::CASOS[$columna];
        $antesFila = $this->fila('ReqProgramaTejido', 2);
        $antesAjeno = $this->fotoAjena('ReqProgramaTejido', 'ReqProgramaTejidoLine', 2);

        $r = $this->como()->putJson('/planeacion/programa-tejido/2', [$campo => $valor]);

        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Programa de tejido actualizado')
            ->assertJsonPath('data.Id', 2);

        $despues = $this->fila('ReqProgramaTejido', 2);
        foreach ($esperadas as $col => $esperado) {
            $this->assertValorColumna($esperado, $despues[$col], "{$columna}: {$col} no se guardó");
            // El resumen que repinta la fila trae el mismo valor que quedó en BD.
            if (array_key_exists($col, (array) $r->json('data'))) {
                $enData = $r->json("data.{$col}");
                // Las fechas viajan en ISO UTC ("…T16:30:00.000000Z"): se comparan en la zona de la app.
                if (is_string($enData) && str_ends_with($enData, 'Z')) {
                    $enData = Carbon::parse($enData)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
                }
                $this->assertValorColumna($esperado, $enData, "{$columna}: data.{$col} no refleja la BD");
            }
        }

        // Nada fuera de la fila 2 (otros telares, el mismo telar, CatCodificados, Muestras).
        $this->assertEquals($antesAjeno, $this->fotoAjena('ReqProgramaTejido', 'ReqProgramaTejidoLine', 2), "{$columna} tocó otra fila o superficie");

        // Dentro de la fila: solo las columnas del caso y sus derivadas documentadas.
        $permitidas = self::DERIVADAS[$columna];
        if ($permitidas !== '*') {
            $cambiadas = array_keys(array_diff_assoc(
                array_map('strval', array_diff_key($despues, ['UpdatedAt' => 1])),
                array_map('strval', array_diff_key($antesFila, ['UpdatedAt' => 1])),
            ));
            sort($cambiadas);
            $esperado = array_values(array_unique([...array_keys($esperadas), ...$permitidas]));
            sort($esperado);
            $this->assertSame($esperado, $cambiadas, "{$columna}: columnas cambiadas inesperadas");
        }

        // Posición, telar y vínculo no se mueven con una edición inline.
        foreach (['Posicion', 'NoTelarId', 'SalonTejidoId', 'EnProceso', 'Ultimo', 'OrdCompartida', 'OrdCompartidaLider', 'FechaInicio'] as $col) {
            $this->assertEquals($antesFila[$col], $despues[$col], "{$columna} movió {$col}");
        }
    }

    #[DataProvider('clavesDelMapa')]
    public function test_cada_clave_del_mapa_en_muestras_solo_toca_muestras(string $columna, string $modo): void
    {
        $this->modo($modo);
        [$campo, $valor, $esperadas] = self::CASOS[$columna];
        DB::table('MuestrasPrograma')->where('Id', 2)->update([
            'TamanoClave' => 'MOD1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'NoTiras' => 2, 'Luchaje' => 200,
            'Repeticiones' => 16, 'HorasProd' => 10, 'PesoCrudo' => 450, 'LargoCrudo' => 70,
        ]);
        $programaAntes = $this->fotoSuperficies()['ReqProgramaTejido'];
        $lineasProgramaAntes = $this->fotoSuperficies()['ReqProgramaTejidoLine'];

        $r = $this->como()->putJson('/planeacion/muestras/2', [$campo => $valor]);

        $r->assertOk()->assertJsonPath('success', true);
        $despues = $this->fila('MuestrasPrograma', 2);
        foreach ($esperadas as $col => $esperado) {
            $this->assertValorColumna($esperado, $despues[$col], "Muestras {$columna}: {$col} no se guardó");
        }
        $this->assertEquals($programaAntes, $this->fotoSuperficies()['ReqProgramaTejido'], "Muestras {$columna} tocó Programa");
        $this->assertEquals($lineasProgramaAntes, $this->fotoSuperficies()['ReqProgramaTejidoLine']);
    }

    // ------------------------------------------------------------------ vaciar la celda

    /**
     * saveInlineField manda null al vaciar una celda numérica o de fecha ('' → null) y
     * CAMPOS_VACIO_A_NULL convierte '' en null en el servidor: se prueban las dos formas.
     */
    #[DataProvider('clavesDelMapa')]
    public function test_vaciar_la_celda_deja_la_columna_en_null_salvo_pedido(string $columna, string $modo): void
    {
        $this->modo($modo);
        [$campo, , $esperadas] = self::CASOS[$columna];
        DB::table('ReqProgramaTejido')->where('Id', 2)->update([
            'FibraRizo' => 'H-OLD', 'CalendarioId' => 'CAL2', 'Rasurado' => 'NO', 'ProgramarProd' => '2026-09-01',
            'FlogsId' => 'CE-1', 'TipoPedido' => 'CE', 'NombreProyecto' => 'P', 'AplicacionId' => 'APX', 'Peine' => 40,
            'Ancho' => 30, 'AnchoToalla' => 30, 'EntregaProduc' => '2026-09-01', 'EntregaPT' => '2026-09-01',
            'EntregaCte' => '2026-09-01 00:00:00', 'PTvsCte' => 1, 'NoProduccion' => '30077',
        ]);
        $antes = $this->fila('ReqProgramaTejido', 2);

        foreach ([null, ''] as $vacio) {
            $r = $this->como()->putJson('/planeacion/programa-tejido/2', [$campo => $vacio]);

            if (in_array($columna, ['NoTiras', 'PesoCrudo'], true)) {
                // Sin NoTiras o PesoCrudo las fórmulas de rollos no se calculan: la regla
                // lo rechaza con 422 sobre el campo en vez de revertir todo con 500.
                $r->assertStatus(422)->assertJsonValidationErrors([$campo]);
                $this->assertEquals($antes, $this->fila('ReqProgramaTejido', 2), "{$columna} vacío no revirtió");

                continue;
            }
            $r->assertOk()->assertJsonPath('success', true);

            $despues = $this->fila('ReqProgramaTejido', 2);
            foreach (array_keys($esperadas) as $col) {
                if ($columna === 'TotalPedido') {
                    // CARACTERIZA: pedido null se ignora en silencio (200 "actualizado") y la
                    // grilla pinta la celda vacía; TotalPedido/SaldoPedido quedan como estaban.
                    $this->assertEquals($antes[$col], $despues[$col], "pedido vacío cambió {$col}");

                    continue;
                }
                $this->assertNull($despues[$col], "{$columna}=".var_export($vacio, true)." no dejó {$col} en null");
            }
        }
    }

    // ------------------------------------------------------------------ validación 422

    /** @return iterable<string, array{string, mixed, string}> */
    public static function valoresInvalidos(): iterable
    {
        $casos = [
            'pedido negativo' => ['pedido', -1],
            'pedido texto' => ['pedido', 'mil'],
            'no_tiras texto' => ['no_tiras', 'dos'],
            'peine texto' => ['peine', 'x'],
            'largo_crudo texto' => ['largo_crudo', 'x'],
            'luchaje texto' => ['luchaje', 'x'],
            'peso_crudo texto' => ['peso_crudo', 'x'],
            'ancho negativo' => ['ancho', -0.5],
            'largo_crudo negativo' => ['largo_crudo', -1],
            'luchaje negativo' => ['luchaje', -1],
            'peine negativo' => ['peine', -1],
            'peso_crudo negativo' => ['peso_crudo', -1],
            'no_tiras negativo' => ['no_tiras', -1],
            'ancho_toalla negativo' => ['ancho_toalla', -1],
            'pt_vs_cte texto' => ['pt_vs_cte', 'x'],
            'programar_prod no fecha' => ['programar_prod', 'no-es-fecha'],
            'entrega_produc no fecha' => ['entrega_produc', '2026-13-40'],
            'entrega_pt no fecha' => ['entrega_pt', 'ayer-no'],
            'entrega_cte no fecha' => ['entrega_cte', '2026-02-30T10:00'],
            'no_produccion de 81' => ['no_produccion', str_repeat('9', 81)],
            'hilo arreglo' => ['hilo', ['H']],
            'descripcion arreglo' => ['descripcion', ['P']],
            'idflog numero' => ['idflog', 123],
            'calendario_id arreglo' => ['calendario_id', ['CAL2']],
            'aplicacion_id arreglo' => ['aplicacion_id', ['APX']],
            'rasurado arreglo' => ['rasurado', ['SI']],
            'tamano_clave arreglo' => ['tamano_clave', ['MOD2']],
        ];
        foreach ($casos as $nombre => [$campo, $valor]) {
            foreach (['legacy' => 'off', 'v2' => 'on'] as $version => $modo) {
                yield "{$nombre} ({$version})" => [$campo, $valor, $modo];
            }
        }
    }

    #[DataProvider('valoresInvalidos')]
    public function test_valor_invalido_responde_422_con_el_error_del_campo_y_no_toca_la_base(string $campo, mixed $valor, string $modo): void
    {
        $this->modo($modo);
        $antes = $this->fotoSuperficies();

        $r = $this->como()->putJson('/planeacion/programa-tejido/2', [$campo => $valor]);

        $r->assertStatus(422)->assertJsonValidationErrors([$campo]);
        $this->assertIsString($r->json('message'));
        $this->assertNotSame('', $r->json('message'));
        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    // ------------------------------------------------------------------ rechazos de negocio

    public function test_clave_modelo_inexistente_responde_422_con_mensaje_y_no_toca_la_base(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $antes = $this->fotoSuperficies();

            $this->como()->putJson('/planeacion/programa-tejido/2', ['tamano_clave' => 'NO-EXISTE'])
                ->assertStatus(422)
                ->assertExactJson(['success' => false, 'message' => 'La clave modelo "NO-EXISTE" no existe en Modelos Codificados', 'tipo' => 'error']);

            $this->assertEquals($antes, $this->fotoSuperficies(), $modo);
        }
    }

    public function test_clave_modelo_de_otro_salon_responde_422_alerta_con_el_salon_encontrado(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $antes = $this->fotoSuperficies();

            $this->como()->putJson('/planeacion/programa-tejido/2', ['tamano_clave' => 'MODJ'])
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('tipo', 'alerta')
                ->assertJsonPath('salon_encontrado', 'JACQUARD')
                ->assertJsonPath('message', 'La clave modelo "MODJ" no existe en el salón actual (SMIT), pero se encontró en el salón: JACQUARD');

            $this->assertEquals($antes, $this->fotoSuperficies(), $modo);
        }
    }

    public function test_no_produccion_cerrada_en_ax_responde_422_orden_cerrada_ax(): void
    {
        DB::table('CatCodificados')->insert(['OrdenTejido' => '36708', 'cierre_ax' => 1]);

        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $antes = $this->fotoSuperficies();

            $this->como()->putJson('/planeacion/programa-tejido/2', ['no_produccion' => ' 36708 '])
                ->assertStatus(422)
                ->assertExactJson([
                    'success' => false, 'code' => 'orden_cerrada_ax', 'field' => 'no_produccion',
                    'message' => 'La orden 36708 ya está cerrada en AX y no puede asignarse como No. Producción.',
                ]);

            $this->assertEquals($antes, $this->fotoSuperficies(), $modo);
        }
    }

    public function test_no_produccion_cerrada_igual_a_la_actual_no_se_rechaza(): void
    {
        DB::table('CatCodificados')->where('OrdenTejido', '30001')->update(['cierre_ax' => 1]);

        // Re-guardar la misma orden del renglón 1 (ya cerrada) no debe bloquear la edición.
        $this->como()->putJson('/planeacion/programa-tejido/1', ['no_produccion' => '30001'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('30001', DB::table('ReqProgramaTejido')->where('Id', 1)->value('NoProduccion'));
    }

    // ------------------------------------------------------------------ permisos y 404

    public function test_sin_permiso_modificar_responde_403_y_no_toca_ninguna_superficie(): void
    {
        $antes = $this->fotoSuperficies();

        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $this->como([2 => ['acceso', 'crear', 'eliminar'], 5 => ['acceso']])
                ->putJson('/planeacion/programa-tejido/2', ['pedido' => 900])->assertForbidden();
            $this->como([2 => ['acceso', 'crear', 'eliminar'], 5 => ['acceso']])
                ->putJson('/planeacion/muestras/2', ['pedido' => 900])->assertForbidden();
        }

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_permiso_de_programa_no_habilita_editar_muestras_ni_al_reves(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'modificar']])->putJson('/planeacion/muestras/2', ['pedido' => 900])->assertForbidden();
        $this->como([5 => ['acceso', 'modificar']])->putJson('/planeacion/programa-tejido/2', ['pedido' => 900])->assertForbidden();

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_sin_sesion_no_edita(): void
    {
        $antes = $this->fotoSuperficies();

        $r = $this->putJson('/planeacion/programa-tejido/2', ['pedido' => 900]);

        $this->assertContains($r->status(), [401, 403]);
        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_registro_inexistente_responde_404_en_legacy_y_v2(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $this->como()->putJson('/planeacion/programa-tejido/999', ['pedido' => 900])->assertNotFound();
        }
    }

    // ------------------------------------------------------------------ claves fuera del mapa

    public function test_campos_fuera_de_las_reglas_se_ignoran_en_silencio_con_200(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $antes = $this->fotoSinUpdatedAt();

            // CARACTERIZA: nombres de BD (los de data-column) o columnas protegidas responden
            // "Programa de tejido actualizado" sin cambiar nada; un cliente que olvide mapear
            // una columna nueva vería un éxito falso.
            $this->como()->putJson('/planeacion/programa-tejido/2', [
                'TotalPedido' => 5, 'FibraRizo' => 'X', 'Posicion' => 9, 'NoTelarId' => '999',
                'FechaInicio' => '2030-01-01 00:00:00', 'EnProceso' => 1, 'OrdCompartida' => 99,
            ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Programa de tejido actualizado');

            $this->assertEquals($antes, $this->fotoSinUpdatedAt(), $modo);
        }
    }

    // ------------------------------------------------------------------ reglas de negocio

    public function test_editar_pedido_recalcula_saldo_como_pedido_menos_produccion_sin_porcentaje_de_segundas(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            DB::table('ReqProgramaTejido')->where('Id', 2)->update(['PorcentajeSegundos' => 5, 'Produccion' => 100, 'TotalPedido' => 500, 'SaldoPedido' => 425]);

            $this->como()->putJson('/planeacion/programa-tejido/2', ['pedido' => 1000])
                ->assertOk()->assertJsonPath('data.TotalPedido', 1000);

            $fila = $this->fila('ReqProgramaTejido', 2);
            $this->assertEquals(1000, $fila['TotalPedido']);
            // CARACTERIZA: la regla confirmada es Saldo = Pedido×(1+%seg/100)−Producción = 950;
            // la edición inline guarda max(0, Pedido − Producción) = 900.
            $this->assertEquals(900, $fila['SaldoPedido'], $modo);
        }
    }

    public function test_editar_pedido_por_debajo_de_la_produccion_deja_saldo_en_cero(): void
    {
        $this->como()->putJson('/planeacion/programa-tejido/1', ['pedido' => 150])->assertOk();

        $fila = $this->fila('ReqProgramaTejido', 1);
        $this->assertEquals(150, $fila['TotalPedido']);
        $this->assertEquals(0, $fila['SaldoPedido']);
        $this->assertEquals(200, $fila['Produccion']);
    }

    public function test_editar_pedido_de_un_renglon_no_ultimo_encadena_el_siguiente_del_mismo_telar(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            DB::table('ReqProgramaTejido')->where('Id', 1)->update([
                'TotalPedido' => 1000, 'SaldoPedido' => 800,
                'TamanoClave' => 'MOD1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'Luchaje' => 200, 'Repeticiones' => 16, 'HorasProd' => 20,
                'FechaFinal' => '2026-09-03 18:00:00',
            ]);
            DB::table('ReqProgramaTejido')->where('Id', 2)->update(['FechaInicio' => '2026-09-03 18:00:00', 'FechaFinal' => '2026-09-05 06:00:00']);
            $otrosTelares = $this->filasFueraDelTelar('201');
            $muestras = $this->fotoSuperficies()['MuestrasPrograma'];

            $this->como()->putJson('/planeacion/programa-tejido/1', ['pedido' => 2000])->assertOk();

            $f1 = $this->fila('ReqProgramaTejido', 1);
            $f2 = $this->fila('ReqProgramaTejido', 2);
            $this->assertSame('2026-09-01 06:30:00', $f1['FechaInicio'], "{$modo}: EnProceso conserva FechaInicio");
            $this->assertNotSame('2026-09-03 18:00:00', $f1['FechaFinal'], "{$modo}: FechaFinal no se recalculó");
            $this->assertEquals(1800, $f1['SaldoPedido']);
            $this->assertSame([1, 2], [(int) $f1['Posicion'], (int) $f2['Posicion']]);
            $this->assertSame([1, 0], [(int) $f1['EnProceso'], (int) $f2['EnProceso']]);
            $this->assertSame(['0', '1'], [(string) $f1['Ultimo'], (string) $f2['Ultimo']]);
            // El siguiente arranca donde termina el editado (cascada).
            $this->assertSame(substr($f1['FechaFinal'], 0, 16), substr((string) $f2['FechaInicio'], 0, 16), "{$modo}: cascada rota");
            $this->assertEquals($otrosTelares, $this->filasFueraDelTelar('201'), $modo);
            $this->assertEquals($muestras, $this->fotoSuperficies()['MuestrasPrograma'], $modo);
        }
    }

    public function test_fallo_al_regenerar_lineas_revierte_cabecera_cascada_y_catcodificados(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update([
            'TamanoClave' => 'MOD1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'Luchaje' => 200, 'Repeticiones' => 16, 'HorasProd' => 20,
        ]);
        $antes = $this->fotoSinLineasPrograma();
        Schema::connection('sqlsrv')->drop('ReqProgramaTejidoLine');

        foreach (['off' => 'legacy', 'on' => 'v2'] as $modo => $version) {
            $this->modo($modo);

            $r = $this->como()->putJson('/planeacion/programa-tejido/1', ['pedido' => 2000]);

            $r->assertStatus(500)->assertJsonPath('success', false);
            $this->assertEquals($antes, $this->fotoSinLineasPrograma(), "{$version}: no revirtió todo");
            // Legacy y v2 dan el mismo mensaje fijo: el detalle SQL se queda en el log.
            $this->assertStringNotContainsString('SQLSTATE', (string) $r->json('message'));
            $this->assertSame('No se pudo actualizar el programa de tejido. Los cambios se revirtieron.', $r->json('message'));
        }
    }

    public function test_respuesta_trae_el_resumen_con_el_valor_nuevo_para_repintar_la_fila(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $r = $this->como()->putJson('/planeacion/programa-tejido/2', ['idflog' => 'CE-777'])->assertOk();

            $r->assertJsonPath('data.Id', 2)->assertJsonPath('data.FlogsId', 'CE-777')->assertJsonPath('data.TipoPedido', 'CE');
            $this->assertSame(['success', 'message', 'data'], array_keys($r->json()));
        }
    }

    public function test_dos_ediciones_seguidas_de_campos_distintos_conservan_ambas(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            $this->como()->putJson('/planeacion/programa-tejido/2', ['descripcion' => "P-{$modo}"])->assertOk();
            $this->como()->putJson('/planeacion/programa-tejido/2', ['rasurado' => 'SI'])->assertOk();

            $fila = $this->fila('ReqProgramaTejido', 2);
            $this->assertSame("P-{$modo}", $fila['NombreProyecto']);
            $this->assertSame('SI', $fila['Rasurado']);
        }
    }

    public function test_no_tiras_o_peso_crudo_en_cero_o_negativo_responde_422_sobre_el_campo(): void
    {
        foreach (['off', 'on'] as $modo) {
            $this->modo($modo);
            foreach (['no_tiras', 'peso_crudo'] as $campo) {
                foreach ([0, -1] as $valor) {
                    $antes = $this->fotoSuperficies();

                    // Las fórmulas de rollos necesitan > 0: 422 sobre el campo, no 500 genérico.
                    $this->como()->putJson('/planeacion/programa-tejido/2', [$campo => $valor])
                        ->assertStatus(422)->assertJsonValidationErrors([$campo]);

                    $this->assertEquals($antes, $this->fotoSuperficies(), "{$modo} {$campo}={$valor}");
                }
            }
        }
    }

    public function test_decimales_en_columnas_enteras_responden_422(): void
    {
        // NoTiras y Peine son int en SQL Server: un decimal se rechaza en la regla en vez de
        // fallar la conversión (500) o truncar.
        foreach (['no_tiras', 'peine'] as $campo) {
            $antes = $this->fotoSuperficies();
            $this->como()->putJson('/planeacion/programa-tejido/2', [$campo => 52.7])
                ->assertStatus(422)->assertJsonValidationErrors([$campo]);
            $this->assertEquals($antes, $this->fotoSuperficies(), $campo);
        }
    }

    public function test_guardar_dos_veces_el_mismo_calendario_mueve_la_fecha_final_si_horas_prod_no_cuadraba(): void
    {
        $this->como()->putJson('/planeacion/programa-tejido/2', ['calendario_id' => 'CAL2'])->assertOk();
        $primera = $this->fila('ReqProgramaTejido', 2);
        $this->como()->putJson('/planeacion/programa-tejido/2', ['calendario_id' => 'CAL2'])->assertOk();
        $segunda = $this->fila('ReqProgramaTejido', 2);

        // La primera usa el HorasProd guardado (10 h) y deja HorasProd recalculado (14.51 h);
        // la segunda ya usa 14.51 h. CARACTERIZA: re-guardar el mismo calendario no es
        // idempotente cuando HorasProd en BD no coincidía con la fórmula.
        $this->assertSame('2026-09-04 12:00:00', $primera['FechaFinal']);
        $this->assertEquals(14.51, $primera['HorasProd']);
        $this->assertNotSame($primera['FechaFinal'], $segunda['FechaFinal']);
        $this->assertSame('2026-09-03 18:00:00', $segunda['FechaInicio']);
    }

    // ------------------------------------------------------------------ helpers de foto

    private function fotoSinUpdatedAt(): array
    {
        return collect($this->fotoSuperficies())
            ->map(fn ($filas) => array_map(fn ($f) => array_diff_key($f, ['UpdatedAt' => 1]), $filas))
            ->all();
    }

    private function fotoSinLineasPrograma(): array
    {
        $foto = [];
        foreach (['ReqProgramaTejido', 'MuestrasPrograma', 'MuestrasProgramaLine', 'CatCodificados'] as $tabla) {
            $foto[$tabla] = DB::table($tabla)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $foto;
    }

    private function filasFueraDelTelar(string $telar): array
    {
        return array_values(array_filter($this->fotoSuperficies()['ReqProgramaTejido'], fn ($f) => (string) $f['NoTelarId'] !== $telar));
    }
}
