<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\Models\Planeacion\ReqCalendarioTab;
use Illuminate\Validation\Rule;

/**
 * Alta de calendario (con plantilla de turnos opcional) y edición masiva
 * (PUT /calendarios/{calendario}/masivo, donde la plantilla es obligatoria y no se valida la llave).
 */
class CalendarioRequest extends CatalogoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('planeacion.calendarios.update.masivo')) {
            return [
                'Nombre' => 'required|string|max:255',
                'FechaInicial' => 'required|date',
                'FechaFinal' => 'required|date',
                'Turnos' => 'required|array',
            ];
        }

        return [
            'CalendarioId' => ['required', 'string', 'max:20', Rule::unique(ReqCalendarioTab::class, 'CalendarioId')],
            'Nombre' => 'required|string|max:255',
            'FechaInicial' => 'required_with:Turnos|date',
            'FechaFinal' => 'required_with:Turnos|date',
            'Turnos' => 'sometimes|array',
        ];
    }
}
