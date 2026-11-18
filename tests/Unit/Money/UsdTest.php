<?php

namespace Tests\Unit\Money;

use App\Money\Usd;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UsdTest extends TestCase
{
    #[TestWith(['0.046125000', 46_125_000])]
    #[TestWith(['12.50', 12_500_000_000])]
    #[TestWith(['0', 0])]
    #[TestWith(['0.000000001', 1])]
    #[TestWith(['-1.5', -1_500_000_000])]
    public function test_it_parses_exact_decimal_strings(string $decimal, int $nanos): void
    {
        $this->assertSame($nanos, Usd::fromDecimal($decimal)->nanos);
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_anything_that_is_not_an_exact_amount(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Usd::fromDecimal($amount);
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'more than 9 decimals' => ['0.0000000001'],
            'scientific notation' => ['1e-5'],
            'not a number' => ['abc'],
            'empty' => [''],
        ];
    }

    public function test_it_formats_with_nine_decimals(): void
    {
        $this->assertSame('0.046125000', Usd::ofNanos(46_125_000)->toDecimal());
        $this->assertSame('-0.000000625', Usd::ofNanos(-625)->toDecimal());
        $this->assertSame('10.000000000', Usd::ofDollars(10)->toDecimal());
    }

    public function test_arithmetic_is_exact(): void
    {
        $balance = Usd::fromDecimal('10')->minus(Usd::fromDecimal('0.467343750'))->plus(Usd::fromDecimal('0.000000001'));

        $this->assertSame('9.532656251', $balance->toDecimal());
        $this->assertTrue(Usd::zero()->isLessThan($balance));
    }
}
