<?php

namespace App\Imports;

use App\Imports\Contracts\ImportConEstadisticas;
use App\Models\Urdido\URDCatalogoMaquina;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Excel del Catálogo de Telares (columnas Salon y Telar; Nombre y Grupo se ignoran) hacia
 * URDCatalogoMaquinas. Cada fila da de alta el telar o le corrige el salón; un número que ya es
 * máquina de otra área (Urdido, Costura…) no se toca. Se guarda fila por fila para que un telar
 * repetido en el mismo archivo no se dé de alta dos veces.
 */
class TelaresImport implements ImportConEstadisticas, ToModel, WithChunkReading, WithHeadingRow
{
    private int $rowCounter = 0;

    private int $processedRows = 0;

    private int $skippedRows = 0;

    private int $createdRows = 0;

    private int $updatedRows = 0;

    /** @var list<string> */
    private array $errores = [];

    /**
     * Obtiene el primer valor no vacío para un conjunto de alias de encabezado, con normalización.
     */
    private function getValue(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeKey($alias);
            if (array_key_exists($key, $row)) {
                $val = $row[$key];
                if ($val !== '' && $val !== null) {
                    return $val;
                }
            }
        }

        return null;
    }

    /** Normaliza una clave de encabezado: minúsculas, sin acentos y sólo [a-z0-9_] */
    private function normalizeKey(string $key): string
    {
        $s = mb_strtolower(trim($key), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s);
        $s = preg_replace('/_+/', '_', $s);

        return trim($s, '_');
    }

    /** Normaliza todas las claves del arreglo de fila */
    private function normalizeRowKeys(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$this->normalizeKey((string) $k)] = $v;
        }

        return $out;
    }

    /**
     * @return null Siempre: la fila se guarda aquí mismo.
     */
    public function model(array $row)
    {
        $this->rowCounter++;
        $row = $this->normalizeRowKeys($row);

        $salon = $this->texto($this->getValue($row, ['Salon', 'salón', 'salon_tejido_id']));
        $telar = $this->texto($this->getValue($row, ['TELAR', 'tela', 'no_telar_id']));

        // Encabezado repetido dentro del cuerpo.
        if (strcasecmp($salon, 'Salon') === 0 && strcasecmp($telar, 'Telar') === 0) {
            $this->skippedRows++;

            return null;
        }

        if ($salon === '' || $telar === '') {
            return $this->saltar("Faltan datos requeridos (Salon: '{$salon}', Telar: '{$telar}')");
        }
        if (mb_strlen($telar) > 10) {
            return $this->saltar("El telar '{$telar}' pasa de 10 caracteres");
        }

        $departamento = URDCatalogoMaquina::departamentoDeSalon($salon);
        if ($departamento === null) {
            return $this->saltar("Salón '{$salon}' no es ".implode(', ', URDCatalogoMaquina::DEPARTAMENTOS_TELARES));
        }

        $existente = URDCatalogoMaquina::query()->where('MaquinaId', $telar)->first();

        if (! $existente) {
            URDCatalogoMaquina::create([
                'MaquinaId' => $telar,
                'Nombre' => $departamento,
                'Departamento' => $departamento,
                'Codificacion' => URDCatalogoMaquina::codificacionTelar($departamento, $telar),
            ]);
            $this->processedRows++;
            $this->createdRows++;

            return null;
        }

        if (! in_array($existente->Departamento, URDCatalogoMaquina::DEPARTAMENTOS_TELARES, true)) {
            return $this->saltar("El número {$telar} ya es una máquina de {$existente->Departamento}");
        }

        // Itema y Smith son el mismo salón: un Excel que dice "Smith" no le quita lo Itema a un telar.
        if (URDCatalogoMaquina::salonDe($existente->Departamento) !== URDCatalogoMaquina::salonDe($departamento)) {
            $cambios = ['Departamento' => $departamento];
            if ($existente->Nombre === $existente->Departamento) {
                $cambios['Nombre'] = $departamento;
            }
            $existente->update($cambios);
        }
        $this->processedRows++;
        $this->updatedRows++;

        return null;
    }

    private function saltar(string $motivo): null
    {
        $this->errores[] = "Fila {$this->rowCounter}: {$motivo}";
        $this->skippedRows++;

        return null;
    }

    private function texto(mixed $valor): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $valor));
    }

    /**
     * Tamaño de lectura por chunks para controlar memoria
     */
    public function chunkSize(): int
    {
        return 100;
    }

    public function getStats(): array
    {
        return [
            'processed_rows' => $this->processedRows,
            'created_rows' => $this->createdRows,
            'updated_rows' => $this->updatedRows,
            'skipped_rows' => $this->skippedRows,
            'total_rows' => $this->rowCounter,
            'errores' => $this->errores,
        ];
    }
}
