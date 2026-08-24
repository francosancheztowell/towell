<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Illuminate\Support\Facades\Schema;

class CatCodificadosDesarrolladorService
{
    /** @var array<string, true>|null */
    private ?array $numericColumnNames = null;

    /** @var array<int, string>|null */
    private ?array $columns = null;

    /**
     * Un solo guardado llamaba aqui entre 8 y 12 veces, y cada llamada era una
     * consulta a INFORMATION_SCHEMA contra un SQL Server remoto (~40 ms de ida y
     * vuelta). El esquema no cambia dentro de una peticion, asi que se memoiza.
     *
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        return $this->columns ??= Schema::getColumnListing((new CatCodificados)->getTable());
    }

    /**
     * Asigna el payload al registro ignorando valores de texto en columnas numéricas
     * (p. ej. calibre "600/1T" contra float), para no romper el UPDATE en SQL Server.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyPayload(CatCodificados $registro, array $payload): void
    {
        $columns = array_flip($this->getColumns());
        $numericColumns = $this->numericColumnNames($registro->getTable());

        foreach ($payload as $column => $value) {
            if (! isset($columns[$column])) {
                continue;
            }

            if (isset($numericColumns[$column])) {
                if ($value === '') {
                    $value = null;
                } elseif (! $this->isNumericCompatible($value)) {
                    continue;
                }
            }

            $registro->setAttribute($column, $value);
        }
    }

    /**
     * @return array<string, true>
     */
    private function numericColumnNames(string $table): array
    {
        if ($this->numericColumnNames !== null) {
            return $this->numericColumnNames;
        }

        $numeric = [];
        foreach (Schema::getColumns($table) as $column) {
            $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
            if (preg_match('/int|float|real|double|decimal|numeric|money|bit/', $type) !== 1) {
                continue;
            }
            $numeric[$column['name']] = true;
        }

        return $this->numericColumnNames = $numeric;
    }

    private function isNumericCompatible(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = str_replace(',', '.', trim($value));

        return $normalized !== '' && is_numeric($normalized);
    }

    /**
     * Localiza el renglon de una orden, prefiriendo el del telar indicado.
     *
     * El desempate por telar importa porque un mismo numero de orden puede describir
     * productos distintos en telares distintos (hay 15 casos en produccion, desde 2021).
     * Sin el, se leia el renglon del telar del operador pero se escribia en el del
     * Id mas alto, que podia ser el del otro producto.
     *
     * El respaldo al Id mas alto NO es opcional: tras un cambio de telar el renglon
     * conserva el telar viejo mientras el programa ya esta en el nuevo, y sin respaldo
     * se crearian duplicados espurios en cada movimiento.
     *
     * ponytail: si es la PRIMERA captura de un numero reutilizado y solo existe el
     * renglon del otro telar, el respaldo lo elige igual. No hay forma de distinguirlo:
     * CatCodificados no guarda ninguna referencia al registro del programa.
     */
    public function resolveForRead(string $noProduccion, ?string $telarId = null): ?CatCodificados
    {
        $queryBase = CatCodificados::query()->where('OrdenTejido', $noProduccion);
        $telarId = trim((string) ($telarId ?? ''));

        if ($telarId !== '') {
            $registro = (clone $queryBase)->where('TelarId', $telarId)->orderByDesc('Id')->first();

            if ($registro) {
                return $registro;
            }
        }

        return (clone $queryBase)->orderByDesc('Id')->first();
    }

    public function resolveCodigoDibujo(string $noProduccion, ?string $telarId = null): ?string
    {
        $registro = $this->resolveForRead($noProduccion, $telarId);
        if (! $registro) {
            return null;
        }

        $codigo = trim((string) ($registro->getAttribute('CodigoDibujo') ?? ''));

        return $codigo !== '' ? $codigo : null;
    }

    /**
     * Renglon sobre el que se escribe. Es el mismo criterio que la lectura: leer de uno
     * y escribir en otro era justamente el fallo.
     */
    public function resolveCanonical(string $noProduccion, ?string $telarId = null): ?CatCodificados
    {
        return $this->resolveForRead($noProduccion, $telarId);
    }
}
