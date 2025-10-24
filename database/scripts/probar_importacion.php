<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Imports\ReqModelosCodificadosImport;
use Maatwebsite\Excel\Facades\Excel;

// Configurar Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROBANDO IMPORTACIÓN DE PLANTILLA ===\n\n";

try {
    // Verificar que el archivo existe
    $archivo = 'plantilla_codificacion_ejemplo.xlsx';
    if (!file_exists($archivo)) {
        echo "ERROR: No se encontró el archivo $archivo\n";
        exit(1);
    }

    echo "Archivo encontrado: $archivo\n";
    echo "Tamaño: " . number_format(filesize($archivo)) . " bytes\n\n";

    // Importar el archivo
    echo "Iniciando importación...\n";
    $import = new ReqModelosCodificadosImport();
    $resultado = Excel::import($import, $archivo);

    echo "=== RESULTADOS DE LA IMPORTACIÓN ===\n";
    echo "Filas procesadas: " . $import->getRowCount() . "\n";
    echo "Registros creados: " . $import->getCreatedCount() . "\n";
    echo "Registros actualizados: " . $import->getUpdatedCount() . "\n";

    $errores = $import->getErrors();
    echo "Total de errores: " . $errores['total_errores'] . "\n";

    if ($errores['total_errores'] > 0) {
        echo "\n=== PRIMEROS ERRORES ===\n";
        foreach ($errores['primeros'] as $error) {
            echo "Fila {$error['fila']}: {$error['error']}\n";
        }
    } else {
        echo "\n✅ ¡IMPORTACIÓN EXITOSA SIN ERRORES!\n";
    }

    // Verificar que los datos se guardaron correctamente
    echo "\n=== VERIFICANDO DATOS GUARDADOS ===\n";
    $registros = \App\Models\ReqModelosCodificados::orderBy('Id', 'desc')->limit(5)->get();
    echo "Últimos 5 registros en la base de datos:\n";
    foreach ($registros as $reg) {
        echo "- ID: {$reg->Id}, Clave: {$reg->TamanoClave}, Orden: {$reg->OrdenTejido}, Modelo: {$reg->Nombre}\n";
    }

} catch (\Exception $e) {
    echo "ERROR durante la importación: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== PRUEBA COMPLETADA ===\n";
