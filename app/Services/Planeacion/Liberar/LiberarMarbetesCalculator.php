<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;

/**
 * Cadena de marbetes al liberar / preview: repeticiones → mts/pzas × rollo →
 * TotalRollos / TotalPzas / no. marbetes, con ajuste FEL.
 *
 * Distinto de {@see \App\Services\Planeacion\SaldoMarbeteCodificacionService}:
 * ese servicio resta ProduccionMarbetes a TotalRollos ya persistido en CatCodificados.
 * Aquí se deriva la cadena Excel *antes* de liberar. No unificar las fórmulas.
 *
 * SaldoMarbete usa TECHO (ceil), no REDONDEAR: el último rollo parcial también lleva marbete.
 */
final class LiberarMarbetesCalculator
{
    /** Peso estándar rodillo cuando TamanoClave / producto son Felpa (FELPA…). */
    public const PESO_ROLLO_KG_FELPA = 90.0;

    /** Peso estándar rodillo en Karl Mayer. Valor inicial: se edita en la grilla. */
    public const PESO_ROLLO_KG_KARL_MAYER = 27.5;

    /** Fallback cuando no hay catálogo ni peso de felpa / Karl Mayer. */
    public const PESO_ROLLO_FALLBACK_KG = 41.5;

    /** Catálogo de pesos de rollo por InventSizeId; se carga una vez por instancia. */
    private ?array $pesosRolloCache = null;

    /**
     * Cadena completa de marbetes de un registro: repeticiones → mts/pzas x rollo →
     * no. marbetes → total rollos/pzas, con el ajuste FEL aplicado al final.
     *
     * El ajuste FEL depende del tamaño/producto del registro (InventSizeId con FEL o
     * FELPA en TamanoClave\NombreProducto), NO del peso: si el registro es FEL sigue
     * siendo FEL aunque se baje el peso de rollo o el tamaño resultante.
     *
     * @param  float|null  $pesoRolloOverride  peso a usar en lugar del de catálogo (para simular)
     * @param  float|null  $repeticionesOverride  repeticiones capturadas a mano; sustituyen a la fórmula y arrastran el resto de la cadena
     * @return array{pesoRollo: float, repeticiones: int|null, saldoMarbete: int, mtsRollo: float|null, pzasRollo: float|null, totalRollos: float|null, totalPzas: float|null, esFel: bool}
     */
    public function calcular(ReqProgramaTejido $registro, ?float $pesoRolloOverride = null, ?float $repeticionesOverride = null): array
    {
        $pCrudo = $registro->PesoCrudo ?? null;
        $tiras = $registro->NoTiras ?? null;
        $pesoRollo = ($pesoRolloOverride !== null && $pesoRolloOverride > 0)
            ? $pesoRolloOverride
            : ($this->obtenerPesoRollo($registro) ?? self::PESO_ROLLO_FALLBACK_KG);

        $repeticiones = null;
        if ($repeticionesOverride !== null && $repeticionesOverride > 0) {
            // Mismo criterio que al liberar: lo que el usuario ve/edita manda sobre la fórmula.
            $repeticiones = (int) $repeticionesOverride;
        } elseif ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
            $repeticiones = $this->repeticionesDesdePesoRollo($pesoRollo, $pCrudo, $tiras);
        }

        $saldoMarbeteValor = $this->saldoMarbeteDesdeFormula($this->basePedido($registro), $tiras, $repeticiones);

