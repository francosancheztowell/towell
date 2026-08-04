<?php

declare(strict_types=1);

namespace App\Http\Requests\Crudo;

final class ListCrudoAuditsRequest extends CrudoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telar' => ['required', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telar' => trim((string) $this->route('telar')),
        ]);
    }
}
