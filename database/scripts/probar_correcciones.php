<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Imports\ReqModelosCodificadosImport;
use Maatwebsite\Excel\Facades\Excel;

// Configurar Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROBANDO CORRECCIONES DE COLUMNAS AMARILLAS ===\n\n";

try {
    // Crear un archivo de prueba con valores problemáticos
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Encabezados
    $sheet->setCellValue('A1', 'Clave mod.');
    $sheet->setCellValue('B1', 'Orden');
    $sheet->setCellValue('C1', 'Fecha  Orden');
    $sheet->setCellValue('D1', 'Fecha   Cumplimiento');
    $sheet->setCellValue('E1', 'Departamento');
    $sheet->setCellValue('F1', 'Telar Actual');
    $sheet->setCellValue('G1', 'Prioridad');
    $sheet->setCellValue('H1', 'Modelo');
    $sheet->setCellValue('I1', 'CLAVE MODELO');
    $sheet->setCellValue('J1', 'CLAVE  AX');
    $sheet->setCellValue('K1', 'Tamaño');
    $sheet->setCellValue('L1', 'TOLERANCIA');
    $sheet->setCellValue('M1', 'CODIGO DE DIBUJO');
    $sheet->setCellValue('N1', 'Fecha Compromiso');
    $sheet->setCellValue('O1', 'Id Flog');
    $sheet->setCellValue('P1', 'Nombre de Formato Logístico');
    $sheet->setCellValue('Q1', 'Clave');
    $sheet->setCellValue('R1', 'Cantidad a Producir');
    $sheet->setCellValue('S1', 'Peine');
    $sheet->setCellValue('T1', 'Ancho');
    $sheet->setCellValue('U1', 'Largo');
    $sheet->setCellValue('V1', 'P_crudo');
    $sheet->setCellValue('W1', 'Luchaje');
    $sheet->setCellValue('X1', 'Tra');
    $sheet->setCellValue('Y1', 'Codigo Color Trama');
    $sheet->setCellValue('Z1', 'Nombre Color Trama');
    $sheet->setCellValue('AA1', 'OBS.');
    $sheet->setCellValue('AB1', 'Tipo plano');
    $sheet->setCellValue('AC1', 'Med plano');
    $sheet->setCellValue('AD1', 'TIPO DE RIZO');
    $sheet->setCellValue('AE1', 'ALTURA DE RIZO');
    $sheet->setCellValue('AF1', 'Veloc.    Mínima');
    $sheet->setCellValue('AG1', 'Rizo');
    $sheet->setCellValue('AH1', 'Pie');
    $sheet->setCellValue('AI1', 'TIRAS');
    $sheet->setCellValue('AJ1', 'Repeticiones p/corte');
    $sheet->setCellValue('AK1', 'No. De Marbetes');
    $sheet->setCellValue('AL1', 'Cambio de repaso');
    $sheet->setCellValue('AM1', 'Vendedor');
    $sheet->setCellValue('AN1', 'No. Orden');
    $sheet->setCellValue('AO1', 'Observaciones');
    $sheet->setCellValue('AP1', 'TRAMA (Ancho Peine)');
    $sheet->setCellValue('AQ1', 'LOG. DE LUCHA TOTAL');
    $sheet->setCellValue('AR1', 'C1   trama de Fondo');
    $sheet->setCellValue('AS1', 'PASADAS');
    $sheet->setCellValue('AT1', 'C1|Cod Color');
    $sheet->setCellValue('AU1', 'C1|Nombre Color');
    $sheet->setCellValue('AV1', 'C1|PASADAS');
    $sheet->setCellValue('AW1', 'C2|Cod Color');
    $sheet->setCellValue('AX1', 'C2|Nombre Color');
    $sheet->setCellValue('AY1', 'C2|PASADAS');
    $sheet->setCellValue('AZ1', 'C3|Cod Color');
    $sheet->setCellValue('BA1', 'C3|Nombre Color');
    $sheet->setCellValue('BB1', 'C3|PASADAS');
    $sheet->setCellValue('BC1', 'C4|Cod Color');
    $sheet->setCellValue('BD1', 'C4|Nombre Color');
    $sheet->setCellValue('BE1', 'C4|PASADAS');
    $sheet->setCellValue('BF1', 'C5|Cod Color');
    $sheet->setCellValue('BG1', 'C5|Nombre Color');
    $sheet->setCellValue('BH1', 'C5|PASADAS');
    $sheet->setCellValue('BI1', 'Pasadas TOTAL');
    $sheet->setCellValue('BJ1', 'PASADAS DIBUJO');
    $sheet->setCellValue('BK1', 'Contraccion');
    $sheet->setCellValue('BL1', 'Tramas cm/Tejido');
    $sheet->setCellValue('BM1', 'Contrac Rizo');
    $sheet->setCellValue('BN1', 'Clasificación(KG)');
    $sheet->setCellValue('BO1', 'KG/Día');
    $sheet->setCellValue('BP1', 'Densidad');
    $sheet->setCellValue('BQ1', 'Pzas/Día/ pasadas');
    $sheet->setCellValue('BR1', 'Pzas/Día/ formula');
    $sheet->setCellValue('BS1', 'DIF');
    $sheet->setCellValue('BT1', 'EFIC.');
    $sheet->setCellValue('BU1', 'Rev');
    $sheet->setCellValue('BV1', 'TIRAS');
    $sheet->setCellValue('BW1', 'PASADAS');
    $sheet->setCellValue('BX1', 'ColumCT');
    $sheet->setCellValue('BY1', 'ColumCU');
    $sheet->setCellValue('BZ1', 'ColumCV');
    $sheet->setCellValue('CA1', 'COMPROBAR modelos duplicados');

    // Fila 2 (subencabezados)
    $sheet->setCellValue('A2', 'Clave mod.|');
    $sheet->setCellValue('B2', 'NoProduccion|Orden');
    $sheet->setCellValue('C2', 'Fecha  Orden|Fecha  Orden');
    $sheet->setCellValue('D2', 'Fecha   Cumplimiento');
    $sheet->setCellValue('E2', 'Departamento');
    $sheet->setCellValue('F2', 'Telar|Actual');
    $sheet->setCellValue('G2', 'Prioridad');
    $sheet->setCellValue('H2', 'Modelo');
    $sheet->setCellValue('I2', 'CLAVE MODELO');
    $sheet->setCellValue('J2', 'CLAVE AX');
    $sheet->setCellValue('K2', 'Tamaño');
    $sheet->setCellValue('L2', 'TOLERANCIA');
    $sheet->setCellValue('M2', 'CODIGO|DE DIBUJO');
    $sheet->setCellValue('N2', 'Fecha|Compromiso');
    $sheet->setCellValue('O2', 'Id Flog|');
    $sheet->setCellValue('P2', 'Nombre|de Formato Logístico');
    $sheet->setCellValue('Q2', 'Clave');
    $sheet->setCellValue('R2', 'Cantidad|a Producir');
    $sheet->setCellValue('S2', 'Peine');
    $sheet->setCellValue('T2', 'Ancho');
    $sheet->setCellValue('U2', 'Largo');
    $sheet->setCellValue('V2', 'P crudo');
    $sheet->setCellValue('W2', 'Luchaje');
    $sheet->setCellValue('X2', 'Tra');
    $sheet->setCellValue('Y2', 'Codigo|Color Trama');
    $sheet->setCellValue('Z2', 'Nombre|Color Trama');
    $sheet->setCellValue('AA2', 'OBS');
    $sheet->setCellValue('AB2', 'Tipo|plano');
    $sheet->setCellValue('AC2', 'Med|plano');
    $sheet->setCellValue('AD2', 'TIPO|DE RIZO');
    $sheet->setCellValue('AE2', 'ALTURA|DE RIZO');
    $sheet->setCellValue('AF2', 'Veloc.|Mínima');
    $sheet->setCellValue('AG2', 'Rizo');
    $sheet->setCellValue('AH2', 'Pie');
    $sheet->setCellValue('AI2', 'TIRAS');
    $sheet->setCellValue('AJ2', 'Repeticiones|p/corte');
    $sheet->setCellValue('AK2', 'No.|De Marbetes');
    $sheet->setCellValue('AL2', 'Cambio|de repaso');
    $sheet->setCellValue('AM2', 'Vendedor');
    $sheet->setCellValue('AN2', 'No.|Orden');
    $sheet->setCellValue('AO2', 'Observaciones');
    $sheet->setCellValue('AP2', 'TRAMA|(Ancho Peine)');
    $sheet->setCellValue('AQ2', 'LOG.|DE LUCHA TOTAL');
    $sheet->setCellValue('AR2', 'C1|trama de Fondo');
    $sheet->setCellValue('AS2', 'PASADAS');
    $sheet->setCellValue('AT2', 'C1|Cod Color');
    $sheet->setCellValue('AU2', 'C1|Nombre Color');
    $sheet->setCellValue('AV2', 'C1|PASADAS');
    $sheet->setCellValue('AW2', 'C2|Cod Color');
    $sheet->setCellValue('AX2', 'C2|Nombre Color');
    $sheet->setCellValue('AY2', 'C2|PASADAS');
    $sheet->setCellValue('AZ2', 'C3|Cod Color');
    $sheet->setCellValue('BA2', 'C3|Nombre Color');
    $sheet->setCellValue('BB2', 'C3|PASADAS');
    $sheet->setCellValue('BC2', 'C4|Cod Color');
    $sheet->setCellValue('BD2', 'C4|Nombre Color');
    $sheet->setCellValue('BE2', 'C4|PASADAS');
    $sheet->setCellValue('BF2', 'C5|Cod Color');
    $sheet->setCellValue('BG2', 'C5|Nombre Color');
    $sheet->setCellValue('BH2', 'C5|PASADAS');
    $sheet->setCellValue('BI2', 'Pasadas|TOTAL');
    $sheet->setCellValue('BJ2', 'PasadasDibujo');
    $sheet->setCellValue('BK2', 'Contraccion');
    $sheet->setCellValue('BL2', 'Tramas|cm/Tejido');
    $sheet->setCellValue('BM2', 'Contrac|Rizo');
    $sheet->setCellValue('BN2', 'Clasificacion|KG');
    $sheet->setCellValue('BO2', 'KG/Día');
    $sheet->setCellValue('BP2', 'Densidad');
    $sheet->setCellValue('BQ2', 'Pzas/Día|pasadas');
    $sheet->setCellValue('BR2', 'Pzas/Día|formula');
    $sheet->setCellValue('BS2', 'DIF');
    $sheet->setCellValue('BT2', 'EFIC');
    $sheet->setCellValue('BU2', 'Rev');
    $sheet->setCellValue('BV2', 'TIRAS');
    $sheet->setCellValue('BW2', 'PASADAS');
    $sheet->setCellValue('BX2', 'ColumCT');
    $sheet->setCellValue('BY2', 'ColumCU');
    $sheet->setCellValue('BZ2', 'ColumCV');
    $sheet->setCellValue('CA2', 'COMPROBAR|modelos duplicados');

    // Datos de prueba con valores problemáticos
    $sheet->setCellValue('A3', 'TEST001');
    $sheet->setCellValue('B3', 'ORD-TEST-001');
    $sheet->setCellValue('C3', '2024-01-15');
    $sheet->setCellValue('D3', '2024-02-15');
    $sheet->setCellValue('E3', 'SALON A');
    $sheet->setCellValue('F3', 'TELAR-01');
    $sheet->setCellValue('G3', 'ALTA');
    $sheet->setCellValue('H3', 'MODELO TEST');
    $sheet->setCellValue('I3', 'TE001');
    $sheet->setCellValue('J3', 'AX001');
    $sheet->setCellValue('K3', 'M');
    $sheet->setCellValue('L3', '5%');
    $sheet->setCellValue('M3', 'DIB001');
    $sheet->setCellValue('N3', '2024-02-10');
    $sheet->setCellValue('O3', 'FL001');
    $sheet->setCellValue('P3', 'FORMATO LOG 1');
    $sheet->setCellValue('Q3', 'A');
    $sheet->setCellValue('R3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('S3', '120');
    $sheet->setCellValue('T3', '50');
    $sheet->setCellValue('U3', '100');
    $sheet->setCellValue('V3', '250');
    $sheet->setCellValue('W3', '2.5');
    $sheet->setCellValue('X3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('Y3', 'CT001');
    $sheet->setCellValue('Z3', 'AZUL MARINO');
    $sheet->setCellValue('AA3', 'FIBRA ALGODON');
    $sheet->setCellValue('AB3', 'PLANO A');
    $sheet->setCellValue('AC3', '5');
    $sheet->setCellValue('AD3', 'RIZO ALTO');
    $sheet->setCellValue('AE3', '3.5');
    $sheet->setCellValue('AF3', '120');
    $sheet->setCellValue('AG3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('AH3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('AI3', '4');
    $sheet->setCellValue('AJ3', '2');
    $sheet->setCellValue('AK3', '50');
    $sheet->setCellValue('AL3', 'NO');
    $sheet->setCellValue('AM3', 'JUAN PEREZ');
    $sheet->setCellValue('AN3', 'ORD001');
    $sheet->setCellValue('AO3', 'SIN OBSERVACIONES');
    $sheet->setCellValue('AP3', '45');
    $sheet->setCellValue('AQ3', '150');
    $sheet->setCellValue('AR3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('AS3', '4');
    $sheet->setCellValue('AT3', 'C1-001');
    $sheet->setCellValue('AU3', 'ROJO');
    $sheet->setCellValue('AV3', '2');
    $sheet->setCellValue('AW3', 'C2-001');
    $sheet->setCellValue('AX3', 'VERDE');
    $sheet->setCellValue('AY3', '2');
    $sheet->setCellValue('AZ3', '');
    $sheet->setCellValue('BA3', '');
    $sheet->setCellValue('BB3', '');
    $sheet->setCellValue('BC3', '');
    $sheet->setCellValue('BD3', '');
    $sheet->setCellValue('BE3', '');
    $sheet->setCellValue('BF3', '');
    $sheet->setCellValue('BG3', '');
    $sheet->setCellValue('BH3', '');
    $sheet->setCellValue('BI3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BJ3', '4');
    $sheet->setCellValue('BK3', '5%');
    $sheet->setCellValue('BL3', '25');
    $sheet->setCellValue('BM3', '3%');
    $sheet->setCellValue('BN3', 'A');
    $sheet->setCellValue('BO3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BP3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BQ3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BR3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BS3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BT3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BU3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BV3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BW3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BX3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BY3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('BZ3', 'ABIERTO'); // VALOR PROBLEMÁTICO
    $sheet->setCellValue('CA3', 'NO');

    // Guardar archivo de prueba
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filename = 'test_correcciones.xlsx';
    $writer->save($filename);

    echo "Archivo de prueba creado: $filename\n";
    echo "Contiene valores problemáticos como 'ABIERTO' en campos numéricos\n\n";

    // Probar importación
    echo "Iniciando importación de prueba...\n";
    $import = new ReqModelosCodificadosImport();
    $resultado = Excel::import($import, $filename);

    echo "=== RESULTADOS DE LA PRUEBA ===\n";
    echo "Filas procesadas: " . $import->getRowCount() . "\n";
    echo "Registros creados: " . $import->getCreatedCount() . "\n";
    echo "Registros actualizados: " . $import->getUpdatedCount() . "\n";

    $errores = $import->getErrors();
    echo "Total de errores: " . $errores['total_errores'] . "\n";

    if ($errores['total_errores'] > 0) {
        echo "\n=== ERRORES ENCONTRADOS ===\n";
        foreach ($errores['primeros'] as $error) {
            echo "Fila {$error['fila']}: {$error['error']}\n";
        }
    } else {
        echo "\n✅ ¡PRUEBA EXITOSA! Los valores 'ABIERTO' se manejaron correctamente\n";
    }

    // Verificar datos guardados
    echo "\n=== VERIFICANDO DATOS GUARDADOS ===\n";
    $registro = \App\Models\Planeacion\ReqModelosCodificados::where('TamanoClave', 'TEST001')->first();
    if ($registro) {
        echo "Registro encontrado:\n";
        echo "- Clave: {$registro->TamanoClave}\n";
        echo "- Orden: {$registro->OrdenTejido}\n";
        echo "- Pedido: {$registro->Pedido}\n";
        echo "- CalibreTrama: {$registro->CalibreTrama}\n";
        echo "- CalibreRizo: {$registro->CalibreRizo}\n";
        echo "- CalibrePie: {$registro->CalibrePie}\n";
        echo "- Total: {$registro->Total}\n";
        echo "- KGDia: {$registro->KGDia}\n";
        echo "- Densidad: {$registro->Densidad}\n";
        echo "- DIF: {$registro->DIF}\n";
        echo "- EFIC: {$registro->EFIC}\n";
    } else {
        echo "❌ No se encontró el registro de prueba\n";
    }

    // Limpiar archivo de prueba
    unlink($filename);
    echo "\nArchivo de prueba eliminado.\n";

} catch (\Exception $e) {
    echo "ERROR durante la prueba: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== PRUEBA DE CORRECCIONES COMPLETADA ===\n";
