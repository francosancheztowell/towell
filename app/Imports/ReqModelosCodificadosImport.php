<?php

namespace App\Imports;

use App\Models\ReqModelosCodificados;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReqModelosCodificadosImport implements
    ToCollection,
    WithStartRow,
    WithChunkReading,
    WithBatchInserts,
    SkipsEmptyRows,
    WithEvents
{
    private int $rowCount = 0;
    private int $createdCount = 0;
    private int $updatedCount = 0;
    private array $errors = [];

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 300; }
    public function batchSize(): int { return 300; }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                if (function_exists('ini_set')) {
                    @ini_set('memory_limit', '1024M');
                    @ini_set('max_execution_time', '0');
                }
            },
        ];
    }

    public function collection(Collection $rows)
    {
        if ($rows->count() < 3) {
            $this->pushError(1, 'Se requieren al menos 3 filas (2 encabezados + datos).', []);
            return;
        }

        // Encabezados (fila 1 y 2)
        $hdr1 = $this->rowToFlatArray($rows[0]);
        $hdr2 = $this->rowToFlatArray($rows[1]);
        $headers = $this->buildCompositeHeaders($hdr1, $hdr2);

        // Fallback por posición (1-based) – ajusta si cambia tu layout
        $pos = [
            'TamanoClave'       => 1,
            'OrdenTejido'       => 2,
            'FechaTejido'       => 3,
            'FechaCumplimiento' => 4,
            'SalonTejidoId'     => 5,
            'NoTelarId'         => 6,
            'Prioridad'         => 7,
            'Nombre'            => 8,
            'ClaveModelo'       => 9,
            'ItemId'            => 10,
            'InventSizeId'      => 11,
            'Tolerancia'        => 12,
            'CodigoDibujo'      => 13,
            'FechaCompromiso'   => 14,
            'FlogsId'           => 15,
            'NombreProyecto'    => 16,
            'Clave'             => 17,
            'Pedido'            => 18,
            'Peine'             => 19,
            'AnchoToalla'       => 20,
            'LargoToalla'       => 21,
            'PesoCrudo'         => 22,
            'Luchaje'           => 23,
            'CalibreTrama'      => 24,
            'CodColorTrama'     => 26,
            'ColorTrama'        => 27,
            'FibraId'           => 28,
            'DobladilloId'      => 29,
            'MedidaPlano'       => 30,
            'TipoRizo'          => 31,
            'AlturaRizo'        => 32,
            'VelocidadSTD'      => 34,
            'CalibreRizo'       => 35,
            'CalibrePie'        => 39,
            'NoTiras'           => 52,
            'Repeticiones'      => 53,
            'TotalMarbetes'     => 54,
            'CambioRepaso'      => 55,
            'Vendedor'          => 56,
            'CatCalidad'        => 57,
            'Obs5'              => 58,
            'AnchoPeineTrama'   => 59,
            'LogLuchaTotal'     => 60,
            'CalTramaFondoC1'   => 61,
            'PasadasTramaFondoC1'=> 64,
            'CodColorC1'        => 65,
            'NomColorC1'        => 66,
            'PasadasComb1'      => 67,
            'CodColorC2'        => 71,
            'NomColorC2'        => 72,
            'PasadasComb2'      => 73,
            'CodColorC3'        => 77,
            'NomColorC3'        => 78,
            'PasadasComb3'      => 79,
            'CodColorC4'        => 83,
            'NomColorC4'        => 84,
            'PasadasComb4'      => 85,
            'CodColorC5'        => 89,
            'NomColorC5'        => 90,
            'PasadasComb5'      => 91,
            'Total'             => 92,
            'PasadasDibujo'     => 93,
            'Contraccion'       => 94,
            'TramasCMTejido'    => 95,
            'ContracRizo'       => 96,
            'ClasificacionKG'   => 97,
            'KGDia'             => 98,
            'Densidad'          => 99,
            'PzasDiaPasadas'    => 100,
            'PzasDiaFormula'    => 101,
            'DIF'               => 102,
            'EFIC'              => 103,
            'Rev'               => 104,
            'TIRAS'             => 105,
            'PASADAS'           => 106,
            'ColumCT'           => 107,
            'ColumCU'           => 108,
            'ColumCV'           => 109,
            'ComprobarModDup'   => 110,
        ];

        // Datos desde la fila 3
        for ($i = 2; $i < $rows->count(); $i++) {
            $this->rowCount++;
            $excelRow = $i + 1;

            try {
                $vals  = $this->rowToFlatArray($rows[$i]);
                $assoc = $this->combineRowWithHeaders($headers, $vals);

                $data = [
                    'TamanoClave'         => $this->S($assoc, $vals, ['Clave mod.','Clave mod.|'], $pos['TamanoClave'], 120),
                    'OrdenTejido'         => $this->S($assoc, $vals, ['Orden','NoProduccion|Orden','Orden|Orden'], $pos['OrdenTejido'], 80),
                    'FechaTejido'         => $this->D($assoc, $vals, ['Fecha  Orden','Fecha  Orden|Fecha  Orden'], $pos['FechaTejido']),
                    'FechaCumplimiento'   => $this->D($assoc, $vals, ['Fecha   Cumplimiento','Fecha|Cumplimiento','Fecha   Cumplimiento|Fecha   Cumplimiento'], $pos['FechaCumplimiento']),
                    'SalonTejidoId'       => $this->S($assoc, $vals, ['Departamento'], $pos['SalonTejidoId'], 40),
                    'NoTelarId'           => $this->S($assoc, $vals, ['Telar Actual','Telar|Actual'], $pos['NoTelarId'], 40),
                    'Prioridad'           => $this->S($assoc, $vals, ['Prioridad'], $pos['Prioridad'], 1020),
                    'Nombre'              => $this->S($assoc, $vals, ['Modelo'], $pos['Nombre'], 1020),
                    'ClaveModelo'         => $this->S($assoc, $vals, ['CLAVE MODELO'], $pos['ClaveModelo'], 120),
                    'ItemId'              => $this->S($assoc, $vals, ['CLAVE  AX','CLAVE AX'], $pos['ItemId'], 80),
                    'InventSizeId'        => $this->SExact($assoc, $vals, ['Tamaño','Tamano'], $pos['InventSizeId'], 40),
                    'Tolerancia'          => $this->S($assoc, $vals, ['TOLERANCIA'], $pos['Tolerancia'], 40),
                    'CodigoDibujo'        => $this->S($assoc, $vals, ['CODIGO DE DIBUJO','CODIGO|DE DIBUJO'], $pos['CodigoDibujo'], 2000),
                    'FechaCompromiso'     => $this->D($assoc, $vals, ['Fecha Compromiso','Fecha|Compromiso'], $pos['FechaCompromiso']),

                    // FlogsId – más variantes
                    'FlogsId'             => $this->S($assoc, $vals, [
                        'Id Flog','Id Flog|','Flogs Id','Flog Id',
                        'ID FLOG','FlogsId','FLOGS','FLOG'
                    ], $pos['FlogsId'], 60),

                    'NombreProyecto'      => $this->S($assoc, $vals, ['Nombre de Formato Logístico','Nombre de Formato Log\u00edstico','Nombre|de Formato Logístico'], $pos['NombreProyecto'], 1020),

                    // Clave exacta
                    'Clave'               => $this->SExact($assoc, $vals, ['Clave'], $pos['Clave'], 20),

                    // Cantidad a Producir literal (para ADMITIR "ABIERTO")
                    'Pedido'              => $this->SExact($assoc, $vals, ['Cantidad a Producir','Cantidad|a Producir'], $pos['Pedido'], 50),

                    'Peine'               => $this->I($assoc, $vals, ['Peine'], $pos['Peine']),
                    'AnchoToalla'         => $this->I($assoc, $vals, ['Ancho'], $pos['AnchoToalla']),
                    'LargoToalla'         => $this->I($assoc, $vals, ['Largo'], $pos['LargoToalla']),
                    'PesoCrudo'           => $this->I($assoc, $vals, ['P_crudo','P crudo'], $pos['PesoCrudo']),
                    'Luchaje'             => $this->I($assoc, $vals, ['Luchaje'], $pos['Luchaje']),

                    // Tra literal (evita 10.1000 si lo quieres tal cual)
                    'CalibreTrama'        => $this->SExact($assoc, $vals, ['Tra'], $pos['CalibreTrama'], 40),

                    'CodColorTrama'       => $this->S($assoc, $vals, ['Codigo Color Trama','Codigo|Color Trama'], $pos['CodColorTrama'], 40),
                    'ColorTrama'          => $this->S($assoc, $vals, ['Nombre Color Trama','Nombre|Color Trama'], $pos['ColorTrama'], 120),

                    'FibraId'             => $this->S($assoc, $vals, ['OBSFIBRA'], $pos['FibraId'], 200),

                    // Tipo plano literal (evita que aparezca 0)
                    'DobladilloId'        => $this->SExact($assoc, $vals, ['Tipo plano','Tipo|plano'], $pos['DobladilloId'], 80),

                    'MedidaPlano'         => $this->I($assoc, $vals, ['Med plano','Med|plano'], $pos['MedidaPlano']),
                    'TipoRizo'            => $this->S($assoc, $vals, ['TIPO DE RIZO','TIPO|DE RIZO'], $pos['TipoRizo'], 256),
                    'AlturaRizo'          => $this->S($assoc, $vals, ['ALTURA DE RIZO','ALTURA|DE RIZO','OBS'], $pos['AlturaRizo'], 50),
                    'VelocidadSTD'        => $this->I($assoc, $vals, ['Veloc.    Mínima','Veloc.|Mínima','Velocidad Mínima','Velocidad Minima'], $pos['VelocidadSTD']),
                    'CalibreRizo'         => $this->S($assoc, $vals, ['Rizo','Calibre Rizo'], $pos['CalibreRizo'], 50),
                    'CalibrePie'          => $this->S($assoc, $vals, ['Pie','Calibre Pie'], $pos['CalibrePie'], 50),
                    'NoTiras'             => $this->I($assoc, $vals, ['No.TIRAS'], $pos['NoTiras']),
                    'Repeticiones'        => $this->I($assoc, $vals, ['Repeticiones p/corte','Repeticiones|p/corte','Repeticiones por corte','Repeticiones p corte'], $pos['Repeticiones']),
                    'TotalMarbetes'       => $this->I($assoc, $vals, ['No. De Marbetes','No de Marbetes'], $pos['TotalMarbetes']),
                    'CambioRepaso'        => $this->S($assoc, $vals, ['Cambio de repaso','Cambio|de repaso'], $pos['CambioRepaso'], 512),
                    'Vendedor'            => $this->S($assoc, $vals, ['Vendedor'], $pos['Vendedor'], 512),
                    'CatCalidad'          => $this->S($assoc, $vals, ['No. Orden','No.|Orden','No Orden'], $pos['CatCalidad'], 60),
                    'Obs5'                => $this->S($assoc, $vals, ['Observaciones'], $pos['Obs5'], 2000),
                    'AnchoPeineTrama'     => $this->I($assoc, $vals, ['TRAMA (Ancho Peine)','TRAMA|(Ancho Peine)','TRAMA Ancho Peine'], $pos['AnchoPeineTrama']),
                    'LogLuchaTotal'       => $this->I($assoc, $vals, ['LOG. DE LUCHA','LOG DE LUCHA ','Log de Lucha Total'], $pos['LogLuchaTotal']),

                    // Fondo C1 (tolerante a nombre "plano")
                    'CalTramaFondoC1'     => $this->S($assoc, $vals, ['C1   trama de Fondo','C1 trama de Fondo','CalTramaFondoC1'], $pos['CalTramaFondoC1'], 50),
                    'PasadasTramaFondoC1' => $this->I($assoc, $vals, ['PasadasTramaFondoC1','Pasadas trama Fondo C1','PASADASTRAMAC1'], $pos['PasadasTramaFondoC1']),

                    'CodColorC1'          => $this->S($assoc, $vals, ['C1|Cod Color','C1 Cod Color','CodColorC1'], $pos['CodColorC1'], 40),
                    'NomColorC1'          => $this->S($assoc, $vals, ['C1|Nombre Color','C1 Nombre Color','NomColorC1'], $pos['NomColorC1'], 120),
                    'PasadasComb1'        => $this->I($assoc, $vals, ['PASADASC1'], $pos['PasadasComb1']),
                    'CodColorC2'          => $this->S($assoc, $vals, ['Cod Color C2','Cod Color','CodColorC2'], $pos['CodColorC2'], 40),
                    'NomColorC2'          => $this->S($assoc, $vals, ['Nombre Color C2','Nombre Color','NomColorC2'], $pos['NomColorC2'], 120),
                    'PasadasComb2'        => $this->I($assoc, $vals, ['PASADASC2'], $pos['PasadasComb2']),
                    'CodColorC3'          => $this->S($assoc, $vals, ['Cod Color C3','Cod Color','CodColorC3'], $pos['CodColorC3'], 40),
                    'NomColorC3'          => $this->S($assoc, $vals, ['Nombre Color C3','Nombre Color','NomColorC3'], $pos['NomColorC3'], 120),
                    'PasadasComb3'        => $this->I($assoc, $vals, ['PASADASC3'], $pos['PasadasComb3']),
                    'CodColorC4'          => $this->S($assoc, $vals, ['Cod Color C4','Cod Color','CodColorC4'], $pos['CodColorC4'], 40),
                    'NomColorC4'          => $this->S($assoc, $vals, ['Nombre Color C4','Nombre Color','NomColorC4'], $pos['NomColorC4'], 120),
                    'PasadasComb4'        => $this->I($assoc, $vals, ['C4|PASADAS','C4 PASADAS','PasadasComb4'], $pos['PasadasComb4']),
                    'CodColorC5'          => $this->S($assoc, $vals, ['Cod Color C5','Cod Color','CodColorC5'], $pos['CodColorC5'], 40),
                    'NomColorC5'          => $this->S($assoc, $vals, ['Nombre Color C5','Nombre Color','NomColorC5'], $pos['NomColorC5'], 120),
                    'PasadasComb5'        => $this->I($assoc, $vals, ['C5PASADAS','C5 PASADAS','PasadasComb5'], $pos['PasadasComb5']),
                    'Total'               => $this->I($assoc, $vals, ['Pasadas TOTAL','Pasadas|TOTAL','TOTAL','Total'], $pos['Total']),
                    // “Derecha” problemáticos normalizados
                    'PasadasDibujo'       => $this->S($assoc, $vals, ['PasadasDibujo','Pasadas Dibujo'], $pos['PasadasDibujo'], 100),
                    'Contraccion'         => $this->S($assoc, $vals, ['Contraccion'], $pos['Contraccion'], 20),

                    'TramasCMTejido'      => $this->S($assoc, $vals, ['Tramas cm/Tejido','Tramas CMTejido','Tramas cm Tejido'], $pos['TramasCMTejido'], 20),
                    'ContracRizo'         => $this->S($assoc, $vals, ['Contrac Rizo','ContracRizo'], $pos['ContracRizo'], 20),
                    'ClasificacionKG'     => $this->S($assoc, $vals, ['Clasificación(KG)','Clasificacion KG','Clasificacion(KG)','ClasificacionKG'], $pos['ClasificacionKG'], 5),

                    'KGDia'               => $this->S($assoc, $vals, ['KG/Día','KGDia','KG Dia'], $pos['KGDia'], 50),
                    'Densidad'            => $this->S($assoc, $vals, ['Densidad'], $pos['Densidad'], 50),
                    'PzasDiaPasadas'      => $this->S($assoc, $vals, ['Pzas/Día/ pasadas','PzasDia pasadas','PzasDiaPasadas','Pzas Dia pasadas'], $pos['PzasDiaPasadas'], 50),
                    'PzasDiaFormula'      => $this->S($assoc, $vals, ['Pzas/Día/ formula','PzasDia formula','PzasDiaFormula','Pzas Dia formula'], $pos['PzasDiaFormula'], 50),
                    'DIF'                 => $this->S($assoc, $vals, ['DIF'], $pos['DIF'], 50),
                    'EFIC'                => $this->S($assoc, $vals, ['EFIC.','EFIC'], $pos['EFIC'], 50),
                    'Rev'                 => $this->S($assoc, $vals, ['Rev'], $pos['Rev'], 50),
                    'TIRAS'               => $this->I($assoc, $vals, ['TIRAST'], $pos['TIRAS']),
                    'PASADAS'             => $this->I($assoc, $vals, ['PASADASF'], $pos['PASADAS']),
                    'ColumCT'             => $this->S($assoc, $vals, ['ColumCT'], $pos['ColumCT'], 50),
                    'ColumCU'             => $this->S($assoc, $vals, ['ColumCU'], $pos['ColumCU'], 50),
                    'ColumCV'             => $this->S($assoc, $vals, ['ColumCV'], $pos['ColumCV'], 50),
                    'ComprobarModDup'     => $this->S($assoc, $vals, ['COMPROBAR modelos duplicados','COMPROBAR|modelos duplicados','ComprobarModDup'], $pos['ComprobarModDup'], 100),

                    // Campos adicionales que faltaban
                    'CalibreTrama2'       => $this->S($assoc, $vals, ['Tra2','Calibre Trama 2','Hilo'], $pos['CalibreTrama2'] ?? null, 50),
                    'CalibreRizo2'        => $this->S($assoc, $vals, ['Rizo2','Calibre Rizo 2'], $pos['CalibreRizo2'] ?? null, 50),
                    'CalibrePie2'         => $this->S($assoc, $vals, ['Pie2','Calibre Pie 2'], $pos['CalibrePie2'] ?? null, 50),
                    'CuentaRizo'          => $this->SExact($assoc, $vals, ['Cuenta Rizo','CUENTARIZO'], $pos['CuentaRizo'] ?? null, 50),
                    'FibraRizo'           => $this->SExact($assoc, $vals, ['Fibra Rizo','OBSRIZO'], $pos['FibraRizo'] ?? null, 50),
                    'CuentaPie'           => $this->S($assoc, $vals, ['Cuenta Pie','CUENTAPIE'], $pos['CuentaPie'] ?? null, 50),
                    'FibraPie'            => $this->SExact($assoc, $vals, ['OBSPIE','OBSPie'], $pos['FibraPie'] ?? null, 50),
                    'Comb1'               => $this->S($assoc, $vals, ['Comb1'], $pos['Comb1'] ?? null, 50),
                    'Obs1'                => $this->S($assoc, $vals, ['Obs1'], $pos['Obs1'] ?? null, 200),
                    'Comb2'               => $this->S($assoc, $vals, ['Comb2'], $pos['Comb2'] ?? null, 50),
                    'Obs2'                => $this->S($assoc, $vals, ['Obs2'], $pos['Obs2'] ?? null, 200),
                    'Comb3'               => $this->S($assoc, $vals, ['Comb3'], $pos['Comb3'] ?? null, 50),
                    'Obs3'                => $this->S($assoc, $vals, ['Obs3'], $pos['Obs3'] ?? null, 200),
                    'Comb4'               => $this->S($assoc, $vals, ['Comb4'], $pos['Comb4'] ?? null, 50),
                    'Obs4'                => $this->S($assoc, $vals, ['Obs4'], $pos['Obs4'] ?? null, 200),
                    'MedidaCenefa'        => $this->S($assoc, $vals, ['Medida Cenefa','Med. de Cenefa'], $pos['MedidaCenefa'] ?? null, 50),
                    'MedIniRizoCenefa'    => $this->S($assoc, $vals, ['Med Ini Rizo Cenefa','Med de inicio de rizo a cenefa'], $pos['MedIniRizoCenefa'] ?? null, 50),
                    'Rasurado'            => $this->S($assoc, $vals, ['Rasurado'], $pos['Rasurado'] ?? null, 50),
                    'Obs'                 => $this->S($assoc, $vals, ['Obs'], $pos['Obs'] ?? null, 200),
                    'CalTramaFondoC12'    => $this->S($assoc, $vals, ['Cal Trama Fondo C1 2','Hilo'], $pos['CalTramaFondoC12'] ?? null, 50),
                    'FibraTramaFondoC1'   => $this->S($assoc, $vals, ['Fibra Trama Fondo C1','OBSTRAMAC1'], $pos['FibraTramaFondoC1'] ?? null, 50),
                    'CalibreComb12'       => $this->S($assoc, $vals, ['Calibre Comb12','HiloC1'], $pos['CalibreComb12'] ?? null, 50),
                    'CalibreComb1'        => $this->S($assoc, $vals, ['Calibre Comb1','C1'], $pos['CalibreComb1'] ?? null, 50),
                    'FibraComb1'          => $this->S($assoc, $vals, ['Fibra Comb1','OBSC1'], $pos['FibraComb1'] ?? null, 50),
                    'CalibreComb2'        => $this->S($assoc, $vals, ['C2'], $pos['CalibreComb2'] ?? null, 50),
                    'CalibreComb22'       => $this->S($assoc, $vals, ['Calibre Comb22','HiloC2'], $pos['CalibreComb22'] ?? null, 50),
                    'FibraComb2'          => $this->S($assoc, $vals, ['OBSC2'], $pos['FibraComb2'] ?? null, 50),
                    'CalibreComb32'       => $this->S($assoc, $vals, ['Calibre Comb3 2'], $pos['CalibreComb32'] ?? null, 50),
                    'FibraComb3'          => $this->S($assoc, $vals, ['Fibra Comb3','OBSC3'], $pos['FibraComb3'] ?? null, 50),
                    'CalibreComb42'       => $this->S($assoc, $vals, ['Calibre Comb4 2','Hilo C4'], $pos['CalibreComb42'] ?? null, 50),
                    'FibraComb4'          => $this->S($assoc, $vals, ['Fibra Comb4','OBSC4'], $pos['FibraComb4'] ?? null, 50),
                    'CalibreComb52'       => $this->S($assoc, $vals, ['Calibre Comb5 2'], $pos['CalibreComb52'] ?? null, 50),
                    'FibraComb5'          => $this->S($assoc, $vals, ['Fibra Comb5'], $pos['FibraComb5'] ?? null, 50),
                ];

                // Upsert por (TamanoClave, OrdenTejido)
                $existing = null;
                if (!empty($data['TamanoClave']) && !empty($data['OrdenTejido'])) {
                    $existing = ReqModelosCodificados::where('TamanoClave', $data['TamanoClave'])
                        ->where('OrdenTejido', $data['OrdenTejido'])
                        ->first();
                }

                if ($existing) {
                    $existing->update($data);
                    $this->updatedCount++;
                } else {
                    ReqModelosCodificados::create($data);
                    $this->createdCount++;
                }
            } catch (\Throwable $e) {
                Log::error('Error importando fila', ['fila_excel' => $excelRow, 'msg' => $e->getMessage()]);
                $this->pushError($excelRow, $e->getMessage(), $rows[$i] instanceof Collection ? $rows[$i]->toArray() : (array)$rows[$i]);
            }
        }
    }

    /* ===================== Helpers de encabezados ===================== */

    private function rowToFlatArray($row): array
    {
        if ($row instanceof Collection) return array_values($row->toArray());
        return array_values((array)$row);
    }

    private function buildCompositeHeaders(array $hdr1, array $hdr2): array
    {
        $len = max(count($hdr1), count($hdr2));
        $headers = [];
        for ($i = 0; $i < $len; $i++) {
            $h1 = isset($hdr1[$i]) ? trim((string)$hdr1[$i]) : '';
            $h2 = isset($hdr2[$i]) ? trim((string)$hdr2[$i]) : '';
            if ($h1 !== '' && $h2 !== '')      $name = $h1.'|'.$h2;
            elseif ($h1 !== '')                $name = $h1;
            elseif ($h2 !== '')                $name = $h2;
            else                               $name = '';
            $headers[] = $name;
        }
        // Hacer únicos
        $seen = [];
        foreach ($headers as $i => $name) {
            $base = $name === '' ? 'col_sin_nombre' : $name;
            if (!isset($seen[$base])) {
                $seen[$base] = 1;
                $headers[$i] = $base;
            } else {
                $headers[$i] = $base.'__'.(++$seen[$base]);
            }
        }
        return $headers;
    }

    private function combineRowWithHeaders(array $headers, array $values): array
    {
        $out = [];
        $len = max(count($headers), count($values));
        for ($i = 0; $i < $len; $i++) {
            $k = $headers[$i] ?? ('col_sin_nombre__'.($i+1));
            $out[$k] = $values[$i] ?? null;
        }
        return $out;
    }

    /* ===================== Normalizador de claves ===================== */

    /** Normaliza una clave de encabezado:
     *  - minúsculas
     *  - colapsa espacios
     *  - quita tildes (áéíóúüñ -> aeiouun)
     *  - elimina signos (/, (), ., comas, guiones, etc.)
     */
    private function normKey(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim(mb_strtolower($s));

        // quitar tildes (sin intl)
        $from = 'áéíóúüñÁÉÍÓÚÜÑ';
        $to   = 'aeiouunAEIOUUN';
        $s = strtr($s, array_combine(
            preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY),
            preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY)
        ));

        // quitar todo lo que no sea letra/dígito/espacio
        $s = preg_replace('/[^a-z0-9 ]/u', '', $s);

        return preg_replace('/\s+/u', ' ', $s);
    }

    /* ===================== Getters ===================== */

    private function S(array $assoc, array $vals, array $cands, ?int $posIdx1Based, int $maxLen = 255): ?string
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        return mb_substr($s, 0, $maxLen);
    }

    // “Exacto” (sin búsqueda por contiene; sí normaliza para coincidencia flexible)
    private function SExact(array $assoc, array $vals, array $cands, ?int $posIdx1Based, int $maxLen = 255): ?string
    {
        // 1) clave exacta sin normalizar
        foreach ($cands as $k) {
            if (array_key_exists($k, $assoc)) {
                $s = trim((string)$assoc[$k]);
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }

        // 2) clave exacta normalizada
        $assocNorm = [];
        foreach ($assoc as $k => $v) $assocNorm[$this->normKey($k)] = $v;

        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            if (array_key_exists($nk, $assocNorm)) {
                $s = trim((string)$assocNorm[$nk]);
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }

        // 3) fallback por posición
        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) {
                $s = trim((string)($vals[$idx] ?? null));
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }
        return null;
    }

    private function I(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?int
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (int)$v;
        $s = preg_replace('/[^\d\-]/', '', (string)$v);
        if ($s === '' || $s === '-' || $s === '--') return null;
        return is_numeric($s) ? (int)$s : null;
    }

    private function F(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?float
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float)$v;
        $vv = str_replace([' ', ','], ['', '.'], (string)$v);
        return is_numeric($vv) ? (float)$vv : null;
    }

    private function D(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?Carbon
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        try {
            if (is_numeric($v)) return Carbon::instance(ExcelDate::excelToDateTimeObject($v));
            $v = trim((string)$v);
            if ($v === '') return null;
            $formats = ['d-m-Y','d/m/Y','Y-m-d','d-m-y','d/m/y'];
            foreach ($formats as $fmt) {
                $dt = Carbon::createFromFormat($fmt, $v);
                if ($dt !== false) return $dt;
            }
            return Carbon::parse($v);
        } catch (\Throwable $e) { return null; }
    }

    /**
     * pick(): intenta obtener el valor por:
     *   1) clave exacta,
     *   2) clave exacta normalizada (sin tildes/ni signos),
     *   3) “contiene” sobre claves normalizadas,
     *   4) fallback por posición.
     */
    private function pick(array $assoc, array $vals, array $cands, ?int $posIdx1Based)
    {
        // 1) exacto
        foreach ($cands as $k) {
            if (array_key_exists($k, $assoc)) return $assoc[$k];
        }

        // 2) exacto normalizado
        $assocNorm = [];
        foreach ($assoc as $k => $v) $assocNorm[$this->normKey($k)] = $v;

        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            if (array_key_exists($nk, $assocNorm)) return $assocNorm[$nk];
        }

        // 3) contiene (sobre normalizados)
        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            foreach ($assoc as $kk => $vv) {
                if (str_contains($this->normKey($kk), $nk)) return $vv;
            }
        }

        // 4) fallback por posición
        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) return $vals[$idx] ?? null;
        }

        return null;
    }

    /* ===================== Errores & getters ===================== */

    private function pushError($filaExcel, $msg, $datos)
    {
        $this->errors[] = ['fila' => $filaExcel, 'error' => $msg, 'datos' => $datos];
    }

    public function getRowCount()     { return $this->rowCount; }
    public function getCreatedCount() { return $this->createdCount; }
    public function getUpdatedCount() { return $this->updatedCount; }
    public function getErrors()
    {
        return [
            'total_errores' => count($this->errors),
            'primeros'      => array_slice($this->errors, 0, 10),
            'todos'         => $this->errors,
        ];
    }

    /**
     * Función personalizada para obtener el valor de Total sin conflictos
     */
    private function getTotalValue(array $assoc, array $vals, ?int $posIdx1Based): ?int
    {
        // 1. Buscar por nombres específicos de Total (no TotalMarbetes)
        $totalCandidates = ['Pasadas TOTAL', 'Pasadas|TOTAL', 'TOTAL', 'Total'];

        foreach ($totalCandidates as $candidate) {
            if (array_key_exists($candidate, $assoc)) {
                $value = $assoc[$candidate];
                if ($value !== null && $value !== '') {
                    $intValue = $this->convertToInt($value);
                    if ($intValue !== null && $this->isValidTotalValue($intValue)) {
                        return $intValue;
                    }
                }
            }
        }

        // 2. Buscar por posición específica
        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) {
                $value = $vals[$idx] ?? null;
                if ($value !== null && $value !== '') {
                    $intValue = $this->convertToInt($value);
                    if ($intValue !== null && $this->isValidTotalValue($intValue)) {
                        return $intValue;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Valida si el valor es un Total válido (no de marbetes)
     */
    private function isValidTotalValue(int $value): bool
    {
        // Excluir solo valores muy específicos de marbetes
        // Los totales de pasadas pueden ser cualquier número > 0
        if ($value <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Convierte un valor a entero de forma segura
     */
    private function convertToInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int)$value;

        $cleaned = preg_replace('/[^\d\-]/', '', (string)$value);
        if ($cleaned === '' || $cleaned === '-' || $cleaned === '--') return null;

        return is_numeric($cleaned) ? (int)$cleaned : null;
    }
}
