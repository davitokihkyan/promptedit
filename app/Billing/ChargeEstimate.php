<?php

namespace App\Billing;

use App\Money\Usd;
use App\Pixelforge\Data\TokenUsage;

/**
 * What a generation will charge the user, before it runs: the likely amount and the most it can be.
 * Amounts include our markup.
 */
final readonly class ChargeEstimate
{
    public function __construct(
        public TokenUsage $expectedUsage,
        public TokenUsage $maximumUsage,
        public Usd $expected,
        public Usd $maximum,
    ) {}
}
