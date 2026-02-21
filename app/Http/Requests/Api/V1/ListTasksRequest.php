<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'uuid', 'exists:projects,id'],
            'status' => [
                'sometimes',
                Rule::enum(TaskStatus::class),
                Rule::notIn([TaskStatus::Blocked->value]),
            ],
            'assigned_to' => ['sometimes', Rule::in(['me'])],
        ];
    }
}
