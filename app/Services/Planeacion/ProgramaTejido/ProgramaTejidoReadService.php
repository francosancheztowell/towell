<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;

/**
 * Lectura v2 de Programa Tejido / Muestras (PT-02 · 02.3).
 *
 * Solo lectura: query builder base (sin modelos hidratados, sin observers, sin escrituras).
 * Columnas, filtros y orden salen de allowlists por superficie: las columnas de la grilla
 * (UtilityHelpers::getTableColumns) menos las que la superficie no tiene físicamente
 * (config planeacion.superficies.<s>.columnas_ausentes). Nunca se interpola input en SQL.
 */
final class ProgramaTejidoReadService
{
    public const POR_PAGINA = [50, 100];

    /**
     * @return list<string> columnas legibles de la superficie, en el orden de la grilla
     */
    public function columnasPermitidas(ProgramaTejidoSurface $superficie): array
    {
        $ausentes = (array) config("planeacion.superficies.{$superficie->value}.columnas_ausentes", []);
        $campos = array_column(UtilityHelpers::getTableColumns(), 'field');

        return array_values(array_unique(array_diff(['Id', ...$campos], $ausentes)));
    }

    /**
     * @param  array{page?: int|string, per_page?: int|string, sort?: string, dir?: string, columnas?: list<string>, filtros?: array<string, string|null>}  $parametros  ya validados
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function leer(ProgramaTejidoSurface $superficie, array $parametros): array
    {
        $permitidas = $this->columnasPermitidas($superficie);
        $columnas = array_values(array_intersect($parametros['columnas'] ?? $permitidas, $permitidas));
        if (! in_array('Id', $columnas, true)) {
            array_unshift($columnas, 'Id');
        }

        $porPagina = (int) ($parametros['per_page'] ?? self::POR_PAGINA[0]);
        $pagina = max(1, (int) ($parametros['page'] ?? 1));

        $sort = isset($parametros['sort']) && in_array($parametros['sort'], $permitidas, true) ? $parametros['sort'] : null;
        $base = $this->consulta($superficie, $sort, ($parametros['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
        foreach ($parametros['filtros'] ?? [] as $campo => $valor) {
            if (! in_array($campo, $permitidas, true)) {
                continue; // el controller ya rechazó llaves fuera de la allowlist
            }
            $valor === null || $valor === '' ? $base->whereNull($campo) : $base->where($campo, $valor);
        }

        // SQL Server rechaza COUNT(*) con ORDER BY: el total va sin orden.
        $total = (clone $base)->reorder()->count();

        $filas = $base->select($columnas)->forPage($pagina, $porPagina)->get();

        return [
            'data' => $filas->map(fn ($fila) => $this->fila((array) $fila))->all(),
            'meta' => [
                'version' => 2,
                'superficie' => $superficie->value,
                'capacidades' => $superficie->capacidades(),
                'columnas' => array_values(array_filter(
                    UtilityHelpers::getTableColumns(),
                    fn (array $c) => in_array($c['field'], $columnas, true)
                )),
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'paginas' => (int) ceil($total / $porPagina),
            ],
        ];
    }

    /**
     * Mismas filas que la grilla legacy para esos Id (lo usa la comparación shadow).
     *
     * @param  list<int>  $ids
     * @param  list<string>  $columnas
     * @return array<int, array<string, mixed>> por Id, en el orden de la grilla
     */
    public function filasPorIds(ProgramaTejidoSurface $superficie, array $ids, array $columnas): array
    {
        $columnas = array_values(array_unique(['Id', ...array_intersect($columnas, $this->columnasPermitidas($superficie))]));
        $resultado = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            foreach ($this->consulta($superficie)->whereIn('Id', $lote)->select($columnas)->get() as $fila) {
                $resultado[(int) $fila->Id] = $this->fila((array) $fila);
            }
        }

        return $resultado;
    }

    /**
     * Valor estable para JSON: fechas como 'Y-m-d H:i:s', el resto tal cual viene de BD.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public function fila(array $fila): array
    {
        return array_map(
            fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v,
            $fila
        );
    }

    /**
     * Regla de validación por columna para filtros: fechas y números se validan antes de
     * llegar a SQL Server (un valor inválido daría error de conversión = 500, no 422).
     *
     * @return list<string>
     */
    public function reglaFiltro(string $columna): array
    {
        $cast = strtok((string) ((new ReqProgramaTejido)->getCasts()[$columna] ?? 'string'), ':');

        return match ($cast) {
            'int', 'integer' => ['nullable', 'integer'],
            'float', 'double', 'real', 'decimal' => ['nullable', 'numeric'],
            'bool', 'boolean' => ['nullable', 'boolean'],
            'date', 'datetime', 'immutable_date', 'immutable_datetime' => ['nullable', 'date'],
            default => ['nullable', 'string', 'max:200'],
        };
    }

    /**
     * Orden pedido primero; el de la grilla legacy (scopeOrdenado) desempata y el Id al final
     * lo hace determinista entre páginas. Sin $sort, el orden legacy + Id.
     */
    private function consulta(ProgramaTejidoSurface $superficie, ?string $sort = null, string $dir = 'asc'): Builder
    {
        // from() explícito: la tabla sale de la superficie, no del config que pone el middleware.
        $query = ReqProgramaTejido::query()->from($superficie->tabla());
        if ($sort !== null) {
            $query->orderBy($sort, $dir);
        }
        $base = $query->ordenado()->orderBy('Id')->toBase();

        // SQL Server rechaza la misma columna dos veces en ORDER BY (Msg 169): si el sort pedido
        // ya está en el orden legacy, se queda solo la primera aparición (la pedida).
        $vistas = [];
        $base->orders = array_values(array_filter($base->orders ?? [], function (array $orden) use (&$vistas): bool {
            if (! isset($orden['column'])) {
                return true; // orderByRaw (CASE de telar numérico)
            }
            $columna = (string) $orden['column'];
            if (isset($vistas[$columna])) {
                return false;
            }
            $vistas[$columna] = true;

            return true;
        }));

        return $base;
    }
}
