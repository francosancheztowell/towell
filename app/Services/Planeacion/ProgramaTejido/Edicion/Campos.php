<?php

namespace App\Services\Planeacion\ProgramaTejido\Edicion;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Log;

/**
 * Asignación de un campo del PUT a su columna, solo si el campo viene en $data.
 *
 * @internal Paso de EdicionProgramaTejido::aplicarCambios().
 */
final class Campos
{
    /** '' (o falsy) se guarda como null. Devuelve si el campo venía en el PUT. */
    public static function texto(ReqProgramaTejido $registro, array $data, string $campo, string $columna): bool
    {
        if (! array_key_exists($campo, $data)) {
            return false;
        }
        $registro->setAttribute($columna, $data[$campo] ?: null);

        return true;
    }

    public static function numero(ReqProgramaTejido $registro, array $data, string $campo, string $columna): bool
    {
        if (! array_key_exists($campo, $data)) {
            return false;
        }
        $registro->setAttribute($columna, $data[$campo] !== null ? (float) $data[$campo] : null);

        return true;
    }

    public static function fecha(ReqProgramaTejido $registro, array $data, string $campo, string $columna): void
    {
        if (! array_key_exists($campo, $data)) {
            return;
        }
        if (! $data[$campo]) {
            $registro->setAttribute($columna, null);

            return;
        }

        try {
            $registro->setAttribute($columna, Carbon::parse($data[$campo]));
        } catch (InvalidFormatException) {
            // Fecha inválida: no se asigna.
        } catch (\Throwable $e) {
            Log::warning('DateHelpers: Error al asignar fecha segura', [
                'atributo' => $columna, 'valor' => $data[$campo], 'error' => $e->getMessage(),
            ]);
        }
    }
}
