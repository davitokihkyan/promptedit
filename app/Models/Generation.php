<?php

namespace App\Models;

use App\Casts\AsUsd;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Money\Usd;
use App\Pixelforge\Data\GenerationParameters;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property Usd $estimated_charge
 * @property Usd $max_charge
 * @property Usd|null $provider_cost
 * @property Usd|null $charge
 * @property array<string, string>|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable([
    'pixelforge_id', 'status', 'prompt', 'size', 'quality', 'reference_images',
    'estimated_charge', 'max_charge', 'provider_cost', 'charge', 'rates',
    'input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens',
    'image_url', 'image_url_expires_at', 'error_code', 'error_message', 'completed_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'estimated_charge' => AsUsd::class,
            'max_charge' => AsUsd::class,
            'provider_cost' => AsUsd::class,
            'charge' => AsUsd::class,
            'image_url_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accepted by Pixelforge and still running there.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inFlight(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inFlight());
    }

    public function parameters(): GenerationParameters
    {
        return new GenerationParameters($this->prompt, $this->size, $this->quality, $this->reference_images);
    }

    public function imageIsAvailable(): bool
    {
        return $this->image_url !== null && $this->image_url_expires_at?->isFuture() === true;
    }
}
