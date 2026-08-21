<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Illuminate\Database\Eloquent\Builder;
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

    public function buildOrderQuery(string $noProduccion, ?array $columns = null): Builder
    {
        $columns ??= $this->getColumns();

        $query = CatCodificados::query();

        if (in_array('OrdenTejido', $columns, true)) {
            return $query->where('OrdenTejido', $noProduccion);
        }

        if (in_array('NumOrden', $columns, true)) {
            return $query->where('NumOrden', $noProduccion);
        }

        return $query->where('NoProduccion', $noProduccion);
    }

    public function resolveForRead(string $noProduccion, ?string $telarId = null): ?CatCodificados
    {
        $columns = $this->getColumns();
        $queryBase = $this->buildOrderQuery($noProduccion, $columns);
        $telarId = trim((string) ($telarId ?? ''));

        if ($telarId !== '') {
            if (in_array('TelarId', $columns, true)) {
                $registro = (clone $queryBase)->where('TelarId', $telarId)->orderByDesc('Id')->first();
                if ($registro) {
                    return $registro;
                }
            } elseif (in_array('NoTelarId', $columns, true)) {
                $registro = (clone $queryBase)->where('NoTelarId', $telarId)->orderByDesc('Id')->first();
                if ($registro) {
                    return $registro;
                }
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

    public function resolveCanonical(string $noProduccion): ?CatCodificados
    {
        $columns = $this->getColumns();

        return $this->buildOrderQuery($noProduccion, $columns)
            ->orderByDesc('Id')
            ->first();
    }
}
