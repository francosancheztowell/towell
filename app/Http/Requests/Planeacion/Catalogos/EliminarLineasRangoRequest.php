<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

class EliminarLineasRangoRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
            'turnos' => 'sometimes|array|min:1',
            'turnos.*' => 'integer|in:1,2,3',
        ];
    }

    /** @return list<int> */
    public function turnos(): array
    {
        return array_map('intval', (array) $this->input('turnos', [1, 2, 3]));
    }
}
