<?php

namespace App\Pixelforge\Data;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * What the user asked for; the body of POST /v1/generations.
 */
final readonly class GenerationParameters
{
    /** @param list<string> $referenceImages */
    public function __construct(
        public string $prompt,
        public ImageSize $size,
        public ImageQuality $quality,
        public array $referenceImages = [],
    ) {}

    /** @return array{prompt: string, size: string, quality: string, reference_images: list<string>} */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->referenceImages,
        ];
    }
}
