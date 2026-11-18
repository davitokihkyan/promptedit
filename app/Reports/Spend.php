<?php

namespace App\Reports;

use App\Money\Usd;

/**
 * What users were charged and what Pixelforge charged us for a set of generations.
 */
final readonly class Spend
{
    public function __construct(
        public int $generations,
        public Usd $charged,
        public Usd $providerCost,
    ) {}

    public static function zero(): self
    {
        return new self(0, Usd::zero(), Usd::zero());
    }

    public function plus(self $other): self
    {
        return new self(
            $this->generations + $other->generations,
            $this->charged->plus($other->charged),
            $this->providerCost->plus($other->providerCost),
        );
    }

    public function profit(): Usd
    {
        return $this->charged->minus($this->providerCost);
    }

    /** @return array{generations: int, charged: Usd, provider_cost: Usd, profit: Usd} */
    public function toArray(): array
    {
        return [
            'generations' => $this->generations,
            'charged' => $this->charged,
            'provider_cost' => $this->providerCost,
            'profit' => $this->profit(),
        ];
    }
}
