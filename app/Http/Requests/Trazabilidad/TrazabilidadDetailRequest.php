<?php

declare(strict_types=1);

namespace App\Http\Requests\Trazabilidad;

use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TrazabilidadDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCan('acceso', 'Trazabilidad');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'flog' => ['nullable', 'string', 'max:100'],
            'articulo' => ['nullable', 'string', 'max:100'],
            'tamano' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'mes' => ['nullable', 'string', 'max:50'],
            'metrica' => ['nullable', 'string', Rule::in(['cantidad', 'peso'])],
        ];
    }

    public function filters(): TrazabilidadFilters
    {
        return TrazabilidadFilters::fromArray($this->validated());
    }
}
