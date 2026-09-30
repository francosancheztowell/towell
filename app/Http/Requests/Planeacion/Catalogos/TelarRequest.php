<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

class TelarRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'SalonTejidoId' => 'required|string|max:20',
            'NoTelarId' => 'required|string|max:10',
            'Nombre' => 'nullable|string|max:30',
            'Grupo' => 'nullable|string|max:30',
        ];
    }
}
