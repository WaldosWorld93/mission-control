<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ArtifactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateArtifactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:51200'],
            'artifact_type' => ['sometimes', Rule::enum(ArtifactType::class)],
        ];
    }
}
