<?php

namespace Tests\Unit\Casts;

use App\Casts\AsUsd;
use App\Models\User;
use App\Money\Usd;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AsUsdTest extends TestCase
{
    public function test_it_stores_whole_nanodollars(): void
    {
        $this->assertSame(1_500_000_000, (new AsUsd)->set(new User, 'balance', Usd::fromDecimal('1.5'), []));
        $this->assertNull((new AsUsd)->set(new User, 'balance', null, []));
    }

    public function test_a_bare_number_is_refused_rather_than_guessed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AsUsd)->set(new User, 'balance', 5, []);
    }
}
