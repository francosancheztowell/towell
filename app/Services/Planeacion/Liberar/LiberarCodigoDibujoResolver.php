<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;

/**
 * Resolución de Código de Dibujo al liberar.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → misma salida:
 * la grilla gana; si viene vacía, último CodigoDibujo no vacío en
 * CatCodificados (Id descendente) por Item+Departamento, luego
 * Item+talla+Departamento, luego Item+talla.
 */
final class LiberarCodigoDibujoResolver
{
    /**
     * Último CodigoDibujo no vacío en CatCodificados (Id descendente).
     *
     * @return string|null Primer CodigoDibujo no vacío con Id más alto en cada consulta filtrada
     */
    public function resolver(string $itemId, string $inventSizeId, string $departamento): ?string
    {
        $try = function (\Illuminate\Database\Eloquent\Builder $q): ?string {
            foreach ($q->orderByDesc('Id')->get(['Id', 'CodigoDibujo']) as $row) {
                $c = trim((string) ($row->CodigoDibujo ?? ''));
                if ($c !== '') {
                    return $c;
                }
            }

            return null;
        };

        // 1) Item + Departamento (= Salón tejido): último con código (regla de negocio principal)
        if ($itemId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        // 2) Item + InventSizeId + Departamento (cuando hace falta acotar por tamaño en AX)
        if ($itemId !== '' && $inventSizeId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        // 3) Sin salón conocido: por Item + InventSizeId únicamente
        if ($itemId !== '' && $inventSizeId !== '') {
            return $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId));
        }

        return null;
    }

    /**
     * Pantalla tiene prioridad; si no, último código en CatCodificados (Item + salón, etc.).
     *
     * @param  array<string, mixed>  $item
     */
    public function paraLiberacion(array $item, ReqProgramaTejido $registro): string
    {
        $explicit = trim((string) ($item['codigoDibujo'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $res = $this->resolver(
            trim((string) ($registro->ItemId ?? '')),
            trim((string) ($registro->InventSizeId ?? '')),
            trim((string) ($registro->SalonTejidoId ?? ''))
        );

        return ($res !== null && $res !== '') ? trim((string) $res) : '';
    }

    /**
     * Autollenado de la grilla: combinations separadas por coma.
     * Cada valor es itemId::inventSizeId::salonTejidoId (salon = Departamento),
     * o legado itemId:inventSizeId.
     *
     * @return array<string, string> clave item|talla|departamento => CodigoDibujo
     */
    public function mapearCombinaciones(string $combinationsParam): array
    {
        $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

        if ($combinations === []) {
            return [];
        }

        $pairs = [];
        foreach ($combinations as $combo) {
            $itemId = '';
            $inventSizeId = '';
            $departamento = '';

            if (str_contains($combo, '::')) {
                $parts = explode('::', $combo, 3);
                $itemId = trim((string) ($parts[0] ?? ''));
                $inventSizeId = trim((string) ($parts[1] ?? ''));
                $departamento = trim((string) ($parts[2] ?? ''));
            } else {
                $parts = explode(':', $combo, 2);
                $itemId = trim((string) ($parts[0] ?? ''));
                $inventSizeId = trim((string) ($parts[1] ?? ''));
            }

            if ($itemId === '' || ($inventSizeId === '' && $departamento === '')) {
                continue;
            }

            $cacheKey = $itemId.'|'.$inventSizeId.'|'.$departamento;
            $pairs[$cacheKey] = [
                'itemId' => $itemId,
                'inventSizeId' => $inventSizeId,
                'departamento' => $departamento,
                'cacheKey' => $cacheKey,
            ];
        }

        if ($pairs === []) {
            return [];
        }

        $map = [];
        foreach ($pairs as $pair) {
            $codigo = $this->resolver(
                $pair['itemId'],
                $pair['inventSizeId'],
                $pair['departamento']
            );
            if ($codigo !== null && $codigo !== '') {
                $map[$pair['cacheKey']] = $codigo;
            }
        }

        return $map;
    }
}
