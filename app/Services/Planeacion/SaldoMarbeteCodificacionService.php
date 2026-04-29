<?php

namespace App\Services\Planeacion;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;

/**
 * Replica la fórmula de Liberar Órdenes para no. marbetes (± ajuste FEL/Felpa) sobre CatCodificados.
 */
class SaldoMarbeteCodificacionService
{
    /** Misma constante que {@see LiberarOrdenesController::PESO_ROLLO_KG_FELPA}. */
    private const PESO_ROLLO_KG_FELPA = 90.0;

    /** Peso de rollo por defecto cuando no hay fila en ReqPesosRollosTejido. */
    private const PESO_ROLLO_FALLBACK_KG = 41.5;

    /**
     * Calcula NoMarbete; no persiste en BD.
     *
     * @return array{ok: bool, valor: ?int, message: ?string}
     */
    public function calcularParaCatCodificados(CatCodificados $c): array
    {
        $ref = $this->referenciaCorta($c);

        $pedido = $c->Pedido ?? $c->Cantidad;
        $tiras = $c->NoTiras;
        $pCrudo = $c->P_crudo;

        if ($pedido === null || ! is_numeric($pedido) || (float) $pedido <= 0.0) {
            return ['ok' => false, 'valor' => null, 'message' => 'Pedido o Cantidad debe ser mayor a cero para recalcular marbetes'.$ref];
        }

        if ($tiras === null || ! is_numeric($tiras) || (int) $tiras <= 0) {
            return ['ok' => false, 'valor' => null, 'message' => 'NoTiras debe ser mayor a cero'.$ref];
        }

        if ($pCrudo === null || ! is_numeric($pCrudo) || (float) $pCrudo <= 0.0) {
            return ['ok' => false, 'valor' => null, 'message' => 'P_crudo debe ser mayor a cero para calcular repeticiones'.$ref];
        }

        $pesoRollo = $this->obtenerPesoRollo($c);

        $repeticiones = $this->repeticionesDesdePesoRollo(
            $pesoRollo,
            $pCrudo,
            $tiras
        );

        if ($repeticiones === null || $repeticiones <= 0) {
            return ['ok' => false, 'valor' => null, 'message' => 'Repeticiones resultan ≤ 0; revisa peso de rollo, P_crudo y tiras'.$ref];
        }

        $saldo = $this->saldoMarbeteDesdeFormula($pedido, $tiras, $repeticiones);

        $this->aplicarAjusteFelSaldoMarbete($c, $saldo);

        if ($saldo <= 0) {
            return ['ok' => false, 'valor' => $saldo, 'message' => 'No marbetes resultó cero o negativo con los datos actuales (revisa pedido y repeticiones).'.$ref];
        }

        return ['ok' => true, 'valor' => $saldo, 'message' => null];
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

    private function esTamanoFelpa(CatCodificados $c): bool
    {
        $inv = trim((string) ($c->InventSizeId ?? ''));
        if ($inv !== '' && stripos($inv, 'FELPA') !== false) {
            return true;
        }
        $nombre = trim((string) ($c->Nombre ?? ''));
        if ($nombre !== '' && stripos($nombre, 'FELPA') !== false) {
            return true;
        }
        $clave = trim((string) ($c->ClaveModelo ?? ''));

        return $clave !== '' && stripos($clave, 'FELPA') !== false;
    }

    private function esInventSizeFel(?string $inventSizeId): bool
    {
        $s = trim((string) ($inventSizeId ?? ''));

        return $s !== '' && stripos($s, 'FEL') !== false;
    }

    private function debeAplicarAjusteFormatoFelRollo(CatCodificados $c): bool
    {
        if ($this->esTamanoFelpa($c)) {
            return true;
        }

        return $this->esInventSizeFel($c->InventSizeId ?? null);
    }

    private function obtenerPesoRollo(CatCodificados $c): float
    {
        if ($this->esTamanoFelpa($c)) {
            return self::PESO_ROLLO_KG_FELPA;
        }

        $inventSizeId = trim((string) ($c->InventSizeId ?? ''));
        if ($inventSizeId !== '') {
            $pr = $this->obtenerPesoRolloPorInventSizeId($inventSizeId);
            if ($pr !== null) {
                return $pr;
            }
        }

        if ($inventSizeId !== '' && stripos($inventSizeId, 'FEL') !== false) {
            $pr = $this->obtenerPesoRolloPorInventSizeId('FEL');
            if ($pr !== null) {
                return $pr;
            }
        }

        $pr = $this->obtenerPesoRolloPorInventSizeId('DEF');

        return $pr ?? self::PESO_ROLLO_FALLBACK_KG;
    }

    private function obtenerPesoRolloPorInventSizeId(string $inventSizeId): ?float
    {
        $pesoRollo = ReqPesosRollosTejido::where('InventSizeId', trim($inventSizeId))
            ->whereNotNull('PesoRollo')
            ->orderByDesc('FechaModificacion')
            ->orderByDesc('FechaCreacion')
            ->orderByDesc('Id')
            ->first();

        return $pesoRollo && $pesoRollo->PesoRollo !== null
            ? (float) $pesoRollo->PesoRollo
            : null;
    }

    /**
     * =TRUNCAR((peso_rollo / peso_crudo) / tiras * 1000).
     *
     * @param  mixed  $pCrudo
     * @param  mixed  $tiras
     */
    private function repeticionesDesdePesoRollo(float $pesoRollo, $pCrudo, $tiras): ?int
    {
        if ($pCrudo === null || $tiras === null
            || ! is_numeric($pCrudo) || ! is_numeric($tiras)
            || (float) $pCrudo <= 0.0 || (float) $tiras <= 0.0) {
            return null;
        }

        $v = (($pesoRollo / (float) $pCrudo) / (float) $tiras) * 1000.0;

        return (int) $v;
    }

    /**
     * =REDONDEAR((cantidad / tiras) / repeticiones, 0) entero.
     *
     * @param  mixed  $cantidadProducir
     * @param  mixed  $tiras
     * @param  mixed  $repeticiones
     */
    private function saldoMarbeteDesdeFormula($cantidadProducir, $tiras, $repeticiones): int
    {
        if ($repeticiones === null || $tiras === null || $cantidadProducir === null) {
            return 0;
        }
        if (! is_numeric($cantidadProducir) || ! is_numeric($tiras) || ! is_numeric($repeticiones)) {
            return 0;
        }
        if ((float) $tiras == 0.0 || (float) $repeticiones == 0.0) {
            return 0;
        }

        $raw = ((float) $cantidadProducir / (float) $tiras) / (float) $repeticiones;

        return (int) round($raw, 0);
    }

    /** Duplicar marbetes si InventSize tiene FEL o es Felpa (coincidente con liberar órdenes). */
    private function aplicarAjusteFelSaldoMarbete(CatCodificados $c, int &$saldoMarbeteValor): void
    {
        if (! $this->debeAplicarAjusteFormatoFelRollo($c)) {
            return;
        }
        $saldoMarbeteValor = (int) round($saldoMarbeteValor * 2);
    }
}
