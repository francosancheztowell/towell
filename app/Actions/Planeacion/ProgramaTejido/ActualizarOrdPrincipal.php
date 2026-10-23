<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\OrdCompartida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Escribe OrdPrincipal (= NoProduccion del líder, texto: en Muestras "M3175"; hasta oct-2026
 * era su ItemId) en todo el grupo de una OrdCompartida, en ReqProgramaTejido y en CatCodificados (movido de
 * VincularTejido::actualizarOrdPrincipalPorOrdCompartida). No abre transacción: corre dentro
 * de la del llamador (vincular, dividir, duplicar, balancear, mover desarrollador) y nunca
 * lanza; un fallo se registra en log y no tumba la operación principal.
 */
final class ActualizarOrdPrincipal
{
    /** @var array<string, array<int, string>> columnas por tabla (INFORMATION_SCHEMA es costoso en SQL Server) */
    private static array $columnas = [];

    public static function ejecutar(mixed $ordCompartida): void
    {
        $ordCompartida = OrdCompartida::normalizar($ordCompartida);
        if ($ordCompartida === null) {
            return;
        }

        try {
            $lider = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->where('OrdCompartidaLider', 1)
                ->first(['Id', 'NoProduccion']);

            // Un líder sin NoProduccion no deja qué escribir.
            $principal = OrdCompartida::obtenerOrdCompartidaDesdeRegistro($lider);
            if ($principal === null) {
                return;
            }

            ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->update([
                    'OrdPrincipal' => $principal,
                    'UpdatedAt' => now(),
                ]);

            $registros = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->get(['Id', 'NoProduccion', 'NoTelarId', 'OrdCompartidaLider']);

            self::sincronizarCatCodificados($registros, $principal, $ordCompartida);
        } catch (\Throwable $e) {
            Log::warning('Error al actualizar OrdPrincipal por OrdCompartida', [
                'ord_compartida' => $ordCompartida,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Query base CatCodificados por orden de tejido + telar (columnas dinámicas en BD).
     *
     * @param  bool  $omitirFiltroTelarSiVacio  Si true (p. ej. OrdPrincipal), no filtra por telar cuando viene vacío.
     * @return array{0: Builder<CatCodificados>, 1: string, 2: array<int, string>}
     */
    public static function queryCatCodificadosOrdenTelar(string $noProduccion, string $noTelarId, bool $omitirFiltroTelarSiVacio = false): array
    {
        $table = (new CatCodificados)->getTable();
        $columns = self::$columnas[$table] ??= Schema::getColumnListing($table);

        $query = CatCodificados::query();
        $columnaOrden = in_array('OrdenTejido', $columns, true) ? 'OrdenTejido'
            : (in_array('NumOrden', $columns, true) ? 'NumOrden' : 'NoProduccion');
        $query->where($columnaOrden, $noProduccion);

        if (! $omitirFiltroTelarSiVacio || $noTelarId !== '') {
            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }
        }

        return [$query, $table, $columns];
    }

    /**
     * La fila de CatCodificados de cada miembro (su orden en su telar; sin telar, la primera de
     * la orden) recibe OrdPrincipal, OrdCompartida y el líder. Una lectura y dos UPDATE por grupo:
     * antes era un SELECT por miembro, que no se veía porque casi siempre salía antes por ItemId.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     */
    private static function sincronizarCatCodificados(Collection $registros, string $principal, string $ordCompartida): void
    {
        $orden = fn (Model $m, string $columna) => trim((string) $m->getAttribute($columna));
        $miembros = $registros->filter(fn (ReqProgramaTejido $r) => $orden($r, 'NoProduccion') !== '');
        $filas = CatCodificados::query()
            ->ordenesTejido($miembros->map(fn (ReqProgramaTejido $r) => $orden($r, 'NoProduccion'))->unique()->values()->all())
            ->orderBy('Id')
            ->get(['Id', 'OrdenTejido', 'TelarId']);

        $ids = [];
        $lideres = [];
        foreach ($miembros as $r) {
            $telar = $orden($r, 'NoTelarId');
            $fila = $filas->first(fn (CatCodificados $c) => $orden($c, 'OrdenTejido') === $orden($r, 'NoProduccion')
                && ($telar === '' || (int) $c->getAttribute('TelarId') === (int) $telar));
            if ($fila !== null) {
                $ids[] = $fila->getKey();
                if (in_array($r->getAttribute('OrdCompartidaLider'), [1, true, '1'], true)) {
                    $lideres[] = $fila->getKey();
                }
            }
        }

        $tabla = (new CatCodificados)->getTable();
        if ($ids !== []) {
            DB::table($tabla)->whereIn('Id', $ids)->update(['OrdPrincipal' => $principal, 'OrdCompartida' => $ordCompartida, 'OrdCompartidaLider' => null]);
        }
        if ($lideres !== []) {
            DB::table($tabla)->whereIn('Id', $lideres)->update(['OrdCompartidaLider' => 1]);
        }
    }
}
