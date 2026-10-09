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
            'flog' => ['nullable', 'string', 'max:100'],
            'articulo' => ['nullable', 'string', 'max:100'],
            'tamano' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function filters(): TrazabilidadFilters
    {
        return TrazabilidadFilters::fromArray($this->validated());
    }
}
