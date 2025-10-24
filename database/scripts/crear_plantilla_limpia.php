<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Crear nuevo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar encabezados (fila 1 y 2) - PLANTILLA LIMPIA
$encabezados1 = [
    'A1' => 'Clave mod.',
    'B1' => 'Orden',
    'C1' => 'Fecha  Orden',
    'D1' => 'Fecha   Cumplimiento',
    'E1' => 'Departamento',
    'F1' => 'Telar Actual',
    'G1' => 'Prioridad',
    'H1' => 'Modelo',
    'I1' => 'CLAVE MODELO',
    'J1' => 'CLAVE  AX',
    'K1' => 'Tamaño',
    'L1' => 'TOLERANCIA',
    'M1' => 'CODIGO DE DIBUJO',
    'N1' => 'Fecha Compromiso',
    'O1' => 'Id Flog',
    'P1' => 'Nombre de Formato Logístico',
    'Q1' => 'Clave',
    'R1' => 'Cantidad a Producir',
    'S1' => 'Peine',
    'T1' => 'Ancho',
    'U1' => 'Largo',
    'V1' => 'P_crudo',
    'W1' => 'Luchaje',
    'X1' => 'Tra',
    'Y1' => 'Codigo Color Trama',
    'Z1' => 'Nombre Color Trama',
    'AA1' => 'OBS.',
    'AB1' => 'Tipo plano',
    'AC1' => 'Med plano',
    'AD1' => 'TIPO DE RIZO',
    'AE1' => 'ALTURA DE RIZO',
    'AF1' => 'Veloc.    Mínima',
    'AG1' => 'Rizo',
    'AH1' => 'Pie',
    'AI1' => 'TIRAS',
    'AJ1' => 'Repeticiones p/corte',
    'AK1' => 'No. De Marbetes',
    'AL1' => 'Cambio de repaso',
    'AM1' => 'Vendedor',
    'AN1' => 'No. Orden',
    'AO1' => 'Observaciones',
    'AP1' => 'TRAMA (Ancho Peine)',
    'AQ1' => 'LOG. DE LUCHA TOTAL',
    'AR1' => 'C1   trama de Fondo',
    'AS1' => 'PASADAS',
    'AT1' => 'C1|Cod Color',
    'AU1' => 'C1|Nombre Color',
    'AV1' => 'C1|PASADAS',
    'AW1' => 'C2|Cod Color',
    'AX1' => 'C2|Nombre Color',
    'AY1' => 'C2|PASADAS',
    'AZ1' => 'C3|Cod Color',
    'BA1' => 'C3|Nombre Color',
    'BB1' => 'C3|PASADAS',
    'BC1' => 'C4|Cod Color',
    'BD1' => 'C4|Nombre Color',
    'BE1' => 'C4|PASADAS',
    'BF1' => 'C5|Cod Color',
    'BG1' => 'C5|Nombre Color',
    'BH1' => 'C5|PASADAS',
    'BI1' => 'Pasadas TOTAL',
    'BJ1' => 'PASADAS DIBUJO',
    'BK1' => 'Contraccion',
    'BL1' => 'Tramas cm/Tejido',
    'BM1' => 'Contrac Rizo',
    'BN1' => 'Clasificación(KG)',
    'BO1' => 'KG/Día',
    'BP1' => 'Densidad',
    'BQ1' => 'Pzas/Día/ pasadas',
    'BR1' => 'Pzas/Día/ formula',
    'BS1' => 'DIF',
    'BT1' => 'EFIC.',
    'BU1' => 'Rev',
    'BV1' => 'TIRAS',
    'BW1' => 'PASADAS',
    'BX1' => 'ColumCT',
    'BY1' => 'ColumCU',
    'BZ1' => 'ColumCV',
    'CA1' => 'COMPROBAR modelos duplicados'
];

