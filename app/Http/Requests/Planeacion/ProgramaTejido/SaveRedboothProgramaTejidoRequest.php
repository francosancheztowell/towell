<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveRedboothProgramaTejidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'string', Rule::in(['programa', 'catcodificados'])],
            'req_programa_tejido_id' => ['required_unless:source,catcodificados', 'nullable', 'integer', 'min:1'],
            'cat_codificados_id' => ['required_if:source,catcodificados', 'nullable', 'integer', 'min:1'],
            'redbooth_task_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
