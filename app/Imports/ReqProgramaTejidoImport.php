<?php

namespace App\Imports;

use App\Models\ReqProgramaTejido;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Carbon\Carbon;

class ReqProgramaTejidoImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, SkipsOnError, SkipsErrors, SkipsFailures
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Mapear las columnas del Excel a los campos de la tabla
        return new ReqProgramaTejido([
            // Datos principales
            'EnProceso' => $this->parseBoolean($row['en_proceso'] ?? null),
            'CuentaRizo' => $row['cuenta_rizo'] ?? null,
            'CalibreRizo' => $this->parseFloat($row['calibre_rizo'] ?? null),
            'SalonTejidoId' => $row['salon_tejido_id'] ?? null,
            'NoTelarId' => $row['no_telar_id'] ?? null,
            'Ultimo' => $this->parseBoolean($row['ultimo'] ?? null),
            'CambioHilo' => $this->parseBoolean($row['cambio_hilo'] ?? null),
            'Maquina' => $row['maquina'] ?? null,
            'Ancho' => $this->parseFloat($row['ancho'] ?? null),
            'EficienciaSTD' => $this->parseFloat($row['eficiencia_std'] ?? null),
            'VelocidadSTD' => $this->parseInteger($row['velocidad_std'] ?? null),
            'FibraRizo' => $row['fibra_rizo'] ?? null,
            'CalibrePie' => $this->parseFloat($row['calibre_pie'] ?? null),
            'CalendarioId' => $row['calendario_id'] ?? null,
            'TamanoClave' => $row['tamano_clave'] ?? null,
            'NoExisteBase' => $row['no_existe_base'] ?? null,
            'ItemId' => $row['item_id'] ?? null,
            'InventSizeId' => $row['invent_size_id'] ?? null,
            'Rasurado' => $row['rasurado'] ?? null,
            'NombreProducto' => $row['nombre_producto'] ?? null,

            // Pedido y producción
            'TotalPedido' => $this->parseFloat($row['total_pedido'] ?? null),
            'Produccion' => $this->parseFloat($row['produccion'] ?? null),
            'SaldoPedido' => $this->parseFloat($row['saldo_pedido'] ?? null),
            'SaldoMarbete' => $this->parseInteger($row['saldo_marbete'] ?? null),
            'ProgramarProd' => $this->parseDate($row['programar_prod'] ?? null),
            'NoProduccion' => $row['no_produccion'] ?? null,
            'Programado' => $this->parseDate($row['programado'] ?? null),
            'FlogsId' => $row['flogs_id'] ?? null,
            'NombreProyecto' => $row['nombre_proyecto'] ?? null,
            'CustName' => $row['cust_name'] ?? null,
            'AplicacionId' => $row['aplicacion_id'] ?? null,
            'Observaciones' => $row['observaciones'] ?? null,
            'TipoPedido' => $row['tipo_pedido'] ?? null,
            'NoTiras' => $this->parseInteger($row['no_tiras'] ?? null),
            'Peine' => $this->parseInteger($row['peine'] ?? null),
            'Luchaje' => $this->parseInteger($row['luchaje'] ?? null),
            'PesoCrudo' => $this->parseInteger($row['peso_crudo'] ?? null),
            'CalibreTrama' => $this->parseFloat($row['calibre_trama'] ?? null),
            'FibraTrama' => $row['fibra_trama'] ?? null,
            'DobladilloId' => $row['dobladillo_id'] ?? null,
            'PasadasTrama' => $this->parseInteger($row['pasadas_trama'] ?? null),
            'PasadasComb1' => $this->parseInteger($row['pasadas_comb1'] ?? null),
            'PasadasComb2' => $this->parseInteger($row['pasadas_comb2'] ?? null),
            'PasadasComb3' => $this->parseInteger($row['pasadas_comb3'] ?? null),
            'PasadasComb4' => $this->parseInteger($row['pasadas_comb4'] ?? null),
            'PasadasComb5' => $this->parseInteger($row['pasadas_comb5'] ?? null),
            'AnchoToalla' => $this->parseInteger($row['ancho_toalla'] ?? null),

            // Colores y combinaciones
            'CodColorTrama' => $row['cod_color_trama'] ?? null,
            'ColorTrama' => $row['color_trama'] ?? null,
            'CalibreComb12' => $this->parseFloat($row['calibre_comb12'] ?? null),
            'FibraComb1' => $row['fibra_comb1'] ?? null,
            'CodColorComb1' => $row['cod_color_comb1'] ?? null,
            'NombreCC1' => $row['nombre_cc1'] ?? null,
            'CalibreComb22' => $this->parseFloat($row['calibre_comb22'] ?? null),
            'FibraComb2' => $row['fibra_comb2'] ?? null,
            'CodColorComb2' => $row['cod_color_comb2'] ?? null,
            'NombreCC2' => $row['nombre_cc2'] ?? null,
            'CalibreComb32' => $this->parseFloat($row['calibre_comb32'] ?? null),
            'FibraComb3' => $row['fibra_comb3'] ?? null,
            'CodColorComb3' => $row['cod_color_comb3'] ?? null,
            'NombreCC3' => $row['nombre_cc3'] ?? null,
            'CalibreComb42' => $this->parseFloat($row['calibre_comb42'] ?? null),
            'FibraComb4' => $row['fibra_comb4'] ?? null,
            'CodColorComb4' => $row['cod_color_comb4'] ?? null,
            'NombreCC4' => $row['nombre_cc4'] ?? null,
            'CalibreComb52' => $this->parseFloat($row['calibre_comb52'] ?? null),
            'FibraComb5' => $row['fibra_comb5'] ?? null,
            'CodColorComb5' => $row['cod_color_comb5'] ?? null,
            'NombreCC5' => $row['nombre_cc5'] ?? null,

            // Datos del Pie
            'MedidaPlano' => $this->parseInteger($row['medida_plano'] ?? null),
            'CuentaPie' => $row['cuenta_pie'] ?? null,
            'CodColorCtaPie' => $row['cod_color_cta_pie'] ?? null,
            'NombreCPie' => $row['nombre_cpie'] ?? null,

            // Producción y métricas
            'PesoGRM2' => $this->parseInteger($row['peso_grm2'] ?? null),
            'DiasEficiencia' => $this->parseFloat($row['dias_eficiencia'] ?? null),
            'ProdKgDia' => $this->parseFloat($row['prod_kg_dia'] ?? null),
            'StdDia' => $this->parseFloat($row['std_dia'] ?? null),
            'ProdKgDia2' => $this->parseFloat($row['prod_kg_dia2'] ?? null),
            'StdToaHra' => $this->parseFloat($row['std_toa_hra'] ?? null),
            'DiasJornada' => $this->parseFloat($row['dias_jornada'] ?? null),
            'HorasProd' => $this->parseFloat($row['horas_prod'] ?? null),
            'StdHrsEfect' => $this->parseFloat($row['std_hrs_efect'] ?? null),
            'FechaInicio' => $this->parseDate($row['fecha_inicio'] ?? null),
            'Calc4' => $this->parseFloat($row['calc4'] ?? null),
            'Calc5' => $this->parseFloat($row['calc5'] ?? null),
            'Calc6' => $this->parseFloat($row['calc6'] ?? null),
            'FechaFinal' => $this->parseDate($row['fecha_final'] ?? null),
            'EntregaProduc' => $this->parseDate($row['entrega_produc'] ?? null),
            'EntregaPT' => $this->parseDate($row['entrega_pt'] ?? null),
            'EntregaCte' => $this->parseDate($row['entrega_cte'] ?? null),
            'PTvsCte' => $this->parseInteger($row['pt_vs_cte'] ?? null),
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            // Solo validar campos que sabemos que existen
            '*.nombre_producto' => 'nullable|string|max:100',
            '*.salon_tejido_id' => 'nullable|string|max:10',
            '*.no_telar_id' => 'nullable|string|max:10',
        ];
    }

    /**
     * Parse boolean values
     */
    private function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'si', 'sí', 'verdadero']);
        }

        return (bool) $value;
    }

    /**
     * Parse float values
     */
    private function parseFloat($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Remove commas and convert to float
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Parse integer values
     */
    private function parseInteger($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Remove commas and convert to integer
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Parse date values
     */
    private function parseDate($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        try {
            // If it's already a Carbon instance
            if ($value instanceof Carbon) {
                return $value->format('Y-m-d');
            }

            // If it's a timestamp
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp($value)->format('Y-m-d');
            }

            // Try to parse as date
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * @param \Throwable $e
     */
    public function onError(\Throwable $e)
    {
        // Log the error but continue processing
        \Log::error('Error importing row: ' . $e->getMessage());
    }
}


