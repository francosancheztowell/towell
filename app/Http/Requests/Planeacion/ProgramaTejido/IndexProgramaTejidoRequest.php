<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexProgramaTejidoRequest extends FormRequest
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
            'sort' => ['sometimes', Rule::in([
                'en_proceso',
                'salon',
                'telar',
                'posicion',
                'orden_produccion',
                'producto',
                'item_id',
                'total_pedido',
                'produccion',
                'saldo_pedido',
                'fecha_inicio',
                'fecha_final',
                'prioridad',
            ])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.salon' => ['sometimes', 'nullable', 'string', 'max:10'],
            'filters.telar' => ['sometimes', 'nullable', 'string', 'max:10'],
            'filters.en_proceso' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
