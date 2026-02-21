<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:51200'],
            'thread_id' => ['nullable', 'uuid', 'exists:message_threads,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message_type' => ['sometimes', Rule::enum(MessageType::class)],
        ];
    }
}
