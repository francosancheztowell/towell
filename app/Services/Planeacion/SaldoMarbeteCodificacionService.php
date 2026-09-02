<?php

namespace App\Services\Planeacion;

use App\Models\Planeacion\Catalogos\CatCodificados;

/**
 * No. marbetes en CatCodificados = marbetes PENDIENTES = TotalRollos − ProduccionMarbetes.
 *
 * ProduccionMarbetes lo alimenta el proceso externo conforme se imprimen marbetes.
 * Misma regla que ReqProgramaTejidoObserver::recalcularFormulasProduccion y que
 * SaldoMarbete/NoMarbete en ReqProgramaTejido (copian este valor).
 */
class SaldoMarbeteCodificacionService
{
    /**
     * Calcula NoMarbete; no persiste en BD.
     *
     * @return array{ok: bool, valor: ?int, message: ?string}
     */
    public function calcularParaCatCodificados(CatCodificados $c): array
    {
        $totalRollos = $c->TotalRollos;

        if ($totalRollos === null || ! is_numeric($totalRollos) || (float) $totalRollos <= 0.0) {
            return ['ok' => false, 'valor' => null, 'message' => 'TotalRollos debe ser mayor a cero para recalcular marbetes'.$this->referenciaCorta($c)];
        }

        $producidos = is_numeric($c->ProduccionMarbetes) ? (int) $c->ProduccionMarbetes : 0;
        $valor = max(0, (int) ceil((float) $totalRollos) - $producidos);

        return ['ok' => true, 'valor' => $valor, 'message' => null];
    }

    private function referenciaCorta(CatCodificados $c): string
    {
        $partes = array_filter([
            $c->Nombre !== null ? trim((string) $c->Nombre) : '',
            $c->ItemId !== null ? trim((string) $c->ItemId) : '',
        ], static fn (string $s): bool => $s !== '');

        $extra = $partes !== [] ? ' — '.implode(' / ', $partes) : '';

        return ' (Cat Id '.$c->Id.$extra.')';
    }
}