// Encabezados fila 2 (subencabezados)
$encabezados2 = [
    'A2' => 'Clave mod.|',
    'B2' => 'NoProduccion|Orden',
    'C2' => 'Fecha  Orden|Fecha  Orden',
    'D2' => 'Fecha   Cumplimiento',
    'E2' => 'Departamento',
    'F2' => 'Telar|Actual',
    'G2' => 'Prioridad',
    'H2' => 'Modelo',
    'I2' => 'CLAVE MODELO',
    'J2' => 'CLAVE AX',
    'K2' => 'Tamaño',
    'L2' => 'TOLERANCIA',
    'M2' => 'CODIGO|DE DIBUJO',
    'N2' => 'Fecha|Compromiso',
    'O2' => 'Id Flog|',
    'P2' => 'Nombre|de Formato Logístico',
    'Q2' => 'Clave',
    'R2' => 'Cantidad|a Producir',
    'S2' => 'Peine',
    'T2' => 'Ancho',
    'U2' => 'Largo',
    'V2' => 'P crudo',
    'W2' => 'Luchaje',
    'X2' => 'Tra',
    'Y2' => 'Codigo|Color Trama',
    'Z2' => 'Nombre|Color Trama',
    'AA2' => 'OBS',
    'AB2' => 'Tipo|plano',
    'AC2' => 'Med|plano',
    'AD2' => 'TIPO|DE RIZO',
    'AE2' => 'ALTURA|DE RIZO',
    'AF2' => 'Veloc.|Mínima',
    'AG2' => 'Rizo',
    'AH2' => 'Pie',
    'AI2' => 'TIRAS',
    'AJ2' => 'Repeticiones|p/corte',
    'AK2' => 'No.|De Marbetes',
    'AL2' => 'Cambio|de repaso',
    'AM2' => 'Vendedor',
    'AN2' => 'No.|Orden',
    'AO2' => 'Observaciones',
    'AP2' => 'TRAMA|(Ancho Peine)',
    'AQ2' => 'LOG.|DE LUCHA TOTAL',
    'AR2' => 'C1|trama de Fondo',
    'AS2' => 'PASADAS',
    'AT2' => 'C1|Cod Color',
    'AU2' => 'C1|Nombre Color',
    'AV2' => 'C1|PASADAS',
    'AW2' => 'C2|Cod Color',
    'AX2' => 'C2|Nombre Color',
    'AY2' => 'C2|PASADAS',
    'AZ2' => 'C3|Cod Color',
    'BA2' => 'C3|Nombre Color',
    'BB2' => 'C3|PASADAS',
    'BC2' => 'C4|Cod Color',
    'BD2' => 'C4|Nombre Color',
    'BE2' => 'C4|PASADAS',
    'BF2' => 'C5|Cod Color',
    'BG2' => 'C5|Nombre Color',
    'BH2' => 'C5|PASADAS',
    'BI2' => 'Pasadas|TOTAL',
    'BJ2' => 'PasadasDibujo',
    'BK2' => 'Contraccion',
    'BL2' => 'Tramas|cm/Tejido',
    'BM2' => 'Contrac|Rizo',
    'BN2' => 'Clasificacion|KG',
    'BO2' => 'KG/Día',
    'BP2' => 'Densidad',
    'BQ2' => 'Pzas/Día|pasadas',
    'BR2' => 'Pzas/Día|formula',
    'BS2' => 'DIF',
    'BT2' => 'EFIC',
    'BU2' => 'Rev',
    'BV2' => 'TIRAS',
    'BW2' => 'PASADAS',
    'BX2' => 'ColumCT',
    'BY2' => 'ColumCU',
    'BZ2' => 'ColumCV',
    'CA2' => 'COMPROBAR|modelos duplicados'
];

