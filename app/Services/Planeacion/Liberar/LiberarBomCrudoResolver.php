<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolución de L.Mat CRUDO (BOMTABLE + BOMVERSION en sqlsrv_ti) al liberar.
 *
 * Extraído de LiberarOrdenesController: misma query EXISTS, mismos filtros de
 * salón/talla y la misma precarga por lote. No unifica todavía
 * {@see \App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController::queryLmatDesdeTi}
 * (JOIN + limit 50, sin filtro de salón): eso es follow-up de BUG-007.
 */
final class LiberarBomCrudoResolver
{
    /** L.Mat CRUDO del lote por item|talla|salón; null = no precargado (ruta de un solo renglón). */
    private ?array $bomCrudoCache = null;

    /**
     * Query base de L.Mat CRUDO: única fuente de verdad para las búsquedas de BOM
     * de Liberar. Amarra el L.Mat al ItemId con EXISTS en lugar de JOIN porque un
     * item puede tener varias versiones del mismo BOMID en BOMVERSION, y el JOIN
     * devolvía la misma fila 2-3 veces.
     *
     * @param  string|null  $inventSizeId  null u '' = no filtrar por talla
     * @param  string|null  $salon  null u '' = aceptar cualquier variante conocida de AX
     */
    public function query(string $itemId, ?string $inventSizeId = null, ?string $salon = null): Builder
    {
        $query = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->where('BT.Vigente', 1)
            ->whereExists(function ($sub) use ($itemId) {
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
     * Búsqueda amplia cuando la exacta no devuelve nada: relaja talla y salón,
     * pero NUNCA el ItemId. Sin item no hay resultados.
     */
    public function queryFallback(string $itemId, string $term = '', int $limit = 50): Collection
    {
        $itemId = trim($itemId);

        if ($itemId === '') {
            return collect();
        }

        $query = $this->query($itemId);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('BT.BOMID', 'like', '%'.$term.'%')
                    ->orWhere('BT.NAME', 'like', '%'.$term.'%');
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * Autocompletado individual (un item). Con fallback relaja talla/salón, no el item.
     */
    public function buscarPorItem(
        string $itemId,
        string $inventSizeId = '',
        string $salon = '',
        string $term = '',
        bool $allowFallback = false,
        int $limit = 20
    ): Collection {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return collect();
        }

        $query = $this->query($itemId, $inventSizeId, $salon);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('BT.BOMID', 'like', '%'.$term.'%')
                    ->orWhere('BT.NAME', 'like', '%'.$term.'%');
            });
        }

        $results = $query->limit($limit)->get();

        if ($results->isEmpty() && $allowFallback) {
            return $this->queryFallback($itemId, $term);
        }

        return $results;
    }

