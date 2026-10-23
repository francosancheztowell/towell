<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sugerencia y validación de flogs al liberar.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → misma salida:
 * catálogo TwArticulosFelpas (quién decide), TwFlogsItemLine+TwFlogsTable
 * (cuál flog vigente, desempate por número final), y apply de cabecera/cliente AX.
 */
final class LiberarFlogSugeridoService
{
    /** Estados AX que se consideran vigentes para sugerir o aplicar un flog. */
    public const ESTADOS_VIGENTES = [3, 4, 5, 21];

    /**
     * TwFlogsItemLine.ESTADOLINEA abierta (1 facturada, 2 cancelada). Un flog vigente necesita
     * al menos una línea abierta. Las fechas de cancelación no sirven de filtro: los flogs RS de
     * stock las tienen vencidas y se siguen usando (medido 21-oct-2026 contra 72 órdenes).
     */
    public const ESTADO_LINEA_ABIERTA = 0;

    /** Clave normalizada item+talla usada para cruzar contra el catálogo TwArticulosFelpas. */
    public static function claveItemTalla(?string $itemId, ?string $inventSizeId): string
    {
        return mb_strtoupper(trim((string) $itemId)).'|'.mb_strtoupper(trim((string) $inventSizeId));
    }

    /**
     * Número final del IDFLOG (CE-100 → 100). El flog más reciente es el de mayor
     * número, no el mayor alfabéticamente (CE-100 > CE-99).
     */
    public static function numeroFinalFlog(string $idFlog): int
    {
        return preg_match('/(\d+)$/', $idFlog, $m) ? (int) $m[1] : 0;
    }

