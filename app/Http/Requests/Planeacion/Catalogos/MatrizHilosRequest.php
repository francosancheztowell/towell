<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

class MatrizHilosRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'Hilo' => 'required|string|max:30',
            'Calibre' => 'nullable|numeric',
            'Calibre2' => 'nullable|numeric',
            'CalibreAX' => 'nullable|string|max:20',
            'Fibra' => 'nullable|string|max:30',
            'CodColor' => 'nullable|string|max:10',
            'NombreColor' => 'nullable|string|max:60',
            'N1' => 'nullable|numeric',
            'N2' => 'nullable|numeric',
        ];
    }
}
