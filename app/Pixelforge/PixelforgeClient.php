<?php

namespace App\Pixelforge;

use App\Pixelforge\Data\DailyUsage;
use App\Pixelforge\Data\GenerationParameters;
use App\Pixelforge\Data\RemoteGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;

interface PixelforgeClient
{
    /**
     * @param  array<string, string>  $metadata
     *
     * @throws PixelforgeException when Pixelforge rejects the request
     * @throws ConnectionException when we can't tell whether it was created
     */
    public function createGeneration(GenerationParameters $parameters, string $idempotencyKey, array $metadata): RemoteGeneration;

    /** @throws PixelforgeException|ConnectionException */
    public function getGeneration(string $id): RemoteGeneration;

    /**
     * The raw GET /v1/pricing body, so it can be cached as plain data.
     *
     * @return array<string, mixed>
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function pricing(): array;

    /**
     * What Pixelforge billed this API key, per UTC day (inclusive, at most 92 days).
     *
     * @return list<DailyUsage>
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function usage(CarbonImmutable $from, CarbonImmutable $to): array;
}
