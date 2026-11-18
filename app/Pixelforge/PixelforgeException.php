<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * An error response from Pixelforge, e.g. {"error": {"code": "overloaded", ...}}.
 */
final class PixelforgeException extends RuntimeException
{
    private const array RETRYABLE_CODES = ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'];

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            message: $response->json('error.message') ?? "Pixelforge responded with HTTP {$response->status()}.",
            errorCode: $response->json('error.code') ?? 'http_'.$response->status(),
            status: $response->status(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    /** Nothing was created and Pixelforge asked us to come back later. */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true);
    }

    public function isConcurrencyLimit(): bool
    {
        return $this->errorCode === 'concurrency_limit_exceeded';
    }

    /** The Idempotency-Key was already used, with other parameters, so Pixelforge has a generation for it. */
    public function generationAlreadyExists(): bool
    {
        return $this->errorCode === 'idempotency_key_reused';
    }

    /** Documented errors promise nothing was created; an unexpected server error doesn't. */
    public function outcomeIsUnknown(): bool
    {
        return $this->status >= 500 && ! $this->isRetryable();
    }
}
