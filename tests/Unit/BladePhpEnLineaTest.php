<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Blade empareja un @php(...) en línea con el siguiente @endphp de la plantilla: todo lo de en medio
 * sale como texto y las variables del bloque no existen (Liberar Órdenes: "Undefined variable $columns").
 * Compilado queda como "<?php(" sin espacio, que PHP no reconoce como etiqueta de apertura.
 */
class BladePhpEnLineaTest extends TestCase
{
    public function test_ninguna_vista_mezcla_php_en_linea_antes_de_un_bloque_php(): void
    {
        $rotas = [];
        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }
            if (str_contains(Blade::compileString($archivo->getContents()), '<?php(')) {
                $rotas[] = $archivo->getRelativePathname();
            }
        }

        $this->assertSame([], $rotas, 'Usa @php ... @endphp en lugar de @php(...) antes de un bloque @php');
    }
}
