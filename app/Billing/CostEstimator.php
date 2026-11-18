<?php

namespace App\Billing;

use App\Pixelforge\Data\GenerationParameters;
use App\Pixelforge\PricingRepository;

final readonly class CostEstimator
{
    public function __construct(
        private PricingRepository $pricing,
        private Markup $markup,
    ) {}

    /** What the user will likely pay, and the most they can pay. */
    public function estimate(GenerationParameters $parameters): CostEstimate
    {
        $catalog = $this->pricing->current();
        $expectedUsage = $catalog->expectedUsage($parameters);
        $maximumUsage = $catalog->maximumUsage($parameters);

        return new CostEstimate(
            expectedUsage: $expectedUsage,
            maximumUsage: $maximumUsage,
            expected: $this->markup->apply($catalog->rates->costOf($expectedUsage)),
            maximum: $this->markup->apply($catalog->rates->costOf($maximumUsage)),
        );
    }
}
