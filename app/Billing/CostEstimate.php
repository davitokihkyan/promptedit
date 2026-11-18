<?php

namespace App\Billing;

use App\Money\Usd;
use App\Pixelforge\Data\TokenUsage;

/**
 * Up-front price for a generation; amounts include our markup.
 */
final readonly class CostEstimate
{
    public function __construct(
        public TokenUsage $expectedUsage,
        public TokenUsage $maximumUsage,
        public Usd $expected,
        public Usd $maximum,
    ) {}
}
