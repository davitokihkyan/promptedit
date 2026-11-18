<?php

namespace App\Pixelforge\Data;

use App\Money\Usd;

/**
 * One day of GET /v1/usage: what Pixelforge billed, by the UTC day generations completed.
 */
final readonly class DailyUsage
{
    public function __construct(
        public string $date,
        public int $generations,
        public Usd $cost,
    ) {}

    /** @param array{date: string, generations: int, cost_usd: string} $day */
    public static function fromArray(array $day): self
    {
        return new self($day['date'], $day['generations'], Usd::fromDecimal($day['cost_usd']));
    }
}
