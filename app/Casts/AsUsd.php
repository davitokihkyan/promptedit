<?php

namespace App\Casts;

use App\Money\Usd;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores Usd as a nanodollar integer column. Eloquent passes set() whatever was assigned,
 * so set() checks its own input.
 *
 * @implements CastsAttributes<Usd|null, mixed>
 */
final class AsUsd implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Usd
    {
        return $value === null ? null : Usd::ofNanos((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        // A bare number is ambiguous (dollars or nanodollars?), so refuse it instead of guessing.
        if ($value !== null && ! $value instanceof Usd) {
            throw new InvalidArgumentException("[{$key}] must be a Usd amount, not ".get_debug_type($value).'.');
        }

        return $value?->nanos;
    }
}
