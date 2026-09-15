<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regresión de las guardas de Duplicar/Dividir: observers restaurados en los early return,
 * salón normalizado antes de comparar el registro origen, y lock al leer posiciones.
 * Sin BD: se verifica el código fuente porque estos flujos requieren SQL Server.
 */
class ProgramaTejidoMutacionesGuardsTest extends TestCase
{
    private function src(string $rel): string
    {
        $path = base_path($rel);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_duplicar_restaura_observers_en_cada_salida(): void
    {
        $src = $this->src('app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php');

        // Cada rollBack() debe ir seguido de restoreObservers(): si no, el dispatcher
        // queda desconectado para el resto del request.
        $this->assertSame(
            substr_count($src, 'DBFacade::rollBack();'),
            preg_match_all('/DBFacade::rollBack\(\);\s*\n\s*ReqProgramaTejido::restoreObservers/', $src),
            'Hay un rollBack() sin restoreObservers() en DuplicarTejido'
        );
    }

    public function test_duplicar_vincular_mete_el_origen_al_grupo(): void
    {
        $src = $this->src('app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php');
        $this->assertStringContainsString('$original->OrdCompartida = $ordCompartidaAVincular;', $src);
    }

    public function test_dividir_normaliza_salon_y_no_cae_a_otra_fila(): void
    {
        $src = $this->src('app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DividirTejido.php');

        $this->assertStringContainsString(
            "TelarSalonResolver::normalizeSalon(\$request->input('salon_tejido_id'), \$telarOrigen)",
            $src
        );
        $this->assertStringContainsString('no pertenece al telar indicado', $src);

        $this->assertSame(
            substr_count($src, 'DBFacade::rollBack();'),
            preg_match_all('/DBFacade::rollBack\(\);\s*\n\s*ReqProgramaTejido::restoreObservers/', $src),
            'Hay un rollBack() sin restoreObservers() en DividirTejido'
        );
    }

    public function test_posicion_destino_se_lee_con_lock(): void
    {
        $this->assertStringContainsString(
            '->lockForUpdate()',
            $this->src('app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php')
        );
    }

    public function test_ruta_vincular_telar_muerta_no_existe(): void
    {
        $this->assertStringNotContainsString(
            'vincularTelar',
            $this->src('routes/modules/planeacion.php')
        );
    }
}
