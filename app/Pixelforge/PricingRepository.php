<?php

namespace App\Pixelforge;

use App\Pixelforge\Data\PricingCatalog;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Rates can change, so they're read from Pixelforge and cached briefly.
 */
final readonly class PricingRepository
{
    private const string CACHE_KEY = 'pixelforge.pricing';

    private const int CACHE_SECONDS = 300;

    public function __construct(
        private PixelforgeClient $client,
        private Cache $cache,
    ) {}

    public function current(): PricingCatalog
    {
        // Plain arrays survive deploys that change our classes; serialized objects don't.
        return PricingCatalog::fromArray(
            $this->cache->remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->client->pricing()),
        );
    }
}
