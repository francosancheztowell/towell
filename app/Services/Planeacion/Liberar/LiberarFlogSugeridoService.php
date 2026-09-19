<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
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
     * Flog vigente por item|talla para todo el lote, en UNA consulta a AX. Mismo criterio de
     * desempate que {@see self::sugerir()}: el flog más reciente es el de mayor
     * número final, no el mayor alfabéticamente (CE-100 > CE-99).
     *
     * Ojo con el sufijo: TwFlogsItemLine guarda el item SIN '-1' (al revés que
     * TwArticulosFelpas), y la columna trae relleno, de ahí el LTRIM/RTRIM.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     * @return array<string, string> clave item|talla => IDFLOG
     */
    public function flogsSugeridosDelLote($registros): array
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
                ->get();
        } catch (\Throwable $e) {
            // Sin AX el renglón sale con el flog vacío y el usuario lo captura a mano.
            Log::warning('LiberarOrdenes: no se pudieron precargar los flogs sugeridos', ['error' => $e->getMessage()]);

            return [];
        }

        $mejores = [];
        foreach ($filas as $fila) {
            $idFlog = trim((string) ($fila->IDFLOG ?? ''));
            if ($idFlog === '') {
                continue;
            }

            $clave = self::claveItemTalla((string) ($fila->ITEMID ?? ''), (string) ($fila->INVENTSIZEID ?? ''));
            $numero = self::numeroFinalFlog($idFlog);

            if (! isset($mejores[$clave]) || $numero > $mejores[$clave]['numero']) {
                $mejores[$clave] = ['numero' => $numero, 'idFlog' => $idFlog];
            }
        }

        return array_map(fn (array $v) => $v['idFlog'], $mejores);
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
            $cabecera = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable')
                ->select('NAMEPROYECT', 'CUSTNAME')
                ->where('IDFLOG', $flogsId)
                ->whereIn('ESTADOFLOG', self::ESTADOS_VIGENTES)
                ->first();

            if (! $cabecera) {
                return 'El flog "'.$flogsId.'" no existe o no está vigente en AX.';
            }

            UpdateHelpers::applyFlogYTipoPedido($registro, $flogsId);

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
