<?php

namespace App\Services\Planeacion\CatCodificados\Excel;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class CatCodificadosExcelHeaderMapper
{
    /**
     * @return array<int, array{column: string, header: string, field: string|null, optional?: bool}>
     */
    private function template(): array
    {
        $definitions = [
            ['header' => 'Num de Orden', 'field' => 'OrdenTejido'],
            ['header' => 'Fecha  Orden', 'field' => 'FechaTejido'],
            ['header' => 'Fecha   Cumplimiento.', 'field' => 'FechaCumplimiento'],
            ['header' => 'Departamento', 'field' => 'Departamento'],
            ['header' => 'Telar Actual', 'field' => 'TelarId'],
            ['header' => 'Prioridad', 'field' => 'Prioridad'],
            ['header' => 'Modelo', 'field' => 'Nombre'],
            ['header' => 'CLAVE MODELO', 'field' => 'ClaveModelo'],
            ['header' => 'CLAVE  AX', 'field' => 'ItemId'],
            ['header' => 'Tamaño', 'field' => 'InventSizeId'],
            ['header' => 'TOLERANCIA', 'field' => 'Tolerancia'],
            ['header' => 'CODIGO DE DIBUJO', 'field' => 'CodigoDibujo'],
            ['header' => 'Fecha Compromiso', 'field' => 'FechaCompromiso'],
            ['header' => 'Flogs', 'field' => 'FlogsId'],
            ['header' => 'Nombre de Formato Logístico', 'field' => 'NombreProyecto'],
            ['header' => 'Clave', 'field' => 'Clave'],
            ['header' => 'Cantidad a Producir', 'field' => 'Cantidad'],
            ['header' => 'Peine', 'field' => 'Peine'],
            ['header' => 'Ancho', 'field' => 'Ancho'],
            ['header' => 'Largo', 'field' => 'Largo'],
            ['header' => 'P_crudo', 'field' => 'P_crudo'],
            ['header' => 'Luchaje', 'field' => 'Luchaje'],
            ['header' => 'Tra', 'field' => 'Tra'],
            ['header' => 'Hilo', 'field' => 'CalibreTrama2'],
            ['header' => 'OBS.', 'field' => 'FibraId'],
            ['header' => 'Tipo plano', 'field' => 'DobladilloId'],
            ['header' => 'Med plano', 'field' => 'MedidaPlano'],
            ['header' => 'TIPO DE RIZO', 'field' => 'TipoRizo'],
            ['header' => 'ALTURA DE RIZO', 'field' => 'AlturaRizo'],
            ['header' => 'OBS', 'field' => 'Obs'],
            ['header' => 'Veloc.    Mínima', 'field' => 'VelocidadSTD'],
            ['header' => 'Rizo', 'field' => 'CalibreRizo'],
            ['header' => 'Hilo', 'field' => 'CalibreRizo2'],
            ['header' => 'CUENTA', 'field' => 'CuentaRizo'],
            ['header' => 'OBS.', 'field' => 'FibraRizo'],
            ['header' => 'Pie', 'field' => 'CalibrePie'],
            ['header' => 'Hilo', 'field' => 'CalibrePie2'],
            ['header' => 'CUENTA', 'field' => 'CuentaPie'],
            ['header' => 'OBS.', 'field' => 'FibraPie'],
            ['header' => 'C1', 'field' => 'Comb1'],
            ['header' => 'OBS', 'field' => 'Obs1'],
            ['header' => 'C2', 'field' => 'Comb2'],
            ['header' => 'OBS', 'field' => 'Obs2'],
            ['header' => 'C3', 'field' => 'Comb3'],
            ['header' => 'OBS', 'field' => 'Obs3'],
            ['header' => 'C4', 'field' => 'Comb4'],
            ['header' => 'OBS', 'field' => 'Obs4'],
            ['header' => 'Med. de Cenefa', 'field' => 'MedidaCenefa'],
            ['header' => 'Med de inicio de rizo a cenefa', 'field' => 'MedIniRizoCenefa'],
            ['header' => 'RAZURADA', 'field' => 'Razurada'],
            ['header' => 'TIRAS', 'field' => 'NoTiras'],
            ['header' => 'Repeticiones p/corte', 'field' => 'Repeticiones'],
            ['header' => 'No. De Marbetes', 'field' => 'NoMarbete'],
            ['header' => 'Cambio de repaso', 'field' => 'CambioRepaso'],
            ['header' => 'Vendedor', 'field' => 'Vendedor'],
            ['header' => 'CategoriaCalidad', 'field' => 'CategoriaCalidad'],
            ['header' => 'Observaciones', 'field' => 'Obs5'],
            ['header' => 'TRAMA (Ancho Peine)', 'field' => 'TramaAnchoPeine'],
            ['header' => 'LOG. DE LUCHA TOTAL', 'field' => 'LogLuchaTotal'],
            ['header' => 'C1  Trama de Fondo', 'field' => 'CalTramaFondoC1'],
            ['header' => 'Hilo', 'field' => 'CalTramaFondoC12'],
            ['header' => 'OBS.', 'field' => 'FibraTramaFondoC1'],
            ['header' => 'PASADAS', 'field' => 'PasadasTramaFondoC1'],
            ['header' => 'C1', 'field' => 'CalibreComb1'],
            ['header' => 'Hilo', 'field' => 'CalibreComb12'],
            ['header' => 'OBS.', 'field' => 'FibraComb1'],
            ['header' => 'PASADAS', 'field' => 'PasadasComb1'],
            ['header' => 'C2', 'field' => 'CalibreComb2'],
            ['header' => 'Hilo', 'field' => 'CalibreComb22'],
            ['header' => 'OBS.', 'field' => 'FibraComb2'],
            ['header' => 'PASADAS', 'field' => 'PasadasComb2'],
            ['header' => 'C3', 'field' => 'CalibreComb3'],
            ['header' => 'Hilo', 'field' => 'CalibreComb32'],
            ['header' => 'OBS.', 'field' => 'FibraComb3'],
            ['header' => 'PASADAS', 'field' => 'PasadasComb3'],
            ['header' => 'C4', 'field' => 'CalibreComb4'],
            ['header' => 'Hilo', 'field' => 'CalibreComb42'],
            ['header' => 'OBS.', 'field' => 'FibraComb4'],
            ['header' => 'PASADAS', 'field' => 'PasadasComb4'],
            ['header' => 'C5', 'field' => 'CalibreComb5'],
            ['header' => 'Hilo', 'field' => 'CalibreComb52'],
            ['header' => 'OBS.', 'field' => 'FibraComb5'],
            ['header' => 'PASADAS', 'field' => 'PasadasComb5'],
            ['header' => 'TOTAL', 'field' => 'Total'],
            ['header' => 'RESPONSABLE DE INICIO', 'field' => 'RespInicio'],
            ['header' => 'Hr DE INICIO', 'field' => 'HrInicio'],
            ['header' => 'Hr DE TERMINO', 'field' => 'HrTermino'],
            ['header' => 'MINUTOS DEL CAMBIO=', 'field' => 'MinutosCambio'],
            ['header' => 'PESO MUESTRA', 'field' => 'PesoMuestra'],
            ['header' => 'REGISTRO DE ALINEACION', 'field' => 'RegAlinacion'],
            ['header' => '', 'field' => null, 'optional' => true],
            ['header' => 'OBSERVACIONES PARA PROGRAMACION DE PROD.', 'field' => 'OBSParaPro'],
            ['header' => 'Cantidad a Producir', 'field' => 'CantidadProducir_2'],
            ['header' => 'Tejidas', 'field' => 'Tejidas'],
            ['header' => 'pza. x rollo', 'field' => 'pzaXrollo'],
        ];

        foreach ($definitions as $index => &$definition) {
            $definition['column'] = Coordinate::stringFromColumnIndex($index + 1);
        }
        unset($definition);

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    public function expectedHeaders(): array
    {
        return array_map(
            static fn (array $definition): string => $definition['header'],
            $this->template()
        );
    }

    /**
     * @return array<int, string>
     */
    public function expectedColumns(): array
    {
        return array_map(
            static fn (array $definition): string => $definition['column'],
            $this->template()
        );
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array{columnMap: array<int, string>, errors: array<int, array{column: int, column_letter: string, expected: string, actual: string}>}
     */
    public function map(array $headers): array
    {
        $errors = [];
        $columnMap = [];

        foreach ($this->template() as $index => $definition) {
            $actualHeader = isset($headers[$index]) ? (string) $headers[$index] : '';

            if (!$this->matches($actualHeader, $definition)) {
                $errors[] = [
                    'column' => $index + 1,
                    'column_letter' => $definition['column'],
                    'expected' => $definition['header'],
                    'actual' => trim($actualHeader),
                ];

                continue;
            }

            if ($definition['field'] !== null) {
                $columnMap[$index] = $definition['field'];
            }
        }

        return [
            'columnMap' => $columnMap,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array{column: string, header: string, field: string|null, optional?: bool}  $definition
     */
    private function matches(string $actualHeader, array $definition): bool
    {
        $normalizedActual = $this->normalize($actualHeader);
        if ($normalizedActual === '') {
            return ($definition['optional'] ?? false) === true;
        }

        return $normalizedActual === $this->normalize($definition['header']);
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
