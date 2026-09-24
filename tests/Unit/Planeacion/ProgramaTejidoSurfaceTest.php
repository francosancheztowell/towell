<?php

namespace Tests\Unit\Planeacion;

use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Http\Exceptions\HttpResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PT-02 · 02.1 — Resolución explícita de superficie por familia de rutas.
 */
class ProgramaTejidoSurfaceTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ProgramaTejidoSurface}>
     */
    public static function rutas(): array
    {
        return [
            'index programa' => ['planeacion/programa-tejido', ProgramaTejidoSurface::Programa],
            'mutación programa' => ['planeacion/programa-tejido/5/cambiar-telar', ProgramaTejidoSurface::Programa],
            'catálogo programa' => ['programa-tejido/telares-all', ProgramaTejidoSurface::Programa],
            'líneas programa' => ['planeacion/req-programa-tejido-line', ProgramaTejidoSurface::Programa],
            'utilería' => ['planeacion/utileria/finalizar/procesar', ProgramaTejidoSurface::Programa],
            'index muestras' => ['planeacion/muestras', ProgramaTejidoSurface::Muestras],
            'con barra inicial' => ['/planeacion/muestras/', ProgramaTejidoSurface::Muestras],
            'mutación muestras' => ['planeacion/muestras/5/cambiar-telar', ProgramaTejidoSurface::Muestras],
            'líneas muestras' => ['planeacion/muestras-line', ProgramaTejidoSurface::Muestras],
            'catálogo muestras' => ['muestras/telares-all', ProgramaTejidoSurface::Muestras],
            'ambigua: muestrasx' => ['muestrasx/algo', ProgramaTejidoSurface::Programa],
            'ambigua: planeacion/muestrasx' => ['planeacion/muestrasx', ProgramaTejidoSurface::Programa],
            'ambigua: muestras-line fuera de planeacion' => ['muestras-line', ProgramaTejidoSurface::Programa],
            'desarrolladores-muestras' => ['desarrolladores-muestras', ProgramaTejidoSurface::Programa],
            // Revisión de seguridad: el router decodifica antes de enrutar; la superficie también.
            'codificada %6D' => ['planeacion/%6Duestras/1', ProgramaTejidoSurface::Muestras],
            'codificada %61' => ['muestr%61s/telares-all', ProgramaTejidoSurface::Muestras],
        ];
    }

    #[DataProvider('rutas')]
    public function test_resuelve_la_superficie_por_segmento_completo(string $path, ProgramaTejidoSurface $esperada): void
    {
        $this->assertSame($esperada, ProgramaTejidoSurface::fromPath($path));
    }

    public function test_lee_tablas_modulo_y_capacidades_del_config(): void
    {
        $m = ProgramaTejidoSurface::Muestras;
        $this->assertSame(['MuestrasPrograma', 'MuestrasProgramaLine', 5], [$m->tabla(), $m->tablaLineas(), $m->moduloPermiso()]);
        $this->assertSame(['ReqProgramaTejido', 'ReqProgramaTejidoLine', 2], [
            ProgramaTejidoSurface::Programa->tabla(), ProgramaTejidoSurface::Programa->tablaLineas(), ProgramaTejidoSurface::Programa->moduloPermiso(),
        ]);
        $this->assertTrue($m->soporta('longitudes'));
        $this->assertFalse($m->soporta('descarga'));
        $this->assertFalse($m->soporta('no-existe'), 'Una capacidad sin declarar no se ofrece');
        $this->assertSame(array_fill_keys(array_keys(config('planeacion.superficies.programa.capacidades')), true), ProgramaTejidoSurface::Programa->capacidades());
    }

    public function test_una_capacidad_a_no_se_ofrece_hasta_aplicar_su_ddl(): void
    {
        // marbetes y produccion están decididas A, pero sus columnas siguen en columnas_ausentes.
        $this->assertTrue(config('planeacion.superficies.muestras.capacidades.marbetes'));
        $this->assertFalse(ProgramaTejidoSurface::Muestras->soporta('marbetes'));
        $this->assertFalse(ProgramaTejidoSurface::Muestras->soporta('produccion'));

        // Cuando el DBA aplica pt_muestras_*.sql y se vacía columnas_ausentes, se ofrecen.
        config()->set('planeacion.superficies.muestras.columnas_ausentes', ['IdRedbooth', 'NombreRedbooth']);
        $this->assertTrue(ProgramaTejidoSurface::Muestras->soporta('marbetes'));
        $this->assertTrue(ProgramaTejidoSurface::Muestras->soporta('produccion'));
        $this->assertFalse(ProgramaTejidoSurface::Muestras->soporta('redbooth'), 'B sigue siendo B');
    }

    public function test_una_capacidad_sin_decidir_no_se_ofrece(): void
    {
        config()->set('planeacion.superficies.muestras.capacidades.redbooth', null);

        $this->assertFalse(ProgramaTejidoSurface::Muestras->soporta('redbooth'));
    }

    public function test_exigir_corta_con_422_explicito(): void
    {
        ProgramaTejidoSurface::Programa->exigir('descarga'); // no lanza

        try {
            ProgramaTejidoSurface::Muestras->exigir('descarga');
            $this->fail('Debía lanzar');
        } catch (HttpResponseException $e) {
            $this->assertSame(422, $e->getResponse()->getStatusCode());
            $this->assertSame('descarga', $e->getResponse()->getData(true)['capacidad']);
        }
    }
}
