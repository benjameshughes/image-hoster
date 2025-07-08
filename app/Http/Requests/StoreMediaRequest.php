<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media.*' => 'required|file|max:102400', // 100MB max
            'alt_text' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_shareable' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'media.*.required' => 'Please select at least one file to upload.',
            'media.*.file' => 'Each upload must be a valid file.',
            'media.*.max' => 'Each file must not exceed 100MB.',
            'alt_text.max' => 'Alt text must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'tags.max' => 'You can add a maximum of 10 tags.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
        ];
    }
}
