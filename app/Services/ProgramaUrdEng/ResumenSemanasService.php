<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResumenSemanasService
{
    private const STATUS_ACTIVO = 'Activo';
    private const FALLBACK_METROS_DEFAULT = true;

    /**
     * Genera el resumen de 5 semanas para los telares dados.
     *
     * @param array $telares Lista de telares [{no_telar, tipo, calibre, hilo, salon, ...}]
     * @param bool $usarFallbackMetros Si debe usar MtsPie como fallback de MtsRizo y viceversa
     * @return array ['success' => bool, 'data' => [...], 'semanas' => [...], 'message' => string|null]
     */
    public function generar(array $telares, bool $usarFallbackMetros = self::FALLBACK_METROS_DEFAULT): array
    {
        $semanas = $this->construirSemanas(5);

        if (empty($telares)) {
            return [
                'success' => true,
                'message' => 'No hay telares seleccionados',
                'data'    => ['rizo' => [], 'pie' => []],
                'semanas' => $semanas,
            ];
        }

        $v = $this->validarTelaresConsistentes($telares);
        if ($v['error']) {
            return [
                'success' => false,
                'message' => $v['mensaje'],
                'data'    => ['rizo' => [], 'pie' => []],
                'semanas' => $semanas,
            ];
        }

        $tipoEsperado    = $v['tipo'];
        $calibreEsperado = $v['calibre'];
        $calibreEsVacio  = $v['calibre_vacio'];
        $hiloEsperado    = $v['hilo'];
        $salonEsperado   = $v['salon'];

        $noTelares = collect($telares)->pluck('no_telar')->filter()->unique()->values()->toArray();
        if (empty($noTelares)) {
            return [
                'success' => true,
                'message' => 'No se encontraron números de telar válidos',
                'data'    => ['rizo' => [], 'pie' => []],
                'semanas' => $semanas,
            ];
        }

        [$fechaIni, $fechaFin] = [$semanas[0]['inicio'], $semanas[4]['fin']];

        // Hilos reales por telar
        $hiloPorTelar = $this->obtenerHiloPorTelar($noTelares);

        // Programas + líneas (eager)
        $programas = $this->cargarProgramas($noTelares, $fechaIni, $fechaFin);

        $lineasPorPrograma = [];
        foreach ($programas as $programa) {
            if ($programa->Id && $programa->lineas->count() > 0) {
                $lineasPorPrograma[$programa->Id] = $programa->lineas;
            }
        }

        if ($tipoEsperado === 'RIZO') {
            $programasFiltrados = $this->filtrarProgramasRizo(
                $programas, $salonEsperado, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $hiloPorTelar
            );

            $resumenRizo = $this->procesarResumenPorTipo(
                $programasFiltrados, $semanas, 'Rizo',
                null, $hiloEsperado,
                $v['calibre_original'] === '' ? null : $v['calibre_original'],
                $calibreEsVacio, $lineasPorPrograma, $fechaIni, $fechaFin, $usarFallbackMetros
            );

            return [
                'success' => true,
                'data'    => ['rizo' => $resumenRizo, 'pie' => []],
                'semanas' => $semanas,
            ];
        }

        // PIE
        $programasFiltrados = $programas->filter(function ($p) use ($salonEsperado, $calibreEsperado, $calibreEsVacio) {
            return $this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''))
                && !empty($p->CuentaPie)
                && $this->matchCalibre($calibreEsperado, $calibreEsVacio, $p->CalibrePie ?? null);
        })->values();

        $resumenPie = $this->procesarResumenPorTipo(
            $programasFiltrados, $semanas, 'Pie',
            null, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $lineasPorPrograma,
            $fechaIni, $fechaFin, $usarFallbackMetros
        );

        return [
            'success' => true,
            'data'    => ['rizo' => [], 'pie' => $resumenPie],
            'semanas' => $semanas,
        ];
    }

    /* ==================== Métodos internos ==================== */

    private function obtenerHiloPorTelar(array $noTelares): array
    {
        $telaresConHilo = TejInventarioTelares::whereIn('no_telar', $noTelares)
            ->where('status', self::STATUS_ACTIVO)
            ->select('no_telar', 'tipo', 'hilo', 'salon', 'calibre')
            ->get()
            ->groupBy('no_telar')
            ->map(fn ($g) => $g->first());

        $hiloPorTelar = [];
        foreach ($telaresConHilo as $telar) {
            $noTelar = (string)($telar->no_telar ?? '');
            $hiloTelar = trim((string)($telar->hilo ?? ''));
            if ($noTelar !== '') $hiloPorTelar[$noTelar] = $hiloTelar;
        }
        return $hiloPorTelar;
    }

    private function cargarProgramas(array $noTelares, string $fechaIni, string $fechaFin)
    {
        return ReqProgramaTejido::whereIn('NoTelarId', $noTelares)
            ->with(['lineas' => function ($query) use ($fechaIni, $fechaFin) {
                $query->select([
                    'Id', 'ProgramaId', 'Fecha',
                    DB::raw('[Pie] as Pie'),
                    DB::raw('[Rizo] as Rizo'),
                    'MtsRizo', 'MtsPie'
                ])
                ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                ->orderBy('Fecha');
            }])
            ->get();
    }

    private function filtrarProgramasRizo($programas, string $salonEsperado, ?string $hiloEsperado, $calibreEsperado, bool $calibreEsVacio, array $hiloPorTelar)
    {
        return $programas->filter(function ($p) use ($salonEsperado, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $hiloPorTelar) {
            if (!$this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''))) return false;
            if (empty($p->CuentaRizo)) return false;

            $hiloPrograma = trim((string)($p->FibraRizo ?? ''));
            $noTelarPrograma = (string)($p->NoTelarId ?? '');
            $hiloTelarBD = $hiloPorTelar[$noTelarPrograma] ?? '';
            $matchHiloTelar = true;

            if ($hiloTelarBD !== '') {
                if ($hiloPrograma === '') {
                    $matchHiloTelar = false;
                } else {
                    $matchHiloTelar = $this->matchHilo($hiloTelarBD, $hiloPrograma);
                }
            } elseif ($hiloEsperado !== null && $hiloEsperado !== '') {
                $matchHiloTelar = $this->matchHilo($hiloEsperado, $hiloPrograma);
            }

            $matchCalibre = true;
            if (!$calibreEsVacio && $calibreEsperado !== null) {
                $matchCalibre = $this->matchCalibre($calibreEsperado, $calibreEsVacio, $p->CalibreRizo ?? null);
            }

            return $matchHiloTelar && $matchCalibre;
        })->values();
    }

    private function procesarResumenPorTipo(
        $programas, array &$semanas, string $tipo,
        $cuentaEsperada = null, $hiloEsperado = null,
        $calibreEsperado = null, bool $calibreEsVacio = false,
        array $lineasPorPrograma = [], ?string $fechaIni = null,
        ?string $fechaFin = null, bool $usarFallbackMetros = false
    ): array {
        $resumen = [];

        foreach ($programas as $programa) {
            $esRizo = $tipo === 'Rizo';
            $cuenta = trim((string)($esRizo ? ($programa->CuentaRizo ?? '') : ($programa->CuentaPie ?? '')));
            $calibre = $esRizo ? ($programa->CalibreRizo ?? $programa->Calibre ?? null) : ($programa->CalibrePie ?? null);
            $fibraRaw = $esRizo ? ($programa->FibraRizo ?? null) : ($programa->FibraPie ?? null);
            $hilo = ($fibraRaw !== null && trim((string)$fibraRaw) !== '') ? trim((string)$fibraRaw) : '';
            $campoMetros = $esRizo ? 'MtsRizo' : 'MtsPie';

            if ($cuenta === '') continue;
            if ($tipo !== 'Pie' && !$this->matchHilo($hiloEsperado, $hilo)) continue;
            if (!$this->matchCalibre($calibreEsperado, $calibreEsVacio, $calibre)) continue;
            if ($cuentaEsperada !== null && $cuenta !== $cuentaEsperada) continue;

            $modelo = $programa->ItemId ?? $programa->NombreProducto ?? '';
            $telarId = (string)($programa->NoTelarId ?? '');
            $clave = $esRizo
                ? "{$telarId}|{$cuenta}|{$hilo}|{$modelo}"
                : "{$telarId}|{$cuenta}|{$calibre}|{$modelo}";

            $resumen[$clave] ??= [
                'TelarId' => $telarId, 'CuentaValor' => $cuenta, 'Hilo' => $hilo,
                'Calibre' => $calibre, 'Modelo' => $modelo,
                'SemActual' => 0, 'SemActual1' => 0, 'SemActual2' => 0, 'SemActual3' => 0, 'SemActual4' => 0,
                'SemActualKilos' => 0, 'SemActual1Kilos' => 0, 'SemActual2Kilos' => 0, 'SemActual3Kilos' => 0, 'SemActual4Kilos' => 0,
                'Total' => 0, 'TotalKilos' => 0,
            ];

            $lineas = $this->resolverLineas($programa, $lineasPorPrograma, $fechaIni, $fechaFin);

            foreach ($lineas as $ln) {
                $fecha = $ln->Fecha ?? null;
                if (!$fecha) continue;

                $mts = (float)($ln->{$campoMetros} ?? 0);
                if ($usarFallbackMetros && $mts <= 0) {
                    $altCampo = $campoMetros === 'MtsRizo' ? 'MtsPie' : 'MtsRizo';
                    $alt = (float)($ln->{$altCampo} ?? 0);
                    if ($alt > 0) $mts = $alt;
                }

                $kilos = (float)($esRizo ? ($ln->Rizo ?? 0) : ($ln->Pie ?? 0));
                if ($mts <= 0 && $kilos <= 0) continue;

                $f = $this->parseToCarbon($fecha);
                if (!$f) continue;

                $idx = $this->semanaIndex($semanas, $f);
                if ($idx === null) continue;

                $semKey = $idx === 0 ? 'SemActual' : "SemActual{$idx}";
                $semKilosKey = $idx === 0 ? 'SemActualKilos' : "SemActual{$idx}Kilos";
                $resumen[$clave][$semKey] += $mts;
                $resumen[$clave][$semKilosKey] += $kilos;
                $this->agregarTotalesSemana($semanas, $idx, $mts, $kilos);
                $resumen[$clave]['Total'] += $mts;
                $resumen[$clave]['TotalKilos'] += $kilos;
            }
        }

        return $this->formatearResumen($resumen, $tipo);
    }

    private function resolverLineas($programa, array $lineasPorPrograma, ?string $fechaIni, ?string $fechaFin)
    {
        $programaId = $programa->Id ?? null;
        if (!$programaId) return collect();

        if ($programa->relationLoaded('lineas')) {
            return $programa->lineas;
        }

        if (isset($lineasPorPrograma[$programaId])) {
            return $lineasPorPrograma[$programaId];
        }

        if ($fechaIni && $fechaFin) {
            return $programa->lineas()
                ->select([
                    'Id', 'ProgramaId', 'Fecha',
                    DB::raw('[Pie] as Pie'), DB::raw('[Rizo] as Rizo'),
                    'MtsRizo', 'MtsPie'
                ])
                ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                ->orderBy('Fecha')
                ->get();
        }

        return $programa->lineas;
    }

    private function formatearResumen(array $resumen, string $tipo): array
    {
        return collect($resumen)->values()->map(function ($it) use ($tipo) {
            $base = [
                'TelarId' => $it['TelarId'],
                'Hilo' => $it['Hilo'],
                'Modelo' => $it['Modelo'],
                'Total' => round((float)$it['Total'], 2),
                'TotalKilos' => round((float)$it['TotalKilos'], 2),
            ];

            $prefix = $tipo === 'Rizo' ? 'Rizo' : 'Pie';
            $base["Cuenta{$prefix}"] = $it['CuentaValor'];
            $base[$tipo === 'Rizo' ? 'Calibre' : 'CalibrePie'] = $it['Calibre'];

            for ($i = 0; $i < 5; $i++) {
                $semKey = $i === 0 ? 'SemActual' : "SemActual{$i}";
                $semKilosKey = $i === 0 ? 'SemActualKilos' : "SemActual{$i}Kilos";
                $mtsKey = $i === 0 ? "SemActualMts{$prefix}" : "SemActual{$i}Mts{$prefix}";
                $kilosKey = $i === 0 ? "SemActualKilos{$prefix}" : "SemActual{$i}Kilos{$prefix}";
                $base[$mtsKey] = round((float)$it[$semKey], 2);
                $base[$kilosKey] = round((float)$it[$semKilosKey], 2);
            }

            return $base;
        })
        ->sortBy([['TelarId', 'asc'], ['Modelo', 'asc']])
        ->values()
        ->toArray();
    }

    /* ==================== Helpers ==================== */

    private function parseToCarbon($fecha): ?Carbon
    {
        if ($fecha instanceof Carbon) return $fecha->copy()->startOfDay();
        if ($fecha instanceof \DateTime) return Carbon::instance($fecha)->startOfDay();
        if (is_string($fecha)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $fecha)?->startOfDay() ?? Carbon::parse($fecha)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
        try { return Carbon::parse($fecha)->startOfDay(); } catch (\Throwable) { return null; }
    }

    public function construirSemanas(int $n = 5): array
    {
        $inicioBase = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $ini = $inicioBase->copy()->addWeeks($i)->startOfDay();
            $fin = $ini->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            $out[] = [
                'numero' => $i + 1,
                'inicio' => $ini->format('Y-m-d'),
                'fin'    => $fin->format('Y-m-d'),
                'label'  => $i === 0 ? 'Sem Actual' : "Sem Actual +{$i}",
                'total_metros' => 0.0,
                'total_kilos'  => 0.0,
            ];
        }
        return $out;
    }

    private function semanaIndex(array $semanas, Carbon $fecha): ?int
    {
        foreach ($semanas as $i => $sem) {
            $ini = Carbon::parse($sem['inicio'])->startOfDay();
            $fin = Carbon::parse($sem['fin'])->endOfDay();
            if ($fecha->between($ini, $fin)) return $i;
        }
        return null;
    }

    private function agregarTotalesSemana(array &$semanas, int $idx, float $mts, float $kilos): void
    {
        if (!isset($semanas[$idx])) return;
        $semanas[$idx]['total_metros'] += $mts;
        $semanas[$idx]['total_kilos'] += $kilos;
    }

    public function validarTelaresConsistentes(array $telares): array
    {
        $t0 = $telares[0] ?? [];
        $tipo = strtoupper(trim((string)($t0['tipo'] ?? '')));
        if ($tipo === '') return ['error' => true, 'mensaje' => 'El telar seleccionado debe tener un tipo definido'];

        $calibreOriginal = (string)($t0['calibre'] ?? '');
        $calibreVacio = $calibreOriginal === '';
        $calibreRef = $calibreVacio ? null : $calibreOriginal;
        $hiloRef = (string)($t0['hilo'] ?? '');
        $salonRef = strtoupper(trim((string)($t0['salon'] ?? '')));
        $esPie = $tipo === 'PIE';

        foreach ($telares as $t) {
            $tipoAct = strtoupper(trim((string)($t['tipo'] ?? '')));
            if ($tipoAct !== $tipo) return ['error' => true, 'mensaje' => "Todos los telares deben tener el mismo tipo. {$tipoAct} ≠ {$tipo}"];

            $calAct = (string)($t['calibre'] ?? '');
            if ($calibreRef !== null && $calAct !== '' && abs((float)$calAct - (float)$calibreRef) >= 0.01) {
                return ['error' => true, 'mensaje' => "Todos los telares deben tener el mismo calibre. {$calAct} ≠ {$calibreRef}"];
            }

            if (!$esPie) {
                $hiloAct = trim((string)($t['hilo'] ?? ''));
                if ($hiloRef !== '' && $hiloAct !== '' && strcasecmp($hiloAct, $hiloRef) !== 0) {
                    return ['error' => true, 'mensaje' => "Todos los telares deben tener el mismo hilo. {$hiloAct} ≠ {$hiloRef}"];
                }
            }

            $salAct = strtoupper(trim((string)($t['salon'] ?? '')));
            if ($salonRef !== '' && $salAct !== '' && $salAct !== $salonRef) {
                return ['error' => true, 'mensaje' => "Todos los telares deben tener el mismo salón. {$salAct} ≠ {$salonRef}"];
            }
        }

        return [
            'error' => false, 'tipo' => $tipo, 'calibre' => $calibreRef,
            'calibre_vacio' => $calibreVacio, 'calibre_original' => $calibreOriginal,
            'hilo' => $esPie ? '' : $hiloRef, 'salon' => $salonRef,
        ];
    }

    private function matchHilo($esperado, string $actual): bool
    {
        $act = trim($actual);
        $esp = $esperado !== null ? trim((string)$esperado) : null;
        if ($esperado === null) return true;
        if ($esperado === '' || $esp === '') return ($act === '' || $act === 'null');
        return strcasecmp($act, $esp) === 0;
    }

    private function matchCalibre($esperado, bool $vacio, $actual): bool
    {
        if ($vacio) return ($actual === null || $actual === '' || trim((string)$actual) === '');
        if ($esperado === null || $esperado === '') return true;
        return abs((is_numeric($actual) ? (float)$actual : 0) - (float)$esperado) <= 0.11;
    }

    private function matchSalon(string $esperado, string $actual): bool
    {
        if ($esperado === '') return true;
        $esp = strtoupper(trim($esperado));
        $act = strtoupper(trim($actual));
        if ($act === $esp) return true;
        if ($esp === 'ITEMA' && $act === 'SMIT') return true;
        if ($esp === 'SMITH' && $act === 'SMIT') return true;
        if ($esp === 'SMIT' && ($act === 'ITEMA' || $act === 'SMITH')) return true;
        return false;
    }
}
