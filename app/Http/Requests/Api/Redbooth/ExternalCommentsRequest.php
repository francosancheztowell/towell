<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Redbooth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExternalCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::in(['Task', 'Conversation'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'organization_id' => ['sometimes', 'integer', 'min:1'],
            'order' => ['sometimes', 'string', 'regex:/^(id|created_at|updated_at|position)-(ASC|DESC)$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'created_from' => ['sometimes', 'integer', 'min:0'],
            'created_to' => ['sometimes', 'integer', 'min:0', 'gte:created_from'],
        ];
    }
}
