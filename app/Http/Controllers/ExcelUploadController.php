<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelUploadController extends Controller
{
    public function uploadExcel(Request $request)
    {
        try {
            // Validar el archivo
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB máximo
            ]);

            $file = $request->file('excel_file');

            // Leer el archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Obtener las columnas esperadas
            $expectedColumns = [
                'Cuenta', 'Calibre Rizo', 'Salon', 'Telar', 'Ultimo', 'Cambios Hilo', 'Maq', 'Ancho', 'Ef Std', 'Vel',
                'Hilo', 'Calibre Pie', 'Jornada', 'Clave mod.', 'Usar cuando no existe en base', 'Clave AX', 'Tamaño AX',
                'Rasurado', 'Producto', 'Pedido', 'Saldos', 'Saldo Marbetes', 'Day Sheduling', 'Orden Prod.', 'INN',
                'Id Flog', 'Descrip.', 'Aplic.', 'Obs', 'Tipo Ped', 'Tiras', 'Pei.', 'Lcr', 'Pcr', 'Luc',
                'CALIBRE TRA', 'Fibra Trama', 'Dob', 'PASADAS TRA', 'PASADAS C1', 'PASADAS C2', 'PASADAS C3', 'PASADAS C4',
                'PASADAS C5', 'ancho por toalla', 'CODIGO COLOR', 'COLOR TRA', 'CALIBRE C1', 'FIBRA C1', 'CODIGO COLOR',
                'COLOR C1', 'CALIBRE C2', 'FIBRA C2', 'CODIGO COLOR', 'COLOR C2', 'CALIBRE C3', 'FIBRA C3', 'CODIGO COLOR',
                'COLOR C3', 'CALIBRE C4', 'FIBRA C4', 'CODIGO COLOR', 'COLOR C4', 'CALIBRE C5', 'FIBRA C5', 'CODIGO COLOR',
                'COLOR C5', 'Plano', 'Cuenta Pie', 'CODIGO COLOR', 'Color Pie', 'Peso (gr / m²)', 'Dias Ef.',
                'Prod (Kg)/Día', 'Std/Dia', 'Prod (Kg)/Día', 'Std (Toa/Hr) 100%', 'Dias jornada completa', 'Horas',
                'Std/Hr.efectivo', 'Inicio', 'Calc4', 'Calc5', 'Calc6', 'Fin', 'Fecha Compromiso', 'Fecha Compromiso',
                'Entrega', 'Dif vs Compromiso'
            ];

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo Excel está vacío'
                ], 400);
            }

            // Obtener la primera fila (encabezados)
            $headers = array_shift($data);

            // Verificar que las columnas coincidan
            $missingColumns = array_diff($expectedColumns, $headers);
            if (!empty($missingColumns)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan las siguientes columnas: ' . implode(', ', $missingColumns)
                ], 400);
            }

            // Procesar los datos
            $processedData = [];
            foreach ($data as $rowIndex => $row) {
                // Saltar filas vacías
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowData = [];
                foreach ($expectedColumns as $column) {
                    $columnIndex = array_search($column, $headers);
                    $rowData[$column] = isset($row[$columnIndex]) ? $row[$columnIndex] : null;
                }

                $processedData[] = $rowData;
            }

            // Aquí puedes guardar los datos en la base de datos o hacer lo que necesites
            // Por ahora solo retornamos los datos procesados para mostrar en el frontend

            return response()->json([
                'success' => true,
                'message' => 'Archivo Excel procesado correctamente',
                'data' => $processedData,
                'total_rows' => count($processedData),
                'columns' => $expectedColumns
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadExcelTelar(Request $request)
    {
        // Método específico para telares
        return $this->uploadExcel($request);
    }

    public function uploadExcelEficiencia(Request $request)
    {
        // Método específico para eficiencia
        return $this->uploadExcel($request);
    }

    public function uploadExcelVelocidad(Request $request)
    {
        // Método específico para velocidad
        return $this->uploadExcel($request);
    }

    public function uploadExcelCalendario(Request $request)
    {
        // Método específico para calendarios
        return $this->uploadExcel($request);
    }

    public function uploadExcelAplicacion(Request $request)
    {
        // Método específico para aplicaciones
        return $this->uploadExcel($request);
    }
}
