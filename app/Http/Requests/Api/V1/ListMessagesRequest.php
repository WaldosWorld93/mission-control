<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'thread_id' => ['sometimes', 'uuid', 'exists:message_threads,id'],
            'project_id' => ['sometimes', 'uuid', 'exists:projects,id'],
            'message_type' => ['sometimes', Rule::enum(MessageType::class)],
            'mentioning' => ['sometimes', 'in:me'],
        ];
    }
}
