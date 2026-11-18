<?php

namespace App\Billing;

use App\Money\Usd;

/**
 * What we charge users on top of Pixelforge's cost.
 */
final readonly class Markup
{
    public function __construct(private int $percent) {}

    /** Rounds up to the next nanodollar; exact at Pixelforge's current rates. */
    public function apply(Usd $cost): Usd
    {
        return Usd::ofNanos(intdiv($cost->nanos * (100 + $this->percent) + 99, 100));
    }
}
