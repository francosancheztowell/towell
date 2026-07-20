<?php

declare(strict_types=1);

namespace App\Http\Requests\Integraciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RedboothFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'min:1'],
            'target_id' => ['required', 'integer', 'min:1'],
            'target_type' => ['required', 'string', Rule::in(['Task', 'Conversation'])],
            'type' => ['sometimes', 'string', Rule::in(['file'])],
            'order' => ['sometimes', 'string', 'regex:/^(id|created_at|updated_at|position)-(ASC|DESC)$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
