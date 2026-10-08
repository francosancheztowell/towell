<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarMarbetesCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTimeInterface;

/**
 * Fórmulas del programa de tejido que corren en cada save (extraídas de
 * ReqProgramaTejidoObserver): cadena de producción por rollo y reparto de horas/ritmo por
 * día. Cálculo puro: no lee ni escribe la BD; el peso de rollo del maestro lo resuelve quien
 * llama. Los consumos de hilo de cada día están en {@see ConsumosLineaDiaria}.
 *
 * Mismas reglas que {@see LiberarMarbetesCalculator} (felpa, FEL, Karl Mayer), duplicadas a
 * propósito: aquí se recalcula sobre el pedido completo, allá se arma la cadena al liberar.
 */
final class FormulasProgramaTejido
{
    private const SEGUNDOS_DIA = 86400;

    // ===== Cadena de producción: Repeticiones → PzasRollo → MtsRollo → TotalRollos → TotalPzas =====

    /** InventSizeId contiene "FEL". */
    public function esFelpaInventSize(ReqProgramaTejido $programa): bool
    {
        $inv = strtoupper(trim((string) ($programa->InventSizeId ?? '')));

        return $inv !== '' && strpos($inv, 'FEL') !== false;
    }

    /**
     * Felpa nominal: TamanoClave o NombreProducto contienen "FELPA"
     * (misma regla que LiberarOrdenesController::esTamanoFelpa).
     */
    public function esTamanoFelpa(ReqProgramaTejido $programa): bool
    {
        $tk = trim((string) ($programa->TamanoClave ?? ''));
        if ($tk !== '' && stripos($tk, 'FELPA') !== false) {
            return true;
        }
        $nombre = trim((string) ($programa->NombreProducto ?? ''));

        return $nombre !== '' && stripos($nombre, 'FELPA') !== false;
    }

    /**
     * Peso de rollo que no depende del maestro: el guardado (gana siempre), Karl Mayer o
     * felpa nominal. Null = hay que buscarlo en ReqPesosRolloTejido.
     */
    public function pesoRolloSinMaestro(ReqProgramaTejido $programa, bool $esFelpaNominal, bool $esKarlMayer): ?float
    {
        // El PesoRollo guardado gana sobre el maestro: es el que el usuario capturó al liberar y con el
        // que se calcularon las Repeticiones que quedaron en CatCodificados. Ignorarlo desalinea ambas tablas.
        $pesoGuardado = $programa->PesoRollo ?? null;
        if ($pesoGuardado !== null && is_numeric($pesoGuardado) && (float) $pesoGuardado > 0.0) {
            return (float) $pesoGuardado;
        }

        // Karl Mayer va antes que felpa: es su peso estándar, y el capturado ya ganó arriba.
        if ($esKarlMayer) {
            return LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER;
        }

        return $esFelpaNominal ? 90.0 : null;
    }

    /** Repeticiones = TRUNC((PesoRollo / PesoCrudo) / NoTiras × 1000). */
    public function repeticiones(float $pesoRollo, float $pCrudo, float $tiras): int
    {
        return (int) ((($pesoRollo / $pCrudo) / $tiras) * 1000);
    }

    /**
     * Resto de la cadena a partir de Repeticiones, con el ajuste FEL (÷2 en PzasRollo y MtsRollo).
     * TotalRollos = CEIL(TotalPedido / PzasRollo); TotalPzas = TotalRollos × PzasRollo.
     *
     * @return array{repeticiones: int, pzasRollo: float, mtsRollo: float|null, totalRollos: float|null, totalPzas: float|null}
     */
    public function cadenaProduccion(int $repeticiones, float $tiras, float $largo, float $totalPedido, bool $aplicaAjusteFel): array
    {
        $pzasRollo = (float) round($repeticiones * $tiras, 0);
        $mtsRollo = $largo > 0 ? (float) (($largo * $repeticiones) / 100) : null;

        // Ajuste FEL: ÷2 en PzasRollo y MtsRollo (igual que LiberarOrdenesController)
        if ($aplicaAjusteFel) {
            $pzasRollo = (float) round($pzasRollo / 2);
            $mtsRollo = $mtsRollo !== null ? (float) ($mtsRollo / 2) : null;
        }

        $totalRollos = ($totalPedido > 0 && $pzasRollo > 0)
            ? (float) ceil($totalPedido / $pzasRollo)
            : null;
        $totalPzas = ($totalRollos !== null && $pzasRollo > 0)
            ? (float) round($totalRollos * $pzasRollo, 0)
            : null;

        return compact('repeticiones', 'pzasRollo', 'mtsRollo', 'totalRollos', 'totalPzas');
    }

