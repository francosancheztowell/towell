<?php
/**
 * TEST: Verificar que CREATE genera líneas en ReqProgramaTejidoLine
 * Simula una petición POST al endpoint /planeacion/programa-tejido
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use App\Http\Controllers\ProgramaTejidoController;

echo "🧪 TEST: Crear programa y verificar líneas diarias\n";
echo "=".str_repeat("=", 50)."\n\n";

try {
    // Limpiar registros anteriores de test
    $anterior = ReqProgramaTejido::where('SalonTejidoId', 'SMIT')
        ->where('NoTelarId', '999')
        ->first();

    if ($anterior) {
        ReqProgramaTejidoLine::where('ProgramaId', $anterior->Id)->delete();
        $anterior->delete();
        echo "✓ Limpiado registro anterior de test\n";
    }

    // Crear request simulado
    $payload = [
        'salon_tejido_id' => 'SMIT',
        'tamano_clave' => 'MB7304',
        'hilo' => 'RZ',
        'idflog' => 'None',
        'calendario_id' => 'CalendarioTej3',
        'aplicacion_id' => 'RZ',
        'telares' => [
            [
                'no_telar_id' => '999',
                'cantidad' => 5000,
                'fecha_inicio' => '2025-11-01',
                'fecha_final' => '2025-11-05',
            ]
        ],
        'NombreProducto' => 'TEST-PRODUCTO',
        'NombreProyecto' => 'TEST-PROYECTO',
        'CalibreTrama' => 12.1,
        'CalibreComb12' => 8.86,
        'CalibreComb22' => 10.0,
        'FibraTrama' => 'VISCOSA',
        'FibraComb1' => 'RAYON',
        'FibraComb2' => 'TERMO',
        'CuentaRizo' => 2766,
        'CalibreRizo' => 16.1,
        'AnchoToalla' => 70,
        'PesoCrudo' => 330,
        'NoTiras' => 3,
        'Luchaje' => 25,
        'PasadasTrama' => 1660,
        'CalibrePie' => 10.0,
        'CuentaPie' => 3278,
        'FibraPie' => 'OPEN',
        'EficienciaSTD' => 0.8,
        'VelocidadSTD' => 400.0,
        'Maquina' => 'SMI 999'
    ];

    // Crear request
    $request = new Request($payload);
    $request->setMethod('POST');

    // Ejecutar controlador
    $controller = new ProgramaTejidoController();
    $response = $controller->store($request);
    $data = json_decode($response->getContent(), true);

    if (!$data['success']) {
        echo "❌ Error al crear programa: " . $data['message'] . "\n";
        exit(1);
    }

    echo "✓ Programa creado exitosamente\n";

    // El modelo no tiene autoincrement, así que buscar el registro recién creado
    $programa = ReqProgramaTejido::where('SalonTejidoId', 'SMIT')
        ->where('NoTelarId', '999')
        ->where('TamanoClave', 'MB7304')
        ->latest('CreatedAt')
        ->first();

    if (!$programa) {
        echo "❌ No se encontró el programa creado\n";
        exit(1);
    }

    $programaId = $programa->Id;
    echo "  ID: " . $programaId . "\n\n";

    // Verificar que se crearon líneas
    $lineas = ReqProgramaTejidoLine::where('ProgramaId', $programaId)->get();

    echo "📊 RESULTADO:\n";
    echo "  Total de líneas creadas: " . count($lineas) . "\n";

    if (count($lineas) === 0) {
        echo "\n❌ ERROR: No se crearon líneas diarias\n";
        exit(1);
    }

    echo "\n  Primeras 3 líneas:\n";
    foreach ($lineas->take(3) as $line) {
        echo "  - Fecha: {$line->Fecha}\n";
        echo "    Cantidad: " . round($line->Cantidad, 2) . " pzas\n";
        echo "    Kilos: " . round($line->Kilos, 2) . " kg\n";
        echo "    Trama: " . round($line->Trama, 2) . " kg\n";
        echo "    Rizo: " . round($line->Rizo, 2) . " kg\n";
    }

    echo "\n✅ TEST PASÓ: Las líneas se crearon correctamente en CREATE\n";

} catch (\Throwable $e) {
    echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
