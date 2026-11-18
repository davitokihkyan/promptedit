<?php

namespace App\Pixelforge;

use App\Pixelforge\Data\DailyUsage;
use App\Pixelforge\Data\GenerationParameters;
use App\Pixelforge\Data\RemoteGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

final readonly class HttpPixelforgeClient implements PixelforgeClient
{
    public function __construct(
        private Factory $http,
        private string $baseUrl,
        private string $apiKey,
    ) {}

    public function createGeneration(GenerationParameters $parameters, string $idempotencyKey, array $metadata): RemoteGeneration
    {
        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', [...$parameters->toArray(), 'metadata' => $metadata]);

        return RemoteGeneration::fromArray($this->decode($response));
    }

    public function getGeneration(string $id): RemoteGeneration
    {
        return RemoteGeneration::fromArray($this->decode($this->request()->get("generations/{$id}")));
    }

    public function pricing(): array
    {
        return $this->decode($this->request()->get('pricing'));
    }

    public function usage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $response = $this->request()->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]);

        return array_values(array_map(DailyUsage::fromArray(...), $this->decode($response)['data']));
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }
}
