<?php

namespace Tests\Unit\Billing;

use App\Billing\Markup;
use App\Money\Usd;
use PHPUnit\Framework\TestCase;

class MarkupTest extends TestCase
{
    public function test_it_adds_the_markup_exactly(): void
    {
        $charge = (new Markup(25))->apply(Usd::fromDecimal('0.373875000'));

        $this->assertSame('0.467343750', $charge->toDecimal());
    }

    public function test_it_never_undercharges_a_fraction_of_a_nanodollar(): void
    {
        $this->assertSame(2, (new Markup(25))->apply(Usd::ofNanos(1))->nanos);
    }

    public function test_free_stays_free(): void
    {
        $this->assertTrue((new Markup(25))->apply(Usd::zero())->isZero());
    }
}
