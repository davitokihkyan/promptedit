<?php

namespace App\Billing;

use App\Money\Usd;
use RuntimeException;

final class InsufficientCredits extends RuntimeException
{
    public function __construct(public readonly Usd $needed, public readonly Usd $available)
    {
        parent::__construct("Needed {$needed} but only {$available} is available.");
    }
}
