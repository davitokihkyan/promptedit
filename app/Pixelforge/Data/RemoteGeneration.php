<?php

namespace App\Pixelforge\Data;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

/**
 * A generation as Pixelforge reports it, from an API response or a webhook.
 */
final readonly class RemoteGeneration
{
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public ?string $localId,
        public TokenRates $rates,
        public ?TokenUsage $usage = null,
        public ?string $imageUrl = null,
        public ?CarbonImmutable $imageUrlExpiresAt = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?CarbonImmutable $completedAt = null,
    ) {}

    /** @param array<string, mixed> $generation */
    public static function fromArray(array $generation): self
    {
        return new self(
            id: $generation['id'],
            status: GenerationStatus::from($generation['status']),
            localId: $generation['metadata']['generation_id'] ?? null,
            rates: TokenRates::fromArray($generation['rates']),
            usage: isset($generation['usage']) ? TokenUsage::fromArray($generation['usage']) : null,
            imageUrl: $generation['image_url'] ?? null,
            imageUrlExpiresAt: self::date($generation['image_url_expires_at'] ?? null),
            errorCode: $generation['error']['code'] ?? null,
            errorMessage: $generation['error']['message'] ?? null,
            completedAt: self::date($generation['completed_at'] ?? null),
        );
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
