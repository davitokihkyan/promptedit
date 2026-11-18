<?php

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pixelforge\Data\GenerationParameters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public const int MAX_PROMPT_LENGTH = 4000;

    public const int MAX_REFERENCE_IMAGES = 4;

    /**
     * Pixelforge's own limits, so a request it would reject never leaves the app.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add at most '.self::MAX_REFERENCE_IMAGES.' reference images.',
            'reference_images.*.url' => 'Each reference image must be an http(s) URL.',
        ];
    }

    public function parameters(): GenerationParameters
    {
        /** @var list<string> $referenceImages */
        $referenceImages = array_values($this->validated('reference_images', []));

        return new GenerationParameters(
            prompt: (string) $this->validated('prompt'),
            size: ImageSize::from($this->validated('size')),
            quality: ImageQuality::from($this->validated('quality')),
            referenceImages: $referenceImages,
        );
    }
}
