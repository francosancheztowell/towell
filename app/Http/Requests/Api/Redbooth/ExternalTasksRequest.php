<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Redbooth;

use Illuminate\Foundation\Http\FormRequest;

final class ExternalTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'min:1'],
            'order' => ['sometimes', 'string', 'regex:/^(id|created_at|updated_at|position)-(ASC|DESC)$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'organization_id' => ['sometimes', 'integer', 'min:1'],
            'task_list_id' => ['sometimes', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'assigned_user_id' => ['sometimes', 'integer', 'min:1'],
            'assigned' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
