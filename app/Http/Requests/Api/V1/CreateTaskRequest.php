<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:51200'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'parent_task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['uuid', 'exists:tasks,id'],
            'assigned_agent_name' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'initial_message' => ['nullable', 'string', 'max:51200'],
        ];
    }
}