    // ===== Líneas diarias =====

    /**
     * Ritmo de producción del programa entre $inicio y $fin. Null = no hay nada que repartir
     * (sin horas o sin piezas): no se generan líneas.
     *
     * @return array{stdHrEfectivo: float, prodKgDia: float, stdHrsEfect: float, prodKgDia2: float}|null
     */
    public function ritmo(ReqProgramaTejido $programa, Carbon $inicio, Carbon $fin): ?array
    {
        $totalSegundos = $fin->diffInSeconds($inicio, absolute: true);
        $totalHoras = $totalSegundos / 3600.0;
        $totalPzas = $this->piezasAProgramar($programa);
        $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

        if ($totalHoras <= 0 || $totalPzas <= 0) {
            return null;
        }

        $stdHrEfectivo = $totalPzas / $totalHoras;
        $prodKgDia = ($stdHrEfectivo > 0 && $pesoCrudo > 0) ? ($stdHrEfectivo * $pesoCrudo) / 1000.0 : 0.0;

        $diffDias = $totalSegundos / (float) self::SEGUNDOS_DIA;
        $stdHrsEfect = ($diffDias > 0) ? (($totalPzas / $diffDias) / 24.0) : 0.0;
        $prodKgDia2 = ($pesoCrudo > 0 && $stdHrsEfect > 0)
            ? ((($pesoCrudo * $stdHrsEfect) * 24.0) / 1000.0)
            : 0.0;

        return compact('stdHrEfectivo', 'prodKgDia', 'stdHrsEfect', 'prodKgDia2');
    }

    /**
     * Horas de cada día calendario entre $inicio y $fin (primer y último día parciales).
     *
     * @return array<string, float> fecha Y-m-d => horas
     */
    public function horasPorDia(Carbon $inicio, Carbon $fin): array
    {
        $inicioPeriodo = $inicio->copy()->startOfDay();
        $finPeriodo = $fin->copy()->startOfDay();
        $diasTotales = $inicioPeriodo->diffInDays($finPeriodo) + 1;

        $periodo = CarbonPeriod::create()
            ->setStartDate($inicioPeriodo)
            ->setRecurrences($diasTotales)
            ->setDateInterval('1 day');

        $horasPorDia = [];
        foreach ($periodo as $index => $dia) {
            $diaNormalizado = $this->aCarbon($dia)->copy()->startOfDay();
            $esUltimoDia = ($diaNormalizado->toDateString() === $finPeriodo->toDateString());
            $fraccion = $this->fraccionDelDia($diaNormalizado, $index === 0, $esUltimoDia, $inicio, $fin);

            $horasPorDia[$diaNormalizado->toDateString()] = $fraccion <= 0 ? 0.0 : $fraccion * 24.0;
        }

        return $horasPorDia;
    }

    private function fraccionDelDia(Carbon $diaNormalizado, bool $esPrimerDia, bool $esUltimoDia, Carbon $inicio, Carbon $fin): float|int
    {
        if ($esPrimerDia && $esUltimoDia) {
            return ($fin->timestamp - $inicio->timestamp) / self::SEGUNDOS_DIA;
        }
        if ($esPrimerDia) {
            $segundosDesdeMedianoche = ($inicio->hour * 3600) + ($inicio->minute * 60) + $inicio->second;

            return (self::SEGUNDOS_DIA - $segundosDesdeMedianoche) / self::SEGUNDOS_DIA;
        }
        if ($esUltimoDia) {
            return abs($fin->diffInSeconds($diaNormalizado, false)) / self::SEGUNDOS_DIA;
        }

        return 1.0;
    }

    private function aCarbon(mixed $dia): Carbon
    {
        if ($dia instanceof Carbon) {
            return $dia;
        }

        return $dia instanceof DateTimeInterface ? Carbon::instance($dia) : Carbon::parse($dia);
    }

    private function piezasAProgramar(ReqProgramaTejido $programa): float
    {
        return (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
    }
}