    /**
     * Parsea `combinations`: "itemId::inventSizeId" o "itemId::inventSizeId::..."
     * (tercer segmento ignorado). Legado: "itemId:inventSizeId".
     *
     * @return array<int, array{itemIdWithSuffix: string, inventSizeId: string}>
     */
    public function parsearCombinaciones(string $combinationsParam): array
    {
        $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));
        $pairs = [];

        foreach ($combinations as $combo) {
            $itemIdCombo = '';
            $inventSizeIdCombo = '';
            if (str_contains($combo, '::')) {
                $parts = array_map('trim', explode('::', $combo, 3));
                $itemIdCombo = $parts[0] ?? '';
                $inventSizeIdCombo = $parts[1] ?? '';
            } else {
                $parts = array_map('trim', explode(':', $combo, 2));
                if (count($parts) === 2) {
                    $itemIdCombo = $parts[0];
                    $inventSizeIdCombo = $parts[1];
                }
            }
            if ($itemIdCombo === '' || $inventSizeIdCombo === '') {
                continue;
            }
            $pairs[] = [
                'itemIdWithSuffix' => $itemIdCombo.'-1',
                'inventSizeId' => $inventSizeIdCombo,
            ];
        }

        return $pairs;
    }

    /**
     * Autollenado masivo: todas las L.Mat distintas por item|talla.
     * Las ESTAND se omiten (nunca deben quedar puestas sin elección manual).
     *
     * @param  array<int, array{itemIdWithSuffix: string, inventSizeId: string}>  $pairs
     * @return array<string, array<int, array{bomId: string, bomName: string}>>
     */
    public function opcionesPorCombinaciones(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $results = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
            ->distinct()
            ->select('BV.ITEMID', 'BT.TWINVENTSIZEID', 'BT.BOMID as bomId', 'BT.NAME as bomName')
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->where('BT.Vigente', 1)
            ->whereIn('BT.TwSalon', TelarSalonResolver::todosLosAliasesAx())
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('BV.ITEMID', $pair['itemIdWithSuffix'])
                            ->where('BT.TWINVENTSIZEID', $pair['inventSizeId']);
                    });
                }
            })
            ->orderBy('BT.BOMID')
            ->get();

        $porClave = [];
        foreach ($results as $result) {
            $matchingPairs = array_values(array_filter(
                $pairs,
                static function (array $p) use ($result): bool {
                    return $p['itemIdWithSuffix'] === $result->ITEMID && $p['inventSizeId'] === $result->TWINVENTSIZEID;
                }
            ));

            if ($matchingPairs === []) {
                continue;
            }

            $key = self::itemIdSinSufijo((string) $result->ITEMID).'|'.$result->TWINVENTSIZEID;
            $bomId = trim((string) $result->bomId);

            if ($bomId === '' || self::esBomEstandar($bomId)) {
                continue;
            }

            $porClave[$key][$bomId] = [
                'bomId' => $bomId,
                'bomName' => trim((string) $result->bomName),
            ];
        }

        return array_map('array_values', $porClave);
    }

    public function resolverExacto(ReqProgramaTejido $registro): ?object
    {
        $auto = self::bomAutoAsignable($this->resolverOpciones($registro));

        return $auto !== null ? (object) $auto : null;
    }

    /**
     * @return array<int, array{bomId: string, bomName: string}>
     */
    public function resolverOpciones(ReqProgramaTejido $registro): array
    {
        $itemId = trim((string) ($registro->ItemId ?? ''));
        $inventSizeId = trim((string) ($registro->InventSizeId ?? ''));
        $salon = $this->normalizarSalonBomCrudo($registro);

        if ($itemId === '' || $inventSizeId === '' || $salon === '') {
            return [];
        }

        if ($this->bomCrudoCache !== null) {
            return $this->bomCrudoCache[self::claveBomCrudo($itemId, $inventSizeId, $salon)] ?? [];
        }

        return $this->query($itemId, $inventSizeId, $salon)
            ->get()
            ->map(fn ($row) => [
                'bomId' => trim((string) ($row->bomId ?? '')),
                'bomName' => trim((string) ($row->bomName ?? '')),
            ])
            ->filter(fn (array $row) => $row['bomId'] !== '')
            ->unique('bomId')
            ->values()
            ->all();
    }

    /**
     * Trae en UNA consulta a AX todas las L.Mat CRUDO vigentes de los items del lote
     * y las indexa por item|talla|salón.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     */
    public function precargar($registros): void
    {
        $this->bomCrudoCache = [];

        $itemIds = collect($registros)
            ->map(fn ($r) => trim((string) ($r->ItemId ?? '')))
            ->filter()
            ->unique()
            ->map(fn (string $id) => $id.'-1')
            ->values()
            ->all();

        if ($itemIds === []) {
            return;
        }

        try {
            $filas = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->distinct()
                ->select('BV.ITEMID', 'BT.TWINVENTSIZEID', 'BT.TWSALON', 'BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where('BT.ITEMGROUPID', 'CRUDO')
                ->where('BT.Vigente', 1)
                ->whereIn('BV.ITEMID', $itemIds)
                ->orderBy('BT.BOMID')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('LiberarOrdenes: no se pudo precargar BOMTABLE', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($filas as $fila) {
            $bomId = trim((string) ($fila->bomId ?? ''));
            if ($bomId === '') {
                continue;
            }

            $clave = self::claveBomCrudo(
                self::itemIdSinSufijo((string) ($fila->ITEMID ?? '')),
                (string) ($fila->TWINVENTSIZEID ?? ''),
                self::normalizarSalon((string) ($fila->TWSALON ?? ''))
            );

            if (isset($this->bomCrudoCache[$clave][$bomId])) {
                continue;
            }

            $this->bomCrudoCache[$clave][$bomId] = [
                'bomId' => $bomId,
                'bomName' => trim((string) ($fila->bomName ?? '')),
            ];
        }

        foreach ($this->bomCrudoCache as $clave => $opciones) {
            $this->bomCrudoCache[$clave] = array_values($opciones);
        }
    }

    /**
     * Salones canónicos en los que cada L.Mat es válido, indexados por item|talla|bom.
     *
     * @param  array<int, string>  $bomIds
     * @return array<string, array<int, string>>
     */
    public function salonesValidosPorBomIds(array $bomIds): array
    {
        if ($bomIds === []) {
            return [];
        }

        $filas = DB::connection('sqlsrv_ti')
            ->table('BOMTABLE as BT')
            ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
            ->distinct()
            ->select('BT.BOMID', 'BT.TWINVENTSIZEID', 'BT.TWSALON', 'BV.ITEMID')
            ->whereIn('BT.BOMID', $bomIds)
            ->where('BT.ITEMGROUPID', 'CRUDO')
            ->where('BT.Vigente', 1)
            ->get();

        $salonesValidos = [];
        foreach ($filas as $fila) {
            $clave = implode('|', [
                self::itemIdSinSufijo((string) $fila->ITEMID),
                trim((string) $fila->TWINVENTSIZEID),
                trim((string) $fila->BOMID),
            ]);
            $salonesValidos[$clave][] = self::normalizarSalon((string) $fila->TWSALON);
        }

        return $salonesValidos;
    }

    public function normalizarSalonBomCrudo(ReqProgramaTejido $registro): string
    {
        return self::normalizarSalon((string) ($registro->SalonTejidoId ?? ''));
    }

    /**
     * Traduce cualquier variante (del programa o de AX) al salón canónico.
     */
    public static function normalizarSalon(string $salon): string
    {
        return TelarSalonResolver::normalizeSalon($salon);
    }

    /** Clave item|talla|salón con la que se indexan las L.Mat CRUDO del lote. */
    public static function claveBomCrudo(string $itemId, string $inventSizeId, string $salon): string
    {
        return mb_strtoupper(trim($itemId)).'|'.mb_strtoupper(trim($inventSizeId)).'|'.mb_strtoupper(trim($salon));
    }

    /**
     * Las L.Mat 'ESTAND ...' son genéricas: AX las liga a cientos de items a la vez
     * (ESTAND JS 3060-3524 cuelga de 1025 items en BOMVERSION), así que "pertenece
     * al item" no las distingue de la L.Mat propia del producto.
     */
    public static function esBomEstandar(string $bomId): bool
    {
        return str_starts_with(mb_strtoupper(trim($bomId)), 'ESTAND');
    }

    /**
     * L.Mat que se puede poner sola en el renglón: sólo cuando queda exactamente una
     * candidata NO estándar. Las ESTAND siguen en la lista para elegirlas a mano.
     *
     * @param  array<int, array{bomId: string, bomName: string}>  $opciones
     * @return array{bomId: string, bomName: string}|null
     */
    public static function bomAutoAsignable(array $opciones): ?array
    {
        $candidatas = array_values(array_filter(
            $opciones,
            static fn (array $o): bool => ! self::esBomEstandar((string) ($o['bomId'] ?? ''))
        ));

        return count($candidatas) === 1 ? $candidatas[0] : null;
    }

    /**
     * Quita únicamente el sufijo '-1' de AX. Un str_replace('-1', '') global
     * destrozaba items que llevan '-1' en medio (ej. '3-100-1' → '300').
     */
    public static function itemIdSinSufijo(string $itemId): string
    {
        return preg_replace('/-1$/', '', trim($itemId)) ?? trim($itemId);
    }
}