        // MtsRollo: RECALCULAR SIEMPRE desde Repeticiones (el valor guardado puede estar
        // desfasado si se creó/importó con otro peso de rollo). Solo se conserva el valor
        // almacenado como último recurso cuando no es posible calcular (sin largo o reps).
        // Se mantiene como decimal sin redondear.
        $mtsRollo = null;
        $largo = $registro->LargoCrudo ?? null;
        if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
            $largoNum = is_numeric($largo) ? (float) $largo : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);
            if ($largoNum > 0 && $repeticiones > 0) {
                // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                $mtsRollo = (float) (($largoNum * $repeticiones) / 100);
            }
        }
        if ($mtsRollo === null && isset($registro->MtsRollo) && is_numeric($registro->MtsRollo)) {
            $mtsRollo = (float) $registro->MtsRollo;
        }

        // PzasRollo = Repeticiones × NoTiras SIEMPRE (piezas por rollo por definición).
        // NO se hereda el valor almacenado: queda desfasado si cambió el peso crudo y corrompe TotalRollos/TotalPzas.
        $pzasRollo = $this->pzasRolloDesdeRepeticiones($repeticiones, $tiras);

        $this->aplicarAjusteFelTamanho($registro->InventSizeId ?? null, $saldoMarbeteValor, $mtsRollo, $pzasRollo, $registro);

        // TotalRollos = ceil(TotalPedido / PzasRollo); TotalPzas = PzasRollo × TotalRollos.
        ['totalRollos' => $totalRollos, 'totalPzas' => $totalPzas] =
            $this->derivarTotalRollosTotalPzas($pzasRollo, $this->basePedido($registro));

        if ($totalRollos === null) {
            // Fallbacks solo si no se pudo derivar desde PzasRollo/SaldoPedido.
            if (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos) && $registro->TotalRollos > 0) {
                $totalRollos = (float) ceil((float) $registro->TotalRollos);
            } elseif ($saldoMarbeteValor > 0) {
                $totalRollos = (float) ceil($saldoMarbeteValor);
            }
            if ($totalRollos !== null && $pzasRollo !== null && is_numeric($pzasRollo)) {
                $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
            }
        }

        return [
            'pesoRollo' => (float) $pesoRollo,
            'repeticiones' => $repeticiones,
            // No. marbetes = TotalRollos (pendientes antes de liberar = todos).
            'saldoMarbete' => $totalRollos !== null ? (int) $totalRollos : 0,
            'mtsRollo' => $mtsRollo,
            'pzasRollo' => $pzasRollo,
            'totalRollos' => $totalRollos,
            'totalPzas' => $totalPzas,
            'esFel' => $this->debeAplicarAjusteFormatoFelRollo($registro->InventSizeId ?? null, $registro),
        ];
    }

    /**
     * =TRUNCAR((peso_rollo / peso_crudo) / tiras * 1000) en Excel.
     */
    public function repeticionesDesdePesoRollo(float $pesoRollo, mixed $pCrudo, mixed $tiras): ?int
    {
        if ($pCrudo === null || $tiras === null
            || ! is_numeric($pCrudo) || ! is_numeric($tiras)
            || (float) $pCrudo <= 0.0 || (float) $tiras <= 0.0) {
            return null;
        }

        $v = (($pesoRollo / (float) $pCrudo) / (float) $tiras) * 1000.0;

        // TRUNCAR hacia cero; para cantidades no negativas coincide con (int)
        return (int) $v;
    }

    /**
     * =SI(ESERROR((cantidad a producir / tiras) / repeticiones), 0, MULTIPLO.SUPERIOR(..., 1)) — entero.
     *
     * TECHO, no REDONDEAR: es la misma división que TotalRollos —(Pedido/Tiras)/Repeticiones
     * ≡ Pedido/PzasRollo— y el último rollo sale parcial pero igual lleva marbete. Con
     * REDONDEAR quedaba 1 abajo de TotalRollos siempre que el decimal fuera menor a 0.5.
     * Misma fórmula en ReqProgramaTejidoObserver y SaldoMarbeteCodificacionService: los tres
     * tienen que techar o se pisan entre sí en el siguiente save().
     */
    public function saldoMarbeteDesdeFormula(mixed $cantidadProducir, mixed $tiras, mixed $repeticiones): int
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

        return (int) ceil($raw);
    }

    /**
     * PzasRollo = round(Repeticiones × NoTiras). Piezas por rollo por definición.
     * Devuelve null si no hay datos válidos. NO considera valores almacenados ni del request:
     * un PzasRollo previo queda desfasado si cambió el peso crudo y corrompe TotalRollos/TotalPzas.
     */
    public function pzasRolloDesdeRepeticiones(mixed $repeticiones, mixed $tiras): ?float
    {
        if ($repeticiones === null || $tiras === null
            || ! is_numeric($repeticiones) || ! is_numeric($tiras)
            || (float) $repeticiones <= 0.0 || (float) $tiras <= 0.0) {
            return null;
        }

        return round((float) $repeticiones * (float) $tiras, 0);
    }

    /**
     * Base de las fórmulas de producción: el PEDIDO completo, no el saldo pendiente.
     * SaldoPedido baja conforme se produce, así que usarlo hacía que rollos/marbetes/piezas
     * cambiaran solos y dejaran de cuadrar con CatCodificados (que se recalcula sobre TotalPedido
     * en ReqProgramaTejidoObserver). Fallback a SaldoPedido/Produccion solo en órdenes viejas sin TotalPedido.
     */
    public function basePedido(ReqProgramaTejido $registro): ?float
    {
        foreach ([$registro->TotalPedido ?? null, $registro->SaldoPedido ?? null, $registro->Produccion ?? null] as $valor) {
            if ($valor !== null && is_numeric($valor) && (float) $valor > 0.0) {
                return (float) $valor;
            }
        }

        return null;
    }

    /**
     * Deriva TotalRollos y TotalPzas a partir del PzasRollo ya resuelto (post-ajuste FEL).
     * TotalRollos = ceil(TotalPedido / PzasRollo), salvo override del usuario (techo).
     * TotalPzas   = round(PzasRollo × TotalRollos).
     * Cualquier valor previo de TotalRollos/TotalPzas se ignora para que nunca quede desfasado.
     *
     * @return array{totalRollos: float|null, totalPzas: float|null}
     */
    public function derivarTotalRollosTotalPzas(?float $pzasRollo, mixed $saldoPedido, mixed $totalRollosOverride = null): array
    {
        $totalRollos = null;

        if ($totalRollosOverride !== null && $totalRollosOverride !== ''
            && is_numeric($totalRollosOverride) && (float) $totalRollosOverride > 0.0) {
            $totalRollos = (float) ceil((float) $totalRollosOverride);
        } elseif ($pzasRollo !== null && (float) $pzasRollo > 0.0
            && $saldoPedido !== null && is_numeric($saldoPedido) && (float) $saldoPedido > 0.0) {
            $totalRollos = (float) ceil((float) $saldoPedido / (float) $pzasRollo);
        }

        $totalPzas = null;
        if ($totalRollos !== null && $pzasRollo !== null && is_numeric($pzasRollo)) {
            $totalPzas = round((float) $pzasRollo * (float) $totalRollos, 0);
        }

        return ['totalRollos' => $totalRollos, 'totalPzas' => $totalPzas];
    }

    /**
     * Obtiene el peso del rollo desde la tabla ReqPesosRolloTejido.
     * Karl Mayer: {@see self::PESO_ROLLO_KG_KARL_MAYER} kg, sin importar felpa ni catálogo.
     * Felpa (TamanoClave / nombre con FELPA): siempre {@see self::PESO_ROLLO_KG_FELPA} kg.
     * Orden de búsqueda: 1) InventSizeId exacto del registro; 2) FEL (si aplica); 3) DEF.
     * Si no encuentra nada, retorna null (para usar {@see self::PESO_ROLLO_FALLBACK_KG} como default).
     *
     * Los tres son solo el valor inicial: lo que el usuario capture en la columna Peso x Rollo
     * gana en index() y al liberar, igual que para cualquier otro tamaño.
     */
    public function obtenerPesoRollo(ReqProgramaTejido $registro): ?float
    {
        if ($this->esKarlMayer($registro)) {
            return self::PESO_ROLLO_KG_KARL_MAYER;
        }

        if ($this->esTamanoFelpa($registro)) {
            return self::PESO_ROLLO_KG_FELPA;
        }
        try {
            $inventSizeId = trim((string) ($registro->InventSizeId ?? ''));

            // 1) Primero buscar por coincidencia exacta de InventSizeId
            if (! empty($inventSizeId)) {
                $pesoRollo = $this->obtenerPesoRolloPorInventSizeId($inventSizeId);

                if ($pesoRollo !== null) {
                    return $pesoRollo;
                }
            }

            // 2) Si el registro tiene FEL en InventSizeId, buscar por FEL
            $buscarFel = ! empty($inventSizeId) && (stripos($inventSizeId, 'FEL') !== false);

            if ($buscarFel) {
                $pesoRollo = $this->obtenerPesoRolloPorInventSizeId('FEL');

                if ($pesoRollo !== null) {
                    return $pesoRollo;
                }
            }

            // 3) Buscar por DEF (default)
            $pesoRollo = $this->obtenerPesoRolloPorInventSizeId('DEF');

            if ($pesoRollo !== null) {
                return $pesoRollo;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Tamaños cuyo InventSizeId contiene "FEL": duplicar no. marbetes (SaldoMarbete) y usar la mitad en MtsRollo y PzasRollo (negocio / Excel).
     */
    public function esInventSizeFel(?string $inventSizeId): bool
    {
        $s = trim((string) ($inventSizeId ?? ''));

        return $s !== '' && stripos($s, 'FEL') !== false;
    }

    /** TamanoClave tipo FELPA… o nombre de producto FELPA: peso rodillo fijo {@see self::PESO_ROLLO_KG_FELPA} y mismo ajuste m/marbetes que FEL en rollo. */
    public function esTamanoFelpa(ReqProgramaTejido $registro): bool
    {
        $tk = trim((string) ($registro->TamanoClave ?? ''));
        if ($tk !== '' && stripos($tk, 'FELPA') !== false) {
            return true;
        }
        $nombre = trim((string) ($registro->NombreProducto ?? ''));

        return $nombre !== '' && stripos($nombre, 'FELPA') !== false;
    }

    /**
     * FEL en InventSizeId o Felpa por tamano/nombre: duplicar marbetes y mitad en Mts/Pzas x rollo.
     * En Karl Mayer no aplica aunque el tamaño diga FELPA.
     */
    public function debeAplicarAjusteFormatoFelRollo(?string $inventSizeId, ?ReqProgramaTejido $registro = null): bool
    {
        if ($this->esKarlMayer($registro)) {
            return false;
        }

        if ($registro !== null && $this->esTamanoFelpa($registro)) {
            return true;
        }

        return $this->esInventSizeFel($inventSizeId);
    }

    /**
     * @param  int  $saldoMarbeteValor  por referencia: resultado de saldoMarbeteDesdeFormula
     */
    public function aplicarAjusteFelSaldoMarbete(?string $inventSizeId, int &$saldoMarbeteValor, ?ReqProgramaTejido $registro = null): void
    {
        if (! $this->debeAplicarAjusteFormatoFelRollo($inventSizeId, $registro)) {
            return;
        }
        $saldoMarbeteValor = (int) round($saldoMarbeteValor * 2);
    }

    /**
     * @param  float|null  $mtsRollo  por referencia
     * @param  float|null  $pzasRollo  por referencia
     */
    public function aplicarAjusteFelMtsYpzas(?string $inventSizeId, ?float &$mtsRollo, ?float &$pzasRollo, ?ReqProgramaTejido $registro = null): void
    {
        $this->aplicarAjusteFelMtsRollo($inventSizeId, $mtsRollo, $registro);
        $this->aplicarAjusteFelPzasRollo($inventSizeId, $pzasRollo, $registro);
    }

    /**
     * @param  float|null  $mtsRollo  por referencia
     */
    public function aplicarAjusteFelMtsRollo(?string $inventSizeId, ?float &$mtsRollo, ?ReqProgramaTejido $registro = null): void
    {
        if (! $this->debeAplicarAjusteFormatoFelRollo($inventSizeId, $registro)) {
            return;
        }
        if ($mtsRollo !== null && is_numeric($mtsRollo)) {
            $mtsRollo = (float) $mtsRollo / 2.0;
        }
    }

    /**
     * @param  float|null  $pzasRollo  por referencia
     */
    public function aplicarAjusteFelPzasRollo(?string $inventSizeId, ?float &$pzasRollo, ?ReqProgramaTejido $registro = null): void
    {
        if (! $this->debeAplicarAjusteFormatoFelRollo($inventSizeId, $registro)) {
            return;
        }
        if ($pzasRollo !== null && is_numeric($pzasRollo)) {
            $pzasRollo = (float) round((float) $pzasRollo / 2.0, 0);
        }
    }

    /**
     * Ajuste FEL/Felpa (carga index): marbetes x2, MtsRollo y PzasRollo ÷2.
     *
     * @param  int  $saldoMarbeteValor  por referencia
     * @param  float|null  $mtsRollo  por referencia
     * @param  float|null  $pzasRollo  por referencia
     */
    public function aplicarAjusteFelTamanho(?string $inventSizeId, int &$saldoMarbeteValor, ?float &$mtsRollo, ?float &$pzasRollo, ?ReqProgramaTejido $registro = null): void
    {
        $this->aplicarAjusteFelSaldoMarbete($inventSizeId, $saldoMarbeteValor, $registro);
        $this->aplicarAjusteFelMtsYpzas($inventSizeId, $mtsRollo, $pzasRollo, $registro);
    }

    /**
     * Karl Mayer no se rige por las reglas de felpa: ni el peso fijo de 90 kg ni el ×2 / ÷2.
     * Se resuelve por salón capturado y, si viene vacío, por número de telar (401-402).
     */
    private function esKarlMayer(?ReqProgramaTejido $registro): bool
    {
        return $registro !== null && TelarSalonResolver::esKarlMayer(
            $registro->SalonTejidoId ?? null,
            $registro->NoTelarId ?? null
        );
    }

    private function obtenerPesoRolloPorInventSizeId(string $inventSizeId): ?float
    {
        return $this->catalogoPesosRollo()[mb_strtoupper(trim($inventSizeId))] ?? null;
    }

    /**
     * Catálogo completo de pesos de rollo indexado por InventSizeId, cargado UNA vez por
     * instancia. Antes se consultaba por renglón y hasta 3 veces cada uno (talla exacta → FEL →
     * DEF): con 20-60 renglones en pantalla eran ~150 queries para leer una tabla chica.
     *
     * Se conserva el desempate original (FechaModificacion, FechaCreacion, Id descendentes):
     * gana la primera fila de cada talla en ese orden.
     *
     * @return array<string, float>
     */
    private function catalogoPesosRollo(): array
    {
        if ($this->pesosRolloCache !== null) {
            return $this->pesosRolloCache;
        }

        $this->pesosRolloCache = [];

        foreach (ReqPesosRollosTejido::whereNotNull('PesoRollo')
            ->orderByDesc('FechaModificacion')
            ->orderByDesc('FechaCreacion')
            ->orderByDesc('Id')
            ->get(['InventSizeId', 'PesoRollo']) as $fila) {
            $clave = mb_strtoupper(trim((string) ($fila->InventSizeId ?? '')));
            if ($clave === '' || isset($this->pesosRolloCache[$clave]) || $fila->PesoRollo === null) {
                continue;
            }
            $this->pesosRolloCache[$clave] = (float) $fila->PesoRollo;
        }

        return $this->pesosRolloCache;
    }
}
