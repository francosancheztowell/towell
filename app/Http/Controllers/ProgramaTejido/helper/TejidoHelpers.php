<?php

namespace App\Http\Controllers\ProgramaTejido\helper;

use App\Models\ReqCalendarioLine;
use App\Models\ReqEficienciaStd;
use App\Models\ReqProgramaTejido;
use App\Models\ReqVelocidadStd;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TejidoHelpers
{
    public static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;

        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    public static function sanitizeNullableNumber($value): ?float
    {
        if ($value === null) return null;
        if ($value === '') return null;
        if (is_numeric($value)) return (float)$value;

        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : null;
    }

    public static function construirMaquinaConSalon(?string $maquinaBase, ?string $salon, $telar): string
    {
        $salonNorm = strtoupper(trim((string)$salon));
        $prefijo = null;

        if ($salonNorm !== '') {
            if (preg_match('/SMI(T)?/i', $salonNorm)) {
                $prefijo = 'SMI';
            } elseif (preg_match('/JAC/i', $salonNorm)) {
                $prefijo = 'JAC';
            }
        }

        if (!$prefijo && $maquinaBase && preg_match('/^([A-Za-z]+)/', trim($maquinaBase), $matches)) {
            $prefijo = $matches[1];
        }

        if (!$prefijo && $salonNorm !== '') {
            $prefijo = substr($salonNorm, 0, 4);
            $prefijo = rtrim($prefijo, '0123456789');
        }

        if (!$prefijo) {
            $prefijo = 'TEL';
        }

        return trim($prefijo) . ' ' . trim((string)$telar);
    }

    public static function construirMaquinaConBase(?string $maquinaBase, ?string $salon, $telar): string
    {
        $prefijo = null;

        if ($maquinaBase && preg_match('/^([A-Za-z]+)\s*\d*/', trim($maquinaBase), $m)) {
            $prefijo = $m[1];
        }

        if (!$prefijo && $salon) {
            $prefijo = rtrim(substr($salon, 0, 4), '0123456789');
        }

        if (!$prefijo) $prefijo = 'TEL';

        return trim($prefijo) . ' ' . trim((string)$telar);
    }

    public static function calcularHorasProd(
        float $vel,
        float $efic,
        float $cantidad,
        float $noTiras,
        float $total,
        float $luchaje,
        float $repeticiones
    ): float {
        if ($efic > 1) $efic = $efic / 100;

        $stdToaHra = 0.0;
        if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $repeticiones > 0 && $vel > 0) {
            $parte1 = $total;
            $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticiones;
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) {
                $stdToaHra = ($noTiras * 60) / $den;
            }
        }

        if ($stdToaHra > 0 && $efic > 0 && $cantidad > 0) {
            return $cantidad / ($stdToaHra * $efic);
        }

        return 0.0;
    }

    public static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio, ?array $lines = null): ?Carbon
    {
        $calendarioId = trim((string)$calendarioId);
        if ($calendarioId === '') return null;

        if ($lines !== null) {
            return self::snapInicioEnLineas($fechaInicio, $lines);
        }

        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio->format('Y-m-d H:i:s'))
            ->orderBy('FechaInicio')
            ->first();

        if (!$linea) return null;

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
            return $fechaInicio->copy();
        }

        return $ini->copy();
    }

    private static function snapInicioEnLineas(Carbon $fechaInicio, array $lines): ?Carbon
    {
        if (empty($lines)) return null;

        $inicioTs = $fechaInicio->getTimestamp();
        foreach ($lines as $line) {
            $finTs = $line['fin_ts'] ?? null;
            if ($finTs === null || $finTs <= $inicioTs) {
                continue;
            }

            $ini = $line['ini'] ?? null;
            $fin = $line['fin'] ?? null;
            if (!$ini || !$fin) continue;

            if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
                return $fechaInicio->copy();
            }

            return $ini->copy();
        }

        return null;
    }

    public static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p, bool $logWarnings = false, bool $logChanges = false): void
    {
        $tipoTelar = self::resolverTipoTelarStd($p->Maquina ?? null, $p->SalonTejidoId ?? null);
        $telar     = trim((string)($p->NoTelarId ?? ''));
        $fibraId   = trim((string)($p->FibraRizo ?? ''));
        $densidad  = self::resolverDensidadStd($p->Densidad ?? null);

        if ($telar === '' || $fibraId === '') {
            if ($logWarnings) {
                Log::warning('STD: telar o fibra vacios, no se puede aplicar', [
                    'tipoTelar' => $tipoTelar,
                    'telar' => $telar,
                    'fibra' => $fibraId,
                    'programa_id' => $p->Id ?? null,
                ]);
            }
            return;
        }

        $velRow = self::buscarStdVelocidad($tipoTelar, $telar, $fibraId, $densidad);
        $efiRow = self::buscarStdEficiencia($tipoTelar, $telar, $fibraId, $densidad);

        $oldVel = $p->VelocidadSTD ?? null;
        $oldEfi = $p->EficienciaSTD ?? null;

        if ($velRow) {
            $p->VelocidadSTD = (float)$velRow->Velocidad;
        } elseif ($logWarnings) {
            Log::warning('STD: No se encontro velocidad', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'densidad' => $densidad,
                'velocidad_actual' => $oldVel,
            ]);
        }

        if ($efiRow) {
            $efi = (float)$efiRow->Eficiencia;
            if ($efi > 1) $efi = $efi / 100;
            $p->EficienciaSTD = round($efi, 2);
        } elseif ($logWarnings) {
            Log::warning('STD: No se encontro eficiencia', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'densidad' => $densidad,
                'eficiencia_actual' => $oldEfi,
            ]);
        }
    }

    public static function resolverTipoTelarStd(?string $maquina, ?string $salonTejidoId): string
    {
        $m = strtoupper(trim((string)$maquina));
        $s = strtoupper(trim((string)$salonTejidoId));

        if ($m !== '') {
            if (str_contains($m, 'SMI')) return 'SMITH';
            if (str_contains($m, 'JAC')) return 'JACQUARD';
            if (str_contains($m, 'KARL MAYER')) return 'KM';
        }

        if ($s === 'SMIT' || $s === 'SMITH') return 'SMITH';
        if ($s === 'JAC' || $s === 'JACQ' || $s === 'JACQUARD') return 'JACQUARD';
        if ($s === 'KM' || $s === 'KARL MAYER') return 'KM';
        return $s !== '' ? $s : 'SMITH';
    }

    public static function resolverDensidadStd(?string $densidad): string
    {
        if ($densidad !== null && $densidad !== '') {
            $d = trim((string)$densidad);
            if (strcasecmp($d, 'Alta') === 0) return 'Alta';
            if (strcasecmp($d, 'Normal') === 0) return 'Normal';
        }
        return 'Normal';
    }

    public static function buscarStdVelocidad(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqVelocidadStd
    {
        $q = ReqVelocidadStd::query()
            ->where('SalonTejidoId', $tipoTelar)
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        $row = (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first();
        if ($row) return $row;

        $rowNull = (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first();
        if ($rowNull) return $rowNull;

        return (clone $q)->orderBy('Id', 'desc')->first();
    }

    public static function buscarStdEficiencia(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqEficienciaStd
    {
        $q = ReqEficienciaStd::query()
            ->where('SalonTejidoId', $tipoTelar)
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        $row = (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first();
        if ($row) return $row;

        $rowNull = (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first();
        if ($rowNull) return $rowNull;

        return (clone $q)->orderBy('Id', 'desc')->first();
    }
}
