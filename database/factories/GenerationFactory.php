<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use App\Money\Usd;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => ImageSize::Landscape,
            'quality' => ImageQuality::High,
            'reference_images' => ['https://example.com/style.png'],
            'estimated_charge' => Usd::fromDecimal('0.405000000'),
            'max_charge' => Usd::fromDecimal('0.467343750'),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => GenerationStatus::Queued,
            'pixelforge_id' => 'gen_'.Str::random(26),
        ]);
    }
}
