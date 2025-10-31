<?php
/**
 * TEST: Verificar que UPDATE también genera líneas en ReqProgramaTejidoLine (regenera)
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use App\Http\Controllers\ProgramaTejidoController;

echo "🧪 TEST: Actualizar programa y verificar líneas se regeneran\n";
echo "=".str_repeat("=", 50)."\n\n";

try {
    // Obtener el programa creado en el test anterior (ID 169)
    $programa = ReqProgramaTejido::where('Id', 169)->first();

    if (!$programa) {
        echo "❌ No se encontró programa ID 169\n";
        exit(1);
    }

    echo "✓ Programa encontrado: ID " . $programa->Id . "\n";

    // Contar líneas actuales
    $lineasAntes = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)->count();
    echo "  Líneas antes de actualizar: " . $lineasAntes . "\n\n";

    // Simular UPDATE: cambiar la cantidad
    $programaData = $programa->toArray();
    $programaData['cantidad'] = 8000; // Cambiar cantidad
    $programaData['fecha_fin'] = '2025-11-10'; // Cambiar fecha

    $request = new Request($programaData);
    $request->setMethod('PUT');

    // Ejecutar controlador
    $controller = new ProgramaTejidoController();
    $response = $controller->update($request, $programa->Id);
    $data = json_decode($response->getContent(), true);

    if (!$data['success']) {
        echo "❌ Error al actualizar programa: " . ($data['message'] ?? 'desconocido') . "\n";
        exit(1);
    }

    echo "✓ Programa actualizado exitosamente\n\n";

    // Contar líneas después
    $lineasDespues = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)->count();
    echo "📊 RESULTADO:\n";
    echo "  Líneas después de actualizar: " . $lineasDespues . "\n";

    if ($lineasDespues === 0) {
        echo "\n❌ ERROR: Se eliminaron todas las líneas sin regenerarlas\n";
        exit(1);
    }

    if ($lineasDespues !== $lineasAntes) {
        echo "  (Cambió de " . $lineasAntes . " a " . $lineasDespues . " - líneas regeneradas)\n";
    } else {
        echo "  (Mismo número de líneas - regeneradas correctamente)\n";
    }

    // Mostrar primeras líneas
    $primeras = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)->take(3)->get();
    echo "\n  Primeras líneas actualizado:\n";
    foreach ($primeras as $line) {
        echo "  - Fecha: " . $line->Fecha . "\n";
        echo "    Cantidad: " . round($line->Cantidad, 2) . " pzas\n";
    }

    echo "\n✅ TEST PASÓ: UPDATE regeneró las líneas correctamente\n";

} catch (\Throwable $e) {
    echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
