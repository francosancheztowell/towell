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

// Datos de ejemplo para probar
$datosEjemplo = [
    [
        'Clave mod.' => 'MOD001',
        'Orden' => 'ORD-2024-001',
        'Fecha  Orden' => '2024-01-15',
        'Fecha   Cumplimiento' => '2024-02-15',
        'Departamento' => 'SALON A',
        'Telar Actual' => 'TELAR-01',
        'Prioridad' => 'ALTA',
        'Modelo' => 'TOALLA EJEMPLO 1',
        'CLAVE MODELO' => 'TE001',
        'CLAVE  AX' => 'AX001',
        'Tamaño' => 'M',
        'TOLERANCIA' => '5%',
        'CODIGO DE DIBUJO' => 'DIB001',
        'Fecha Compromiso' => '2024-02-10',
        'Id Flog' => 'FL001',
        'Nombre de Formato Logístico' => 'FORMATO LOG 1',
        'Clave' => 'A',
        'Cantidad a Producir' => '1000',
        'Peine' => '120',
        'Ancho' => '50',
        'Largo' => '100',
        'P_crudo' => '250',
        'Luchaje' => '2.5',
        'Tra' => '10.5',
        'Codigo Color Trama' => 'CT001',
        'Nombre Color Trama' => 'AZUL MARINO',
        'OBS.' => 'FIBRA ALGODON',
        'Tipo plano' => 'PLANO A',
        'Med plano' => '5',
        'TIPO DE RIZO' => 'RIZO ALTO',
        'ALTURA DE RIZO' => '3.5',
        'Veloc.    Mínima' => '120',
        'Rizo' => '15.2',
        'Pie' => '12.8',
        'TIRAS' => '4',
        'Repeticiones p/corte' => '2',
        'No. De Marbetes' => '50',
        'Cambio de repaso' => 'NO',
        'Vendedor' => 'JUAN PEREZ',
        'No. Orden' => 'ORD001',
        'Observaciones' => 'SIN OBSERVACIONES',
        'TRAMA (Ancho Peine)' => '45',
        'LOG. DE LUCHA TOTAL' => '150',
        'C1   trama de Fondo' => '8.5',
        'PASADAS' => '4',
        'C1|Cod Color' => 'C1-001',
        'C1|Nombre Color' => 'ROJO',
        'C1|PASADAS' => '2',
        'C2|Cod Color' => 'C2-001',
        'C2|Nombre Color' => 'VERDE',
        'C2|PASADAS' => '2',
        'C3|Cod Color' => '',
        'C3|Nombre Color' => '',
        'C3|PASADAS' => '',
        'C4|Cod Color' => '',
        'C4|Nombre Color' => '',
        'C4|PASADAS' => '',
        'C5|Cod Color' => '',
        'C5|Nombre Color' => '',
        'C5|PASADAS' => '',
        'Pasadas TOTAL' => '8',
        'PASADAS DIBUJO' => '4',
        'Contraccion' => '5%',
        'Tramas cm/Tejido' => '25',
        'Contrac Rizo' => '3%',
        'Clasificación(KG)' => 'A',
        'KG/Día' => '50.5',
        'Densidad' => '2.8',
        'Pzas/Día/ pasadas' => '25.5',
        'Pzas/Día/ formula' => '24.8',
        'DIF' => '0.7',
        'EFIC.' => '98.5',
        'Rev' => '1.2',
        'TIRAS' => '4.0',
        'PASADAS' => '8.0',
        'ColumCT' => '12.5',
        'ColumCU' => '15.2',
        'ColumCV' => '18.8',
        'COMPROBAR modelos duplicados' => 'NO'
    ],
    [
        'Clave mod.' => 'MOD002',
        'Orden' => 'ORD-2024-002',
        'Fecha  Orden' => '2024-01-16',
        'Fecha   Cumplimiento' => '2024-02-16',
        'Departamento' => 'SALON B',
        'Telar Actual' => 'TELAR-02',
        'Prioridad' => 'MEDIA',
        'Modelo' => 'TOALLA EJEMPLO 2',
        'CLAVE MODELO' => 'TE002',
        'CLAVE  AX' => 'AX002',
        'Tamaño' => 'L',
        'TOLERANCIA' => '3%',
        'CODIGO DE DIBUJO' => 'DIB002',
        'Fecha Compromiso' => '2024-02-11',
        'Id Flog' => 'FL002',
        'Nombre de Formato Logístico' => 'FORMATO LOG 2',
        'Clave' => 'B',
        'Cantidad a Producir' => 'ABIERTO',
        'Peine' => '140',
        'Ancho' => '60',
        'Largo' => '120',
        'P_crudo' => '300',
        'Luchaje' => '3.0',
        'Tra' => '12.0',
        'Codigo Color Trama' => 'CT002',
        'Nombre Color Trama' => 'VERDE OLIVA',
        'OBS.' => 'FIBRA POLIESTER',
        'Tipo plano' => 'PLANO B',
        'Med plano' => '6',
        'TIPO DE RIZO' => 'RIZO MEDIO',
        'ALTURA DE RIZO' => '2.8',
        'Veloc.    Mínima' => '100',
        'Rizo' => '18.5',
        'Pie' => '15.2',
        'TIRAS' => '6',
        'Repeticiones p/corte' => '3',
        'No. De Marbetes' => '75',
        'Cambio de repaso' => 'SI',
        'Vendedor' => 'MARIA GARCIA',
        'No. Orden' => 'ORD002',
        'Observaciones' => 'REVISAR CALIDAD',
        'TRAMA (Ancho Peine)' => '55',
        'LOG. DE LUCHA TOTAL' => '180',
        'C1   trama de Fondo' => '9.2',
        'PASADAS' => '6',
        'C1|Cod Color' => 'C1-002',
        'C1|Nombre Color' => 'AZUL',
        'C1|PASADAS' => '3',
        'C2|Cod Color' => 'C2-002',
        'C2|Nombre Color' => 'AMARILLO',
        'C2|PASADAS' => '3',
        'C3|Cod Color' => '',
        'C3|Nombre Color' => '',
        'C3|PASADAS' => '',
        'C4|Cod Color' => '',
        'C4|Nombre Color' => '',
        'C4|PASADAS' => '',
        'C5|Cod Color' => '',
        'C5|Nombre Color' => '',
        'C5|PASADAS' => '',
        'Pasadas TOTAL' => '12',
        'PASADAS DIBUJO' => '6',
        'Contraccion' => '4%',
        'Tramas cm/Tejido' => '30',
        'Contrac Rizo' => '2%',
        'Clasificación(KG)' => 'B',
        'KG/Día' => '45.2',
        'Densidad' => '3.2',
        'Pzas/Día/ pasadas' => '22.5',
        'Pzas/Día/ formula' => '21.8',
        'DIF' => '0.7',
        'EFIC.' => '96.8',
        'Rev' => '1.5',
        'TIRAS' => '6.0',
        'PASADAS' => '12.0',
        'ColumCT' => '15.8',
        'ColumCU' => '18.5',
        'ColumCV' => '22.2',
        'COMPROBAR modelos duplicados' => 'SI'
    ]
];

// Configurar encabezados (fila 1 y 2)
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

// Aplicar datos de ejemplo
$fila = 3;
foreach ($datosEjemplo as $datos) {
    $columna = 'A';
    foreach ($datos as $valor) {
        $sheet->setCellValue($columna . $fila, $valor);
        $columna++;
    }
    $fila++;
}

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

// Ajustar ancho de columnas
foreach (range('A', 'CA') as $col) {
    $sheet->getColumnDimension($col)->setWidth(15);
}

// Guardar archivo
$writer = new Xlsx($spreadsheet);
$filename = 'plantilla_codificacion_ejemplo.xlsx';
$writer->save($filename);

echo "Plantilla creada exitosamente: $filename\n";
echo "Archivo guardado en: " . realpath($filename) . "\n";
