<?php

namespace App\Money;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact US dollar amount in whole nanodollars (1e-9 USD), the precision Pixelforge bills in.
 */
final readonly class Usd implements JsonSerializable, Stringable
{
    private const int SCALE = 9;

    private const int NANOS_PER_DOLLAR = 1_000_000_000;

    private function __construct(public int $nanos) {}

    public static function ofNanos(int $nanos): self
    {
        return new self($nanos);
    }

    public static function ofDollars(int $dollars): self
    {
        return new self($dollars * self::NANOS_PER_DOLLAR);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Parses an exact decimal string such as "0.046125000" or "12.50". */
    public static function fromDecimal(string $amount): self
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,9}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("[{$amount}] is not an exact USD amount.");
        }

        $nanos = (int) $matches[2] * self::NANOS_PER_DOLLAR
            + (int) str_pad($matches[3] ?? '', self::SCALE, '0');

        return new self($matches[1] === '-' ? -$nanos : $nanos);
    }

    public function plus(self $other): self
    {
        return new self($this->nanos + $other->nanos);
    }

    public function minus(self $other): self
    {
        return new self($this->nanos - $other->nanos);
    }

    public function isLessThan(self $other): bool
    {
        return $this->nanos < $other->nanos;
    }

    public function isZero(): bool
    {
        return $this->nanos === 0;
    }

    /** Nine decimal places, the same format Pixelforge uses. */
    public function toDecimal(): string
    {
        $absolute = abs($this->nanos);

        return sprintf(
            '%s%d.%09d',
            $this->nanos < 0 ? '-' : '',
            intdiv($absolute, self::NANOS_PER_DOLLAR),
            $absolute % self::NANOS_PER_DOLLAR,
        );
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
