<?php

namespace Tests\Feature\Planeacion;

use App\Http\Middleware\ProgramaTejidoContext;
use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-01 · 01.4 — Programa y Muestras no se pisan.
 *
 * Cada test toma una foto de las 4 tablas + CatCodificados, muta UNA superficie dentro
 * de una transacción y comprueba que solo cambió lo esperado. Los Id se solapan entre
 * tablas (ver ProgramaTejidoFixtures), así que una escritura cruzada se ve en la foto.
 */
class ProgramaTejidoIsolationTest extends TestCase
{
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uris(): array
    {
        return [
            'index programa' => ['/planeacion/programa-tejido', false],
            'update programa' => ['/planeacion/programa-tejido/1', false],
            'catalogo programa' => ['/programa-tejido/telares-all', false],
            'lineas programa' => ['/planeacion/req-programa-tejido-line', false],
            'utileria (solo programa)' => ['/planeacion/utileria/finalizar/procesar', false],
            'index muestras' => ['/planeacion/muestras', true],
            'update muestras' => ['/planeacion/muestras/1', true],
            'lineas muestras' => ['/planeacion/muestras-line', true],
            'catalogo muestras' => ['/muestras/telares-all', true],
            // Invertido en PT-02 (hallazgo 8): antes 'muestras*' atrapaba cualquier URI que
            // solo empezara igual. Ahora cuenta el segmento completo.
            'prefijo ambiguo' => ['/muestrasx/cualquier-cosa', false],
            'prefijo ambiguo bajo planeacion' => ['/planeacion/muestrasx', false],
            'utileria nunca es muestras (finalizacion B)' => ['/planeacion/utileria/finalizar/ordenes', false],
            'desarrolladores-muestras NO cambia tabla' => ['/desarrolladores-muestras', false],
        ];
    }

    #[DataProvider('uris')]
    public function test_el_middleware_elige_la_tabla_por_uri(string $uri, bool $esMuestras): void
    {
        config()->set('planeacion.programa_tejido_table', null);
        config()->set('planeacion.programa_tejido_line_table', null);

        $visto = null;
        (new ProgramaTejidoContext)->handle(Request::create($uri), function () use (&$visto) {
            $visto = [ReqProgramaTejido::tableName(), ReqProgramaTejidoLine::tableName()];

            return response('ok');
        });

        $this->assertSame(
            $esMuestras ? ['MuestrasPrograma', 'MuestrasProgramaLine'] : ['ReqProgramaTejido', 'ReqProgramaTejidoLine'],
            $visto
        );
    }

    public function test_el_modelo_muestras_ignora_el_contexto_por_url(): void
    {
        $this->usarSuperficie('programa');
        $this->assertSame('90001', Muestras::find(1)->NoProduccion);
        $this->assertSame('MuestrasProgramaLine', (new Muestras)->lineas()->getRelated()->getTable());

        $this->usarSuperficie('muestras');
        $this->assertSame('90001', ReqProgramaTejido::find(1)->NoProduccion);
    }

    public function test_leer_una_superficie_no_escribe_nada(): void
    {
        $antes = $this->fotoSuperficies();

        foreach (['programa', 'muestras'] as $superficie) {
            $this->usarSuperficie($superficie);
            ReqProgramaTejido::query()->ordenado()->get();
            ReqProgramaTejido::find(1)->lineas()->get();
            ReqProgramaTejido::getOrdenesProgramadas('201', 'SMIT');
        }

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mutar_muestras_no_toca_programa_ni_sus_lineas(): void
    {
        $antes = $this->fotoSuperficies();
        $this->usarSuperficie('muestras');

        DB::transaction(function () {
            $registro = ReqProgramaTejido::find(1);
            $registro->Observaciones = 'editado en muestras';
            $registro->SaldoPedido = 700; // campo relevante: dispara regeneración de líneas
            $registro->save();
        });

        $despues = $this->fotoSuperficies();

        foreach (['ReqProgramaTejido', 'ReqProgramaTejidoLine', 'CatCodificados'] as $intacta) {
            $this->assertSame($antes[$intacta], $despues[$intacta], "{$intacta} cambió al mutar Muestras");
        }
        $this->assertSame('editado en muestras', DB::table('MuestrasPrograma')->where('Id', 1)->value('Observaciones'));
        $this->assertNotSame($antes['MuestrasProgramaLine'], $despues['MuestrasProgramaLine'], 'El observer no regeneró las líneas de Muestras');
        $this->assertSame(0, DB::table('MuestrasProgramaLine')->where('ProgramaId', 1)->where('Fecha', '2026-09-01')->where('Cantidad', 10)->count());
    }

    public function test_mutar_programa_no_toca_muestras_ni_sus_lineas(): void
    {
        $antes = $this->fotoSuperficies();
        $this->usarSuperficie('programa');

        DB::transaction(function () {
            $registro = ReqProgramaTejido::find(1);
            $registro->Observaciones = 'editado en programa';
            $registro->SaldoPedido = 700;
            $registro->save();
        });

        $despues = $this->fotoSuperficies();

        foreach (['MuestrasPrograma', 'MuestrasProgramaLine'] as $intacta) {
            $this->assertSame($antes[$intacta], $despues[$intacta], "{$intacta} cambió al mutar Programa");
        }
        $this->assertNotSame($antes['ReqProgramaTejidoLine'], $despues['ReqProgramaTejidoLine']);
    }

    public function test_borrar_en_una_superficie_no_borra_el_mismo_id_en_la_otra(): void
    {
        $this->usarSuperficie('muestras');
        DB::transaction(fn () => ReqProgramaTejido::find(5)->delete());

        $this->assertNull(DB::table('MuestrasPrograma')->where('Id', 5)->first());
        $this->assertNotNull(DB::table('ReqProgramaTejido')->where('Id', 5)->first());
    }

    public function test_muestras_no_puede_guardar_columnas_que_solo_existen_en_programa(): void
    {
        $this->usarSuperficie('muestras');
        $antes = $this->fotoSuperficies();

        foreach (config('planeacion.superficies.muestras.columnas_ausentes') as $columna) {
            try {
                DB::transaction(function () use ($columna) {
                    $registro = ReqProgramaTejido::find(2);
                    $registro->setAttribute($columna, 1);
                    $registro->save();
                });
                $this->fail("Guardar {$columna} en Muestras debió fallar: la columna no existe.");
            } catch (QueryException $e) {
                $this->assertStringContainsString($columna, $e->getMessage());
            }
        }

        $this->assertSame($antes, $this->fotoSuperficies(), 'Un save fallido dejó escrituras parciales');
    }

    public function test_el_truncado_usa_los_limites_de_programa_en_ambas_superficies(): void
    {
        // Muestras tiene Observaciones más corta en live (longitud pendiente de RS2), pero
        // StringTruncator no sabe de superficies: corta a 200 en las dos.
        $texto = str_repeat('x', 250);

        foreach (['programa', 'muestras'] as $superficie) {
            $this->usarSuperficie($superficie);
            $registro = ReqProgramaTejido::find(2);
            $registro->Observaciones = $texto;
            $registro->save();

            $tabla = config("planeacion.superficies.{$superficie}.tabla");
            $this->assertSame(200, mb_strlen(DB::table($tabla)->where('Id', 2)->value('Observaciones')));
        }
    }
}
