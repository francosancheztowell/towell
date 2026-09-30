<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

/** Línea de calendario: alta (con CalendarioId) y edición. Solo turnos 1–3 (capacidad de máquina). */
class LineaCalendarioRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        $reglas = [
            'FechaInicio' => 'required|date',
            'FechaFin' => 'required|date',
            'HorasTurno' => 'required|numeric|min:0',
            'Turno' => 'required|integer|in:1,2,3',
        ];

        return $this->isMethod('post') ? ['CalendarioId' => 'required|string|max:20'] + $reglas : $reglas;
    }
}
