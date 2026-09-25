<?php

namespace Tests\Unit\Helpers;

use App\Models\UrdEngomado\UrdEngNucleos;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * BUG-022: app/Models/UrdEngomado y app/Models/urdengomado eran dos carpetas en
 * Linux y una sola en Windows (donde se desarrolla). PSR-4 en Linux cargaba solo
 * una y borrar la otra del indice tumbaba el archivo real al hacer pull en
 * Windows. Este test impide que vuelva a entrar una ruta que solo difiera en
 * mayusculas.
 */
class RutasSinColisionDeMayusculasTest extends TestCase
{
    public function test_ninguna_ruta_de_app_colisiona_al_ignorar_mayusculas(): void
    {
        $vistas = [];
        $colisiones = [];

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('app'), RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterador as $ruta => $info) {
            $clave = strtolower($ruta);
            if (isset($vistas[$clave]) && $vistas[$clave] !== $ruta) {
                $colisiones[] = $vistas[$clave].' <-> '.$ruta;
            }
            $vistas[$clave] = $ruta;
        }

        $this->assertSame([], $colisiones);
    }

    public function test_urd_eng_nucleos_resuelve_en_su_ruta_canonica(): void
    {
        $archivo = (string) (new \ReflectionClass(UrdEngNucleos::class))->getFileName();

        // Separadores normalizados: en Windows Reflection devuelve '\'.
        $this->assertSame(
            str_replace('\\', '/', base_path('app/Models/UrdEngomado/UrdEngNucleos.php')),
            str_replace('\\', '/', $archivo)
        );
    }
}
