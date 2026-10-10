<?php

declare(strict_types=1);

namespace App\Repositories\Ax;

use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L.Mat CRUDO en AX (BOMTABLE + BOMVERSION, conexión sqlsrv_ti).
 * EXISTS en vez de JOIN: un item puede tener varias versiones del mismo BOMID
 * y el JOIN devolvía la misma lista 2 o 3 veces.
 */
final class BomCrudoRepository
{
    /**
     * @param  string|null  $inventSizeId  null u '' = no filtrar por talla
     * @param  string|null  $salon  null u '' = cualquier variante conocida de AX
     */
    public function consulta(string $itemId, ?string $inventSizeId = null, ?string $salon = null): Builder
    {
        $query = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->where('BT.Vigente', 1)
            ->whereExists(function ($sub) use ($itemId) {
                // El 1 no se lee: solo confirma que el BOM pertenece al item.
                $sub->select(DB::raw('1'))
                    ->from('BOMVERSION as BV')
                    ->whereColumn('BV.BOMID', 'BT.BOMID')
                    ->where('BV.ITEMID', $itemId.'-1');
            });

        if ($inventSizeId !== null && trim($inventSizeId) !== '') {
            $query->where('BT.TWINVENTSIZEID', trim($inventSizeId));
        }

        if ($salon !== null && trim($salon) !== '') {
            $query->whereIn('BT.TWSALON', TelarSalonResolver::salonAliasesAx($salon));
        } else {
            $query->whereIn('BT.TwSalon', TelarSalonResolver::todosLosAliasesAx());
        }

        return $query->orderBy('BT.BOMID');
    }

    /**
     * Lista del catálogo de codificación: tope 50, sin duplicar por versión de AX.
     * Si el item viene vacío o AX no responde, devuelve [].
     *
     * @return array<int, array{bomId: string, bomName: string}>
     */
    public function listarParaCodificacion(string $itemId, ?string $inventSizeId = null, int $limite = 50): array
    {
        try {
            $itemId = trim($itemId);
            if ($itemId === '') {
                return [];
            }

            return $this->consulta($itemId, $inventSizeId)
                ->limit($limite)
                ->get()
                ->map(fn ($r) => [
                    'bomId' => $r->bomId !== null ? (string) $r->bomId : '',
                    'bomName' => $r->bomName !== null ? (string) $r->bomName : '',
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('BomCrudoRepository::listarParaCodificacion', [
                'itemId' => $itemId,
                'inventSizeId' => $inventSizeId ?? '',
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
