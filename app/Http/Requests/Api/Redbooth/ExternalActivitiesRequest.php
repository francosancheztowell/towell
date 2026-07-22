<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Redbooth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExternalActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['sometimes', 'string', 'regex:/^(id|created_at|updated_at|position)-(ASC|DESC)$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'organization_id' => ['sometimes', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'target_type' => ['sometimes', 'string', Rule::in([
                'Task', 'Conversation', 'Person', 'Comment', 'Upload', 'Page', 'TaskList', 'Project',
            ])],
            'created_from' => ['sometimes', 'integer', 'min:0'],
            'created_to' => ['sometimes', 'integer', 'min:0', 'gte:created_from'],
        ];
    }
}
