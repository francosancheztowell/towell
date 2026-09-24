<?php

namespace App\Http\Requests\Monitoreo;

use App\Services\Monitoreo\Monitoreo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de los endpoints de telemetría (contrato §4).
 *
 * Sin reglas a propósito: la telemetría nunca se rechaza con 422; los valores se
 * recortan y acotan con los helpers de abajo. Acepta JSON o FormData (sendBeacon).
 */
class TelemetriaRequest extends FormRequest
{
    public const MS_MAX = 600000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function texto(string $llave, int $max): ?string
    {
        return Monitoreo::texto($this->input($llave), $max);
    }

    public function entero(string $llave, int $min = 0, int $max = self::MS_MAX): ?int
    {
        $valor = $this->input($llave);
        if (! is_numeric($valor)) {
            return null;
        }

        return (int) max($min, min($max, round((float) $valor)));
    }

    public function booleano(string $llave, bool $default = true): bool
    {
        return $this->has($llave) ? $this->boolean($llave) : $default;
    }

    /** Solo el path: sin esquema, host, query string ni fragmento. */
    public function soloPath(string $llave, int $max): ?string
    {
        $valor = $this->input($llave);
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $path = parse_url(trim($valor), PHP_URL_PATH);

        return Monitoreo::texto(is_string($path) ? $path : null, $max);
    }

    /** Nombre de ruta (o URI de plantilla): sin query string. */
    public function ruta(string $llave = 'ruta', int $max = 150): ?string
    {
        $valor = $this->texto($llave, 1000);

        return $valor === null ? null : Monitoreo::texto(strtok($valor, '?#'), $max);
    }
}