// Aplicar encabezados
foreach ($encabezados1 as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

foreach ($encabezados2 as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Agregar fila de ejemplo vacía para mostrar formato
$sheet->setCellValue('A3', 'EJEMPLO_MOD001');
$sheet->setCellValue('B3', 'ORD-2024-001');
$sheet->setCellValue('C3', '2024-01-15');
$sheet->setCellValue('D3', '2024-02-15');
$sheet->setCellValue('E3', 'SALON A');
$sheet->setCellValue('F3', 'TELAR-01');
$sheet->setCellValue('G3', 'ALTA');
$sheet->setCellValue('H3', 'MODELO EJEMPLO');
$sheet->setCellValue('I3', 'TE001');
$sheet->setCellValue('J3', 'AX001');
$sheet->setCellValue('K3', 'M');
$sheet->setCellValue('L3', '5%');
$sheet->setCellValue('M3', 'DIB001');
$sheet->setCellValue('N3', '2024-02-10');
$sheet->setCellValue('O3', 'FL001');
$sheet->setCellValue('P3', 'FORMATO LOG 1');
$sheet->setCellValue('Q3', 'A');
$sheet->setCellValue('R3', '1000');
$sheet->setCellValue('S3', '120');
$sheet->setCellValue('T3', '50');
$sheet->setCellValue('U3', '100');
$sheet->setCellValue('V3', '250');
$sheet->setCellValue('W3', '2.5');
$sheet->setCellValue('X3', '10.5');
$sheet->setCellValue('Y3', 'CT001');
$sheet->setCellValue('Z3', 'AZUL MARINO');
$sheet->setCellValue('AA3', 'FIBRA ALGODON');
$sheet->setCellValue('AB3', 'PLANO A');
$sheet->setCellValue('AC3', '5');
$sheet->setCellValue('AD3', 'RIZO ALTO');
$sheet->setCellValue('AE3', '3.5');
$sheet->setCellValue('AF3', '120');
$sheet->setCellValue('AG3', '15.2');
$sheet->setCellValue('AH3', '12.8');
$sheet->setCellValue('AI3', '4');
$sheet->setCellValue('AJ3', '2');
$sheet->setCellValue('AK3', '50');
$sheet->setCellValue('AL3', 'NO');
$sheet->setCellValue('AM3', 'JUAN PEREZ');
$sheet->setCellValue('AN3', 'ORD001');
$sheet->setCellValue('AO3', 'SIN OBSERVACIONES');
$sheet->setCellValue('AP3', '45');
$sheet->setCellValue('AQ3', '150');
$sheet->setCellValue('AR3', '8.5');
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
$sheet->setCellValue('BI3', '8');
$sheet->setCellValue('BJ3', '4');
$sheet->setCellValue('BK3', '5%');
$sheet->setCellValue('BL3', '25');
$sheet->setCellValue('BM3', '3%');
$sheet->setCellValue('BN3', 'A');
$sheet->setCellValue('BO3', '50.5');
$sheet->setCellValue('BP3', '2.8');
$sheet->setCellValue('BQ3', '25.5');
$sheet->setCellValue('BR3', '24.8');
$sheet->setCellValue('BS3', '0.7');
$sheet->setCellValue('BT3', '98.5');
$sheet->setCellValue('BU3', '1.2');
$sheet->setCellValue('BV3', '4.0');
$sheet->setCellValue('BW3', '8.0');
$sheet->setCellValue('BX3', '12.5');
$sheet->setCellValue('BY3', '15.2');
$sheet->setCellValue('BZ3', '18.8');
$sheet->setCellValue('CA3', 'NO');

// Aplicar estilos a los encabezados
$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 10
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E6E6FA']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$sheet->getStyle('A1:CA2')->applyFromArray($headerStyle);

// Estilo para la fila de ejemplo
$exampleStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F8FF']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A3:CA3')->applyFromArray($exampleStyle);

// Ajustar ancho de columnas
foreach (range('A', 'CA') as $col) {
    $sheet->getColumnDimension($col)->setWidth(15);
}

// Agregar comentarios explicativos
$sheet->getComment('A3')->getText()->createTextRun('EJEMPLO: Reemplaza estos datos con tus datos reales');
$sheet->getComment('R3')->getText()->createTextRun('EJEMPLO: Puede ser número (1000) o texto (ABIERTO)');
$sheet->getComment('X3')->getText()->createTextRun('EJEMPLO: Campo Tra - puede ser texto o número');

// Guardar archivo
$writer = new Xlsx($spreadsheet);
$filename = 'PLANTILLA_CODIFICACION_LIMPIA.xlsx';
$writer->save($filename);

echo "Plantilla limpia creada exitosamente: $filename\n";
echo "Archivo guardado en: " . realpath($filename) . "\n";
echo "\n=== INSTRUCCIONES DE USO ===\n";
echo "1. Abre el archivo PLANTILLA_CODIFICACION_LIMPIA.xlsx\n";
echo "2. Reemplaza la fila 3 (ejemplo) con tus datos reales\n";
echo "3. Agrega más filas según necesites\n";
echo "4. Guarda el archivo\n";
echo "5. Sube el archivo a través de la aplicación web\n";
echo "\n=== CAMPOS IMPORTANTES ===\n";
echo "- Clave mod.: Identificador único del modelo\n";
echo "- Orden: Número de orden de producción\n";
echo "- Cantidad a Producir: Puede ser número o 'ABIERTO'\n";
echo "- Tra: Campo de calibre trama (texto o número)\n";
echo "- Todos los campos son opcionales excepto Clave mod. y Orden\n";
