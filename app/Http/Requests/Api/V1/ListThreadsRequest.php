<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListThreadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_resolved' => ['sometimes', 'boolean'],
            'task_id' => ['sometimes', 'uuid', 'exists:tasks,id'],
        ];
    }
}
