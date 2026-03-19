<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class CatCodificadosDesarrolladorService
{
    public function getColumns(): array
    {
        $modelo = new CatCodificados();

        return Schema::getColumnListing($modelo->getTable());
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
        if (!$registro) {
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
