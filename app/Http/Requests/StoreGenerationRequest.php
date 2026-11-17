<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenerationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
            'quality' => ['required', 'in:low,medium,high'],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array{prompt: string, size: string, quality: string, reference_images: array<int, string>}
     */
    public function generationInput(): array
    {
        return [
            'prompt' => $this->string('prompt')->value(),
            'size' => $this->string('size')->value(),
            'quality' => $this->string('quality')->value(),
            'reference_images' => array_values(array_filter($this->array('reference_images'), is_string(...))),
        ];
    }
}
