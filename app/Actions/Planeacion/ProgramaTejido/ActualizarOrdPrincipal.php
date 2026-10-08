<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Escribe OrdPrincipal (= ItemId del líder) en todo el grupo de una OrdCompartida, en
 * ReqProgramaTejido y en CatCodificados (movido tal cual de
 * VincularTejido::actualizarOrdPrincipalPorOrdCompartida). No abre transacción: corre dentro
 * de la del llamador (vincular, dividir, duplicar, balancear, mover desarrollador) y nunca
 * lanza; un fallo se registra en log y no tumba la operación principal.
 */
final class ActualizarOrdPrincipal
{
    /** @var array<string, array<int, string>> columnas por tabla (INFORMATION_SCHEMA es costoso en SQL Server) */
    private static array $columnas = [];

    public static function ejecutar(int $ordCompartida): void
    {
        try {
            $lider = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->where('OrdCompartidaLider', 1)
                ->first(['Id', 'ItemId']);

            if (! $lider || empty($lider->ItemId)) {
                return;
            }

            $itemIdLider = trim((string) $lider->ItemId);
            if ($itemIdLider === '') {
                return;
            }

            ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->update([
                    'OrdPrincipal' => $itemIdLider,
                    'UpdatedAt' => now(),
                ]);

            $registros = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->get(['Id', 'NoProduccion', 'NoTelarId', 'OrdCompartidaLider']);

            foreach ($registros as $registro) {
                self::sincronizarCatCodificados($registro, $itemIdLider, $ordCompartida);
            }
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

    private static function sincronizarCatCodificados(ReqProgramaTejido $registro, string $itemIdLider, int $ordCompartida): void
    {
        $noProduccion = $registro->getAttribute('NoProduccion') ? trim((string) $registro->getAttribute('NoProduccion')) : '';
        if ($noProduccion === '') {
            return;
        }

        [$query, $table, $columns] = self::queryCatCodificadosOrdenTelar($noProduccion, trim((string) ($registro->getAttribute('NoTelarId') ?? '')), true);
        $codificado = $query->first();
        if (! $codificado) {
            return;
        }

        $esLider = in_array($registro->getAttribute('OrdCompartidaLider'), [1, true, '1'], true);
        $datos = array_intersect_key([
            'OrdPrincipal' => $itemIdLider,
            // Sincronizar OrdCompartida y OrdCompartidaLider para no perder el seguimiento.
            'OrdCompartida' => $ordCompartida,
            'OrdCompartidaLider' => $esLider ? 1 : null,
        ], array_flip($columns));

        if ($datos !== []) {
            DB::table($table)->where('Id', $codificado->Id)->update($datos);
        }
    }
}