    /**
     * Artículos del catálogo de flogs presentes en el lote, en UNA consulta a AX (no una
     * por renglón). Pese al nombre, TwArticulosFelpas no es sólo felpa: es el catálogo de
     * combinaciones item+talla de cualquier tipo de artículo donde el usuario decide si la
     * orden lleva flog. Sólo tiene ITEMID/INVENTSIZEID/ITEMNAME: no aporta el flog en sí.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     * @return array<string, true> claves item|talla presentes en la tabla
     */
    public function clavesArticulosConFlog($registros): array
    {
        $itemIds = collect($registros)
            ->map(fn ($r) => trim((string) ($r->ItemId ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        // AX no es consistente con el sufijo: TwArticulosFelpas guarda '6598-1' y
        // TwFlogsItemLine el mismo item como '6598'. Se consultan ambas formas y la
        // clave se normaliza sin sufijo; si no, el catálogo nunca empata.
        $buscar = array_values(array_unique(array_merge(
            $itemIds,
            array_map(static fn (string $id): string => $id.'-1', $itemIds)
        )));

        try {
            $filas = DB::connection('sqlsrv_ti')
                ->table('TwArticulosFelpas')
                ->select('ITEMID', 'INVENTSIZEID')
                ->whereIn('ITEMID', $buscar)
                ->get();
        } catch (\Throwable $e) {
            // Sin AX no se sabe qué renglones piden decisión. Se prefiere no ofrecerla (todos
            // conservan su AsignarFlogs) a tumbar la pantalla completa.
            Log::warning('LiberarOrdenes: no se pudo consultar TwArticulosFelpas', ['error' => $e->getMessage()]);

            return [];
        }

        $claves = [];
        foreach ($filas as $fila) {
            $claves[self::claveItemTalla(LiberarBomCrudoResolver::itemIdSinSufijo((string) ($fila->ITEMID ?? '')), $fila->INVENTSIZEID ?? '')] = true;
        }

        return $claves;
    }

    /**
     * Todos los flogs vigentes de AX (estado vigente + al menos una línea abierta): la lista
     * de la celda Flog y la validación en pantalla. Misma regla que {@see self::aplicarDatosFlog()}.
     *
     * @return list<string>
     */
    public function flogsVigentes(): array
    {
        try {
            return $this->cabecerasVigentes()
                ->orderBy('ft.IDFLOG')
                ->pluck('ft.IDFLOG')
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            // Sin lista la pantalla no valida en cliente; el servidor valida al liberar.
            Log::warning('LiberarOrdenes: no se pudieron cargar los flogs vigentes', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** TwFlogsTable (alias ft) con la regla de vigencia: estado vigente y al menos una línea abierta. */
    public function cabecerasVigentes(): Builder
    {
        return DB::connection('sqlsrv_ti')
            ->table('dbo.TwFlogsTable as ft')
            ->whereIn('ft.ESTADOFLOG', self::ESTADOS_VIGENTES)
            ->whereExists(fn ($q) => $q->from('dbo.TwFlogsItemLine as fil')
                ->whereColumn('fil.IDFLOG', 'ft.IDFLOG')
                ->where('fil.ESTADOLINEA', self::ESTADO_LINEA_ABIERTA));
    }

    /**
     * Flogs vigentes por item|talla para todo el lote, en UNA consulta a AX: sólo los que
     * tienen ABIERTA la línea de ese item+talla. Ordenados del más reciente al más antiguo
     * (mayor número final, no alfabético: CE-100 > CE-99); el primero es el sugerido.
     *
     * Ojo con el sufijo: TwFlogsItemLine guarda el item SIN '-1' (al revés que
     * TwArticulosFelpas), y la columna trae relleno, de ahí el LTRIM/RTRIM.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     * @return array<string, list<string>> clave item|talla => IDFLOGs
     */
    public function flogsDelLote($registros): array
    {
        $itemIds = collect($registros)
            ->map(fn ($r) => trim((string) ($r->ItemId ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        try {
            $filas = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('fil.ITEMID', 'fil.INVENTSIZEID', 'ft.IDFLOG')
                ->whereIn(DB::raw('LTRIM(RTRIM(fil.ITEMID))'), $itemIds)
                ->whereIn('ft.ESTADOFLOG', self::ESTADOS_VIGENTES)
                ->where('fil.ESTADOLINEA', self::ESTADO_LINEA_ABIERTA)
                ->get();
        } catch (\Throwable $e) {
            // Sin AX el renglón sale con el flog vacío y el usuario lo captura a mano.
            Log::warning('LiberarOrdenes: no se pudieron precargar los flogs sugeridos', ['error' => $e->getMessage()]);

            return [];
        }

        $porClave = [];
        foreach ($filas as $fila) {
            $idFlog = trim((string) ($fila->IDFLOG ?? ''));
            if ($idFlog !== '') {
                $porClave[self::claveItemTalla((string) ($fila->ITEMID ?? ''), (string) ($fila->INVENTSIZEID ?? ''))][$idFlog] = true;
            }
        }

        return array_map(function (array $flogs) {
            $ids = array_map('strval', array_keys($flogs));
            usort($ids, fn (string $a, string $b) => self::numeroFinalFlog($b) <=> self::numeroFinalFlog($a));

            return $ids;
        }, $porClave);
    }

    /**
     * Cambio de flog desde la grilla. El flog debe existir y estar vigente en AX: capturado
     * a mano se puede escribir cualquier cosa y quedaría una orden con un flog inexistente.
     * Si existe, arrastra lo que el flog define en AX (descripción, cliente, categoría) y el
     * TipoPedido derivado del prefijo, igual que Duplicar.
     *
     * @return string|null mensaje de error, o null si el flog es válido
     */
    public function aplicarDatosFlog(ReqProgramaTejido $registro, string $flogsId): ?string
    {
        try {
            $cabecera = $this->cabecerasVigentes()
                ->select('ft.NAMEPROYECT', 'ft.CUSTNAME')
                ->where('ft.IDFLOG', $flogsId)
                ->first();

            if (! $cabecera) {
                return 'El flog "'.$flogsId.'" no existe o no está vigente en AX.';
            }

            $registro->asignarFlog($flogsId);

            $registro->NombreProyecto = trim((string) ($cabecera->NAMEPROYECT ?? '')) ?: $registro->NombreProyecto;
            $registro->CustName = trim((string) ($cabecera->CUSTNAME ?? '')) ?: $registro->CustName;

            $cliente = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsCustomer')
                ->select('CustName', 'CategoriaCalidad')
                ->where('IdFlog', $flogsId)
                ->first();

            if ($cliente) {
                $registro->CustName = trim((string) ($cliente->CustName ?? '')) ?: $registro->CustName;
                $registro->CategoriaCalidad = trim((string) ($cliente->CategoriaCalidad ?? '')) ?: $registro->CategoriaCalidad;
            }
        } catch (\Throwable $e) {
            // Un timeout de AX no puede ser MÁS permisivo que un AX que responde "no existe":
            // antes esta rama aceptaba el flog capturado y dejaba la orden con un flog que
            // podía no existir. Sin poder validar, se rechaza igual que un flog inválido.
            Log::warning('LiberarOrdenes: no se pudieron leer los datos del flog en AX', [
                'flogsId' => $flogsId,
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo validar el flog "'.$flogsId.'" contra AX. Intenta de nuevo.';
        }

        return null;
    }

    /**
     * Flog vigente para un item + talla, para precargar la celda de los renglones del
     * catálogo (check "Asignar flogs"). Mismo criterio que Codificación:
     * TwFlogsItemLine + TwFlogsTable, estados vigentes, el más reciente.
     *
     * @return array{flogsId: string, nombreProyecto: string}|null
     */
    public function sugerir(string $itemId, string $inventSizeId): ?array
    {
        $flog = DB::connection('sqlsrv_ti')
            ->table('dbo.TwFlogsItemLine as fil')
            ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
            ->select('ft.IDFLOG', 'ft.NAMEPROYECT')
            ->whereRaw('LTRIM(RTRIM(fil.ITEMID)) = ?', [$itemId])
            ->whereRaw('LTRIM(RTRIM(fil.INVENTSIZEID)) = ?', [$inventSizeId])
            ->whereIn('ft.ESTADOFLOG', self::ESTADOS_VIGENTES)
            ->where('fil.ESTADOLINEA', self::ESTADO_LINEA_ABIERTA)
            ->get()
            ->sortByDesc(function ($fila) {
                return self::numeroFinalFlog(trim((string) ($fila->IDFLOG ?? '')));
            })
            ->first();

        if (! $flog) {
            return null;
        }

        return [
            'flogsId' => trim((string) ($flog->IDFLOG ?? '')),
            'nombreProyecto' => trim((string) ($flog->NAMEPROYECT ?? '')),
        ];
    }
}
