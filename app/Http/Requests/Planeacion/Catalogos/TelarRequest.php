<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\Models\Urdido\URDCatalogoMaquina;

class TelarRequest extends CatalogoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'SalonTejidoId' => ['required', 'string', 'max:20', function (string $campo, mixed $valor, \Closure $falla): void {
                if (URDCatalogoMaquina::departamentoDeSalon((string) $valor) === null) {
                    $falla('El salón debe ser '.implode(', ', URDCatalogoMaquina::DEPARTAMENTOS_TELARES).'.');
                }
            }],
            'NoTelarId' => 'required|string|max:10',
        ];
    }
}
