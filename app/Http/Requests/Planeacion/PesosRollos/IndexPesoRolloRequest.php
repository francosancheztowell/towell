<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\PesosRollos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPesoRolloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['item_id', 'item_name', 'invent_size_id', 'peso_rollo'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.item_id' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.item_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filters.invent_size_id' => ['sometimes', 'nullable', 'string', 'max:10'],
            'filters.peso_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.peso_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:filters.peso_min'],
        ];
    }
}
