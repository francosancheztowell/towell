<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqVelocidadStd;
use App\Support\Planeacion\TelarSalonResolver;

/**
 * Estándares de velocidad y eficiencia (ReqVelocidadStd / ReqEficienciaStd) por tipo de telar,
 * telar, fibra y densidad.
 */
final class EstandaresTelar
{
    private const TIPO_TELAR_POR_SALON = [
        'SMIT' => 'SMITH', 'SMITH' => 'SMITH',
        'JAC' => 'JACQUARD', 'JACQ' => 'JACQUARD', 'JACQUARD' => 'JACQUARD',
        'KM' => 'KM', 'KARL MAYER' => 'KM',
    ];

    /** Pone VelocidadSTD y EficienciaSTD desde los catálogos STD (si hay fila). */
    public static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        $tipoTelar = self::resolverTipoTelarStd($p->Maquina ?? null, $p->SalonTejidoId ?? null);
        $telar = trim((string) ($p->NoTelarId ?? ''));
        $fibraId = trim((string) ($p->FibraRizo ?? ''));
        $densidad = self::resolverDensidadStd($p->Densidad ?? null);

        if ($telar === '' || $fibraId === '') {
            return;
        }

        $velRow = self::buscarStdVelocidad($tipoTelar, $telar, $fibraId, $densidad);
        $efiRow = self::buscarStdEficiencia($tipoTelar, $telar, $fibraId, $densidad);

        if ($velRow) {
            $p->setAttribute('VelocidadSTD', (float) $velRow->Velocidad);
        }

        if ($efiRow) {
            $efi = (float) $efiRow->Eficiencia;
            if ($efi > 1) {
                $efi = $efi / 100;
            }
            $p->setAttribute('EficienciaSTD', round($efi, 2));
        }
    }

    /** La máquina manda sobre el salón; sin ninguno, SMITH. */
    public static function resolverTipoTelarStd(?string $maquina, ?string $salonTejidoId): string
    {
        $tipo = self::tipoTelarDesdeMaquina(strtoupper(trim((string) $maquina)));
        if ($tipo !== null) {
            return $tipo;
        }

        $s = strtoupper(trim((string) $salonTejidoId));

        return self::TIPO_TELAR_POR_SALON[$s] ?? ($s !== '' ? $s : 'SMITH');
    }

    private static function tipoTelarDesdeMaquina(string $m): ?string
    {
        if (str_contains($m, 'SMI')) {
            return 'SMITH';
        }
        if (str_contains($m, 'JAC')) {
            return 'JACQUARD';
        }
        if (str_contains($m, 'KARL') || $m === 'KM' || str_starts_with($m, 'KM ')) {
            return 'KM';
        }

        return null;
    }

    public static function resolverDensidadStd(?string $densidad): string
    {
        if ($densidad !== null && $densidad !== '') {
            $d = trim((string) $densidad);
            if (strcasecmp($d, 'Alta') === 0) {
                return 'Alta';
            }
            if (strcasecmp($d, 'Normal') === 0) {
                return 'Normal';
            }
        }

        return 'Normal';
    }

    public static function buscarStdVelocidad(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqVelocidadStd
    {
        // Los catalogos STD se capturan con el salon escrito a mano (velocidadCreate.blade.php
        // es un input libre), asi que se buscan por todos los alias: 'KM' y 'KARL MAYER'
        // apuntan a la misma fila, igual que 'SMIT'/'SMITH'/'ITEMA'.
        $q = ReqVelocidadStd::query()
            ->whereIn('SalonTejidoId', TelarSalonResolver::salonAliases($tipoTelar) ?: [$tipoTelar])
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        return (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first()
            ?? (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first()
            ?? (clone $q)->orderBy('Id', 'desc')->first();
    }

    /**
     * Eficiencia y Velocidad STD de un programa que se mueve al telar $nuevoTelar.
     *
     * Busca por telar + fibra + densidad exactos (sin alias de salón ni fallback de
     * densidad, a diferencia de buscarStd*): no se unifica para no cambiar valores.
     * Sin fila de velocidad usa VelocidadSTD del modelo destino; lo que no se resuelva
     * conserva el STD actual del programa.
     *
     * @return array{0: mixed, 1: mixed} [eficiencia, velocidad]
     */
    public static function resolverStdSegunTelar(ReqProgramaTejido $registro, ?ReqModelosCodificados $modeloDestino, string $nuevoTelar): array
    {
        $fibra = $registro->FibraRizo
            ?? $registro->FibraTrama
            ?? ($modeloDestino->FibraRizo ?? null)
            ?? ($modeloDestino->FibraId ?? null);

        $calibreTrama = $registro->CalibreTrama
            ?? $registro->CalibreTrama2
            ?? ($modeloDestino->CalibreTrama ?? null)
            ?? ($modeloDestino->CalibreTrama2 ?? null);

        $densidad = ($calibreTrama !== null && (float) $calibreTrama > 40) ? 'Alta' : 'Normal';

        $eficiencia = null;
        $velocidad = null;

        if ($fibra) {
            $eficiencia = ReqEficienciaStd::where('NoTelarId', $nuevoTelar)
                ->where('FibraId', $fibra)
                ->where('Densidad', $densidad)
                ->value('Eficiencia');

            $velocidad = ReqVelocidadStd::where('NoTelarId', $nuevoTelar)
                ->where('FibraId', $fibra)
                ->where('Densidad', $densidad)
                ->value('Velocidad');
        }

        $velocidadModelo = $modeloDestino?->getAttribute('VelocidadSTD');
        if (is_null($velocidad) && ! is_null($velocidadModelo)) {
            $velocidad = (float) $velocidadModelo;
        }

        return [
            $eficiencia ?? $registro->getAttribute('EficienciaSTD'),
            $velocidad ?? $registro->getAttribute('VelocidadSTD'),
        ];
    }

    public static function buscarStdEficiencia(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqEficienciaStd
    {
        $q = ReqEficienciaStd::query()
            ->whereIn('SalonTejidoId', TelarSalonResolver::salonAliases($tipoTelar) ?: [$tipoTelar])
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        return (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first()
            ?? (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first()
            ?? (clone $q)->orderBy('Id', 'desc')->first();
    }
}
