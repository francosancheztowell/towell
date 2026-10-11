<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngFormulacionLineModel;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Sistema\Usuario;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-urd-eng-mant (Engomado): las consultas que se cambiaron por value(),
 * pluck() o por leer las filas ya cargadas tienen que dar el mismo resultado que antes.
 *
 * - Producción Engomado: "Imprimir producción parcial" se habilita solo con Finalizar=1 e
 *   Impresion NULL/0 (antes un exists() aparte, ahora contains() sobre las filas cargadas).
 * - Producción Engomado: sin orden de urdido o con urdido no Finalizado redirige (antes first()).
 * - Programa Engomado (getOrdenes): urdido_finalizado sale del pluck Status/Folio.
 * - Captura de fórmula: ordenIdProduccion sale de value('Id').
 */
final class QueryEngVentasEngomadoTest extends TestCase
{
    use ModuloUrdEng;

    private const BASE = '/engomado/modulo-produccion-engomado';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(EngProgramaEngomado::class, [
            'Prioridad', 'RizoPie', 'InventSizeId', 'Fibra', 'BomFormula', 'Observaciones', 'MaquinaEng', 'FechaProg', 'Metros',
        ]);
        $this->tablaDe(EngProduccionEngomado::class);
        $this->tablaDe(EngProduccionFormulacionModel::class);
        $this->tablaDe(UrdProgramaUrdido::class);
        $this->tablaDe(UrdJuliosOrden::class);
        $this->tablaDe(CatUbicaciones::class);

        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1, 'Folio' => 'U00101', 'Status' => 'En Proceso', 'NoTelas' => 2,
        ]);
    }

    private function usuario(): Usuario
    {
        return $this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar'], 43 => ['acceso', 'modificar']]);
    }

    /** @return array<string, array{array<int, array{0: int|null, 1: int|null}>, bool}> */
    public static function casosParcial(): array
    {
        return [
            'finalizado y sin imprimir (NULL)' => [[[1, null], [0, null]], true],
            'finalizado e Impresion=0' => [[[1, 0], [null, null]], true],
            'finalizado pero ya impreso' => [[[1, 1], [0, null]], false],
            'nada finalizado' => [[[0, null], [null, null]], false],
        ];
    }

    /** @param  array<int, array{0: int|null, 1: int|null}>  $filas  [Finalizar, Impresion] */
    #[DataProvider('casosParcial')]
    public function test_boton_imprimir_parcial_sigue_la_regla_finalizar_e_impresion(array $filas, bool $habilitado): void
    {
        $this->withoutVite();
        $this->actingAs($this->usuario());
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'Finalizado']);
        // Tantas filas como NoTelas: la sincronización no agrega renglones nuevos.
        foreach ($filas as [$finalizar, $impresion]) {
            DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert([
                'Folio' => 'U00101', 'Finalizar' => $finalizar, 'Impresion' => $impresion, 'HoraInicial' => '08:00',
            ]);
        }

        $html = (string) $this->get(self::BASE.'?orden_id=1')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<button[^>]*id="btn-imprimir-parcial"[^>]*>/s', $html, $m), 'Falta el botón de impresión parcial');
        if ($habilitado) {
            $this->assertDoesNotMatchRegularExpression('/\sdisabled\s/', $m[0]);
            $this->assertStringContainsString('title="Imprimir producción parcial"', $m[0]);
        } else {
            $this->assertMatchesRegularExpression('/\sdisabled\s/', $m[0]);
        }
    }

    public function test_sin_orden_de_urdido_redirige_al_programa(): void
    {
        $this->actingAs($this->usuario())
            ->get(self::BASE.'?orden_id=1')
            ->assertRedirect(route('engomado.programar.engomado'))
            ->assertSessionHas('error');
    }

    public function test_urdido_no_finalizado_redirige_y_no_crea_renglones(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'En Proceso']);

        $this->actingAs($this->usuario())
            ->get(self::BASE.'?orden_id=1')
            ->assertRedirect(route('engomado.programar.engomado'));

        $this->assertSame(0, DB::connection('sqlsrv')->table('EngProduccionEngomado')->count());
    }

    public function test_solidos_de_la_formulacion_llegan_a_los_renglones_nuevos(): void
    {
        $this->withoutVite();
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'Finalizado']);
        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert(['Folio' => 'U00101', 'Solidos' => 11.25]);

        $this->actingAs($this->usuario())->get(self::BASE.'?orden_id=1')->assertOk();

        $solidos = DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', 'U00101')->pluck('Solidos')->all();
        $this->assertCount(2, $solidos);
        foreach ($solidos as $s) {
            $this->assertEquals(11.25, $s);
        }
    }

    public function test_formulacion_sin_solidos_deja_solidos_en_null(): void
    {
        $this->withoutVite();
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'Finalizado']);
        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert(['Folio' => 'U00101', 'Solidos' => null]);

        $this->actingAs($this->usuario())->get(self::BASE.'?orden_id=1')->assertOk();

        $this->assertSame(2, DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', 'U00101')->whereNull('Solidos')->count());
    }

    public function test_get_ordenes_marca_urdido_finalizado_por_folio(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('EngProgramaEngomado')->delete();
        $db->table('EngProgramaEngomado')->insert([
            ['Id' => 1, 'Folio' => 'F-FIN', 'Status' => 'Programado', 'MaquinaEng' => 'West Point 2', 'FechaProg' => '2026-10-01'],
            ['Id' => 2, 'Folio' => 'F-PROC', 'Status' => 'Programado', 'MaquinaEng' => 'West Point 2', 'FechaProg' => '2026-10-02'],
            ['Id' => 3, 'Folio' => 'F-SIN', 'Status' => 'En Proceso', 'MaquinaEng' => 'West Point 3', 'FechaProg' => '2026-10-03'],
        ]);
        $db->table('UrdProgramaUrdido')->insert([
            ['Folio' => 'F-FIN', 'Status' => 'Finalizado'],
            ['Folio' => 'F-PROC', 'Status' => 'En Proceso'],
            ['Folio' => 'OTRO', 'Status' => 'Finalizado'],
        ]);

        $data = $this->actingAs($this->usuario())
            ->getJson(route('engomado.programar.engomado.ordenes'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $porFolio = collect([...$data[1], ...$data[2]])->keyBy('folio');
        $this->assertCount(3, $porFolio);
        $this->assertTrue($porFolio['F-FIN']['urdido_finalizado']);
        $this->assertFalse($porFolio['F-PROC']['urdido_finalizado']);
        // Sin fila en UrdProgramaUrdido: false, no null ni error.
        $this->assertFalse($porFolio['F-SIN']['urdido_finalizado']);
    }

    public function test_captura_formula_enlaza_la_orden_del_folio(): void
    {
        $this->tablaDe(EngFormulacionLineModel::class);
        $this->tablaDe(SYSUsuario::class);
        $this->tablaDe(URDCatalogoMaquina::class);
        $u = $this->usuarioCon([168 => ['acceso', 'crear', 'modificar']]);
        DB::connection('sqlsrv')->table('SYSRoles')->where('idrol', 168)->update(['modulo' => 'Captura de Formula']);
        $this->actingAs($u)->withoutVite();

        $r = $this->get('/engomado/capturadeformula?folio=U00101')->assertOk();
        $this->assertSame(1, $r->viewData('ordenIdProduccion'));

        $sinOrden = $this->get('/engomado/capturadeformula?folio=NO-EXISTE')->assertOk();
        $this->assertNull($sinOrden->viewData('ordenIdProduccion'));

        $sinFolio = $this->get('/engomado/capturadeformula')->assertOk();
        $this->assertNull($sinFolio->viewData('ordenIdProduccion'));
    }
}
