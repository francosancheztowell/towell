<?php

declare(strict_types=1);

namespace App\Http\Requests\Crudo;

final class StoreCrudoAuditWithStopRequest extends StoreCrudoAuditRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['defectos'] = ['required', 'array', 'min:1', 'max:5'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'defectos.required' => 'Captura al menos un defecto para generar el paro.',
            'defectos.min' => 'Captura al menos un defecto con piezas para generar el paro.',
        ];
    }
}
