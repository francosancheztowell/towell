<?php

/**
 * Script para reorganizar el orden de los módulos basándose en Dependencia y Nivel
 *
 * Este script reorganiza la tabla SYSRoles para que el campo 'orden' refleje
 * correctamente la jerarquía definida por los campos 'Dependencia' y 'Nivel'.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\SYSRoles;

// Configurar Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔄 Iniciando reorganización de módulos...\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    // Obtener todos los módulos ordenados por Dependencia y Nivel
    $modulos = SYSRoles::orderBy('Dependencia', 'ASC')
        ->orderBy('Nivel', 'ASC')
        ->orderBy('modulo', 'ASC')
        ->get();

    echo "📊 Se encontraron {$modulos->count()} módulos para reorganizar.\n\n";

    // Crear un array para mapear los nuevos órdenes
    $nuevosOrdenes = [];
    $contador = 1;

    // Procesar módulos nivel por nivel
    procesarNivel($modulos, null, $contador, $nuevosOrdenes, 1);

    // Mostrar los cambios antes de aplicar
    echo "\n📋 Cambios a realizar:\n";
    echo "-" . str_repeat("-", 60) . "\n";
    echo sprintf("%-5s | %-30s | %-8s | %-8s\n", "ID", "Módulo", "Orden Actual", "Nuevo Orden");
    echo "-" . str_repeat("-", 60) . "\n";

    foreach ($nuevosOrdenes as $idrol => $nuevoOrden) {
        $modulo = $modulos->find($idrol);
        if ($modulo) {
            echo sprintf("%-5s | %-30s | %-8s | %-8s\n",
                $modulo->idrol,
                substr($modulo->modulo, 0, 30),
                $modulo->orden,
                $nuevoOrden
            );
        }
    }

    echo "\n¿Desea continuar con la actualización? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "❌ Operación cancelada por el usuario.\n";
        exit(0);
    }

    // Actualizar la base de datos
    DB::beginTransaction();

    $actualizados = 0;
    foreach ($nuevosOrdenes as $idrol => $nuevoOrden) {
        SYSRoles::where('idrol', $idrol)->update(['orden' => $nuevoOrden]);
        $actualizados++;
    }

    DB::commit();

    echo "\n✅ Reorganización completada exitosamente!\n";
    echo "📈 Se actualizaron {$actualizados} módulos.\n\n";

    // Mostrar el nuevo orden
    mostrarNuevoOrden();

} catch (\Exception $e) {
    DB::rollback();
    echo "❌ Error durante la reorganización: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Procesa recursivamente los módulos por nivel
 */
function procesarNivel($modulos, $dependenciaPadre, &$contador, &$nuevosOrdenes, $nivelActual)
{
    // Filtrar módulos del nivel actual
    $modulosNivel = $modulos->filter(function ($modulo) use ($dependenciaPadre, $nivelActual) {
        return $modulo->Dependencia == $dependenciaPadre && $modulo->Nivel == $nivelActual;
    })->sortBy('modulo');

    foreach ($modulosNivel as $modulo) {
        $nuevosOrdenes[$modulo->idrol] = $contador;
        $indentacion = str_repeat("  ", $nivelActual - 1);
        $prefijo = $nivelActual == 1 ? "📁" : ($nivelActual == 2 ? "📂" : "📄");

        echo "{$indentacion}{$prefijo} [{$contador}] {$modulo->modulo} (Nivel: {$modulo->Nivel}, Dep: {$modulo->Dependencia})\n";
        $contador++;

        // Procesar submódulos si existen
        procesarNivel($modulos, $modulo->orden, $contador, $nuevosOrdenes, $nivelActual + 1);
    }
}

/**
 * Muestra el nuevo orden jerárquico
 */
function mostrarNuevoOrden()
{
    echo "\n📋 Nuevo orden jerárquico:\n";
    echo "=" . str_repeat("=", 60) . "\n";

    $modulos = SYSRoles::orderBy('orden')->get();

    foreach ($modulos as $modulo) {
        $indentacion = str_repeat("  ", $modulo->Nivel - 1);
        $prefijo = $modulo->Nivel == 1 ? "📁" : ($modulo->Nivel == 2 ? "📂" : "📄");

        echo sprintf("%s%s [%s] %s (Nivel: %s, Dep: %s)\n",
            $indentacion,
            $prefijo,
            $modulo->orden,
            $modulo->modulo,
            $modulo->Nivel,
            $modulo->Dependencia ?? 'NULL'
        );
    }

    echo "\n🎉 ¡Reorganización completada!\n";
}


































































