<?php

namespace App\Casts;

use App\Money\Usd;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Usd as a nanodollar integer column.
 *
 * @implements CastsAttributes<Usd|null, Usd|null>
 */
final class AsUsd implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Usd
    {
        return $value === null ? null : Usd::ofNanos((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value instanceof Usd ? $value->nanos : null;
    }
}
