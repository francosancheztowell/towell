<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadComparison;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-02 · 02.3/02.4 — Lectura v2 (flag apagado por default) y comparación shadow.
 */
class ProgramaTejidoReadV2Test extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    private function leer(string $superficie, array $query = [], ?array $permisos = null)
    {
        $idrol = $superficie === 'muestras' ? 5 : 2;
        $ruta = $superficie === 'muestras' ? 'muestras.v2.registros' : 'programa-tejido.v2.registros';

        return $this->actingAs($this->usuarioConPermisos($permisos ?? [$idrol => ['acceso']]))
            ->getJson(route($ruta, $query));
    }

    public function test_con_el_flag_apagado_responde_404(): void
    {
        $this->assertFalse(config('planeacion.read_v2.programa'));
        $this->assertFalse(config('planeacion.read_v2.muestras'));

        $this->leer('programa')->assertNotFound();
        $this->leer('muestras')->assertNotFound();
    }

    public function test_devuelve_la_superficie_pedida_en_el_orden_de_la_grilla(): void
    {
        config()->set('planeacion.read_v2', ['programa' => true, 'muestras' => true, 'shadow_sample' => 0]);

        $programa = $this->leer('programa', ['columnas' => ['NoTelarId', 'NoProduccion']])->assertOk();
        $muestras = $this->leer('muestras', ['columnas' => ['NoTelarId', 'NoProduccion']])->assertOk();

        // Mismo orden que ReqProgramaTejido::ordenado() (telar numérico, salón, posición).
        $legacy = ReqProgramaTejido::query()->ordenado()->pluck('Id')->all();
        $this->assertSame($legacy, array_column($programa->json('data'), 'Id'));
        $this->assertSame(['Id', 'NoTelarId', 'NoProduccion'], array_keys($programa->json('data.0')));
        $this->assertSame('30001', collect($programa->json('data'))->firstWhere('Id', 1)['NoProduccion']);
        $this->assertSame('90001', collect($muestras->json('data'))->firstWhere('Id', 1)['NoProduccion']);
        $this->assertSame(['superficie' => 'muestras', 'total' => 5, 'por_pagina' => 50], [
            'superficie' => $muestras->json('meta.superficie'), 'total' => $muestras->json('meta.total'), 'por_pagina' => $muestras->json('meta.por_pagina'),
        ]);
        $this->assertFalse($muestras->json('meta.capacidades.descarga'));
    }

    public function test_filtra_ordena_y_pagina_solo_con_allowlist(): void
    {
        config()->set('planeacion.read_v2.programa', true);

        $r = $this->leer('programa', ['filtros' => ['SalonTejidoId' => 'SMIT'], 'sort' => 'NoTelarId', 'dir' => 'desc', 'per_page' => 50])->assertOk();
        $this->assertSame([5, 1, 2], array_column($r->json('data'), 'Id'), 'telar 204 primero, luego 201 por posición');
        $this->assertSame(3, $r->json('meta.total'));

        $this->leer('programa', ['filtros' => ['1=1; DROP TABLE x' => 1]])->assertStatus(422)->assertJsonValidationErrors('filtros');
        $this->leer('programa', ['sort' => 'Id desc; --'])->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->leer('programa', ['per_page' => 5000])->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->leer('programa', ['columnas' => ['contrasenia']])->assertStatus(422);
        // Tipos: un valor que SQL Server no puede convertir es 422, no 500.
        $this->leer('programa', ['filtros' => ['FechaInicio' => 'abc']])->assertStatus(422)->assertJsonValidationErrors('filtros.FechaInicio');
        $this->leer('programa', ['filtros' => ['TotalPedido' => 'x']])->assertStatus(422)->assertJsonValidationErrors('filtros.TotalPedido');
        $this->leer('programa', ['filtros' => ['TotalPedido' => '1000']])->assertOk();
    }

    public function test_el_sort_por_una_columna_del_orden_legacy_no_la_repite(): void
    {
        // SQL Server rechaza la misma columna dos veces en ORDER BY (Msg 169).
        config()->set('planeacion.read_v2.programa', true);
        DB::enableQueryLog();

        foreach (['NoTelarId', 'SalonTejidoId', 'FechaInicio', 'Id'] as $columna) {
            $this->leer('programa', ['sort' => $columna])->assertOk();
        }

        foreach (DB::getQueryLog() as $q) {
            if (! str_contains($q['query'], 'order by')) {
                continue;
            }
            $orden = substr($q['query'], strrpos($q['query'], 'order by'));
            preg_match_all('/"(\w+)" (?:asc|desc)/', $orden, $m);
            $this->assertSame(array_unique($m[1]), $m[1], $orden);
        }
    }

    public function test_muestras_no_expone_columnas_que_no_tiene(): void
    {
        config()->set('planeacion.read_v2.muestras', true);

        foreach (config('planeacion.superficies.muestras.columnas_ausentes') as $columna) {
            $this->assertNotContains($columna, app(\App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService::class)->columnasPermitidas(ProgramaTejidoSurface::Muestras));
        }
        $this->leer('muestras')->assertOk();
    }

    public function test_leer_no_escribe_nada(): void
    {
        config()->set('planeacion.read_v2', ['programa' => true, 'muestras' => true, 'shadow_sample' => 0]);
        $antes = $this->fotoSuperficies();
        DB::enableQueryLog();

        $this->leer('programa')->assertOk();
        $this->leer('muestras')->assertOk();

        $this->assertSame($antes, $this->fotoSuperficies());
        $escrituras = array_filter(DB::getQueryLog(), fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q['query']));
        $this->assertSame([], array_values($escrituras));
    }

    public function test_sin_permiso_de_acceso_no_lee_aunque_el_flag_este_encendido(): void
    {
        config()->set('planeacion.read_v2.muestras', true);

        $this->leer('muestras', [], [2 => ['acceso']])->assertForbidden();
    }

    public function test_shadow_sin_divergencias_no_registra_nada(): void
    {
        $avisos = $this->escucharAvisos();

        $resultado = app(ProgramaTejidoReadComparison::class)->comparar(
            ProgramaTejidoSurface::Programa,
            ReqProgramaTejido::query()->ordenado()->get()
        );

        $this->assertSame(['faltan' => [], 'sobran' => [], 'campos' => [], 'orden' => true], $resultado);
        $this->assertSame([], $avisos->all());
    }

    public function test_shadow_registra_una_divergencia_forzada(): void
    {
        $avisos = $this->escucharAvisos();
        $legacy = ReqProgramaTejido::query()->ordenado()->get();
        // Divergencia: la fila cambia en BD después de la lectura legacy.
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['TotalPedido' => 999]);

        $resultado = app(ProgramaTejidoReadComparison::class)->comparar(ProgramaTejidoSurface::Programa, $legacy);

        $this->assertSame([3 => ['TotalPedido']], $resultado['campos']);
        $this->assertCount(1, $avisos);
        $this->assertSame('programa_tejido.read_v2.divergencia', $avisos[0]['mensaje']);
        $this->assertSame('programa', $avisos[0]['contexto']['superficie']);
        $this->assertSame([3 => ['TotalPedido']], $avisos[0]['contexto']['campos']);
        $this->assertArrayNotHasKey('valores', $avisos[0]['contexto'], 'No se loguean valores de negocio');
    }

    public function test_shadow_con_muestra_cero_no_programa_nada(): void
    {
        config()->set('planeacion.read_v2.shadow_sample', 0);
        $avisos = $this->escucharAvisos();

        ProgramaTejidoReadComparison::programar(ProgramaTejidoSurface::Programa, ReqProgramaTejido::query()->get());
        app()->terminate();

        $this->assertSame([], $avisos->all());
    }

    public function test_la_normalizacion_iguala_valores_equivalentes(): void
    {
        $n = fn ($v) => ProgramaTejidoReadComparison::normalizar($v);
        $this->assertSame($n(null), $n(''));
        $this->assertSame($n('1000'), $n(1000.0));
        $this->assertSame($n(true), $n(1));
        $this->assertSame($n(new \DateTime('2026-09-01 06:30:00')), $n('2026-09-01 06:30:00'));
        $this->assertNotSame($n('30001'), $n('30002'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{mensaje: string, contexto: array<string, mixed>}>
     */
    private function escucharAvisos(): \Illuminate\Support\Collection
    {
        $avisos = collect();
        Log::listen(function ($evento) use ($avisos) {
            if ($evento->level === 'warning' && str_starts_with($evento->message, 'programa_tejido.read_v2')) {
                $avisos->push(['mensaje' => $evento->message, 'contexto' => $evento->context]);
            }
        });

        return $avisos;
    }
}
