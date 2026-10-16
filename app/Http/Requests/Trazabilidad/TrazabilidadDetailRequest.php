<?php

declare(strict_types=1);

namespace App\Http\Requests\Trazabilidad;

use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Foundation\Http\FormRequest;

final class TrazabilidadDetailRequest extends FormRequest
{
    // El acceso lo exige el router (module.permission:acceso,190).

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Sintaxis de AX: lista con comas, rangos y comodines (ver FiltroAx).
            'flog' => ['nullable', 'string', 'max:2000'],
            'articulo' => ['nullable', 'string', 'max:2000'],
            'tamano' => ['nullable', 'string', 'max:2000'],
            'cliente' => ['nullable', 'array', 'max:100'],
            'cliente.*' => ['string', 'max:100'],
            // Años y meses: lista con comas ('2025,2026'); el VO descarta lo que no sea válido.
            'anio' => ['nullable', 'string', 'max:100'],
            'mes' => ['nullable', 'string', 'max:100'],
            // Detalle Flog con varios Flogs: cuál mostrar.
            'ver' => ['nullable', 'string', 'max:25'],
        ];
    }

    public function filters(): TrazabilidadFilters
    {
        return TrazabilidadFilters::fromArray($this->validated());
    }
}
