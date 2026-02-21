<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AgentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(AgentStatus::class)],
            'soul_hash' => ['nullable', 'string', 'size:64'],
            'current_task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'metadata' => ['nullable', 'array'],
            'error' => ['nullable', 'array'],
            'error.type' => ['required_with:error', 'string'],
            'error.task_id' => ['nullable', 'uuid'],
            'error.message' => ['required_with:error', 'string'],
            'error.recoverable' => ['sometimes', 'boolean'],
        ];
    }
}
