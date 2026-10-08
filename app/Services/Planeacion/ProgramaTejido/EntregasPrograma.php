<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Log;

/**
 * Fechas de entrega del programa de tejido (EntregaCte, EntregaPT, EntregaProduc, PTvsCte) a
 * partir de FechaFinal. Parte de {@see FormulasEficiencia}: escribe sobre el mismo arreglo de
 * fórmulas para que un error deje lo calculado hasta ahí.
 */
final class EntregasPrograma
{
    public static function resolverDiasEntrega(ReqProgramaTejido $programa): int
    {
        $aplicacion = trim((string) ($programa->AplicacionId ?? ''));
        if ($aplicacion === '' || strtoupper($aplicacion) === 'NA') {
            return 12;
        }

        return 16;
    }

    /** EntregaCte, EntregaPT, EntregaProduc y PTvsCte. */
    public static function agregarFormulas(
        array &$formulas,
        ReqProgramaTejido $programa,
        bool $includeEntregaCte,
        bool $includePTvsCte,
        bool $fallbackEntregaCteFromProgram
    ): void {
        $diasEntrega = self::resolverDiasEntrega($programa);
        [$entregaCteCalculada, $entregaPT] = self::entregasDesdeFechaFinal($formulas, $programa, $diasEntrega, $includeEntregaCte);

        if (! $entregaPT && ! empty($programa->EntregaPT)) {
            $entregaPT = self::entregaPtGuardada($programa);
        }

        if ($entregaPT) {
            $formulas['EntregaProduc'] = $entregaPT->copy()->subDays($diasEntrega)->format('Y-m-d');
        }

        if ($includePTvsCte && $entregaPT) {
            self::formulaPtVsCte($formulas, $programa, $entregaCteCalculada, $entregaPT, $fallbackEntregaCteFromProgram);
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} [EntregaCte calculada, EntregaPT] */
    private static function entregasDesdeFechaFinal(array &$formulas, ReqProgramaTejido $programa, int $diasEntrega, bool $includeEntregaCte): array
    {
        $entregaCte = null;
        $entregaPT = null;
        if (empty($programa->FechaFinal)) {
            return [null, null];
        }

        try {
            $fechaFinal = Carbon::parse($programa->FechaFinal);
            $entregaCte = $fechaFinal->copy()->addDays($diasEntrega);

            if ($includeEntregaCte) {
                $formulas['EntregaCte'] = $entregaCte->format('Y-m-d H:i:s');
            }

            $entregaPT = $fechaFinal->copy()->day(15);
            $formulas['EntregaPT'] = $entregaPT->format('Y-m-d');
        } catch (InvalidFormatException $e) {
            // FechaFinal inválida o no se puede calcular entrega PT
        } catch (\Throwable $e) {
            Log::warning('TejidoHelpers: Error al calcular fecha PT', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return [$entregaCte, $entregaPT];
    }

    private static function entregaPtGuardada(ReqProgramaTejido $programa): ?Carbon
    {
        try {
            return Carbon::parse($programa->getAttribute('EntregaPT'));
        } catch (InvalidFormatException $e) {
            return null;
        } catch (\Throwable $e) {
            Log::warning('TejidoHelpers: Error al parsear EntregaPT', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);

            return null;
        }
    }

    private static function formulaPtVsCte(array &$formulas, ReqProgramaTejido $programa, ?Carbon $entregaCte, Carbon $entregaPT, bool $fallbackDesdePrograma): void
    {
        if (! $entregaCte && $fallbackDesdePrograma && ! empty($programa->EntregaCte)) {
            $entregaCte = Carbon::parse($programa->EntregaCte);
        }

        if ($entregaCte) {
            $formulas['PTvsCte'] = (float) round($entregaCte->diffInDays($entregaPT, false), 2);
        }
    }
}
