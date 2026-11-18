<?php

namespace Database\Factories;

use App\Billing\Markup;
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
            // The real estimate for these parameters at the docs' rates.
            'estimated_charge' => Usd::fromDecimal('0.402450000'),
            'max_charge' => Usd::fromDecimal('0.457150000'),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => GenerationStatus::Queued,
            'pixelforge_id' => 'gen_'.Str::random(26),
        ]);
    }

    /** Finished and charged at the provider cost plus our markup. */
    public function succeeded(string $providerCost = '0.305510000'): static
    {
        $cost = Usd::fromDecimal($providerCost);

        return $this->queued()->state(fn () => [
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => $cost,
            'charge' => app(Markup::class)->apply($cost),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->queued()->state(fn () => [
            'status' => GenerationStatus::Failed,
            'provider_cost' => Usd::zero(),
            'charge' => Usd::zero(),
            'error_code' => 'generation_failed',
            'completed_at' => now(),
        ]);
    }
}
