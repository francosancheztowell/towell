<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

class CalendarioNombreRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return ['Nombre' => 'required|string|max:255'];
    }
}
