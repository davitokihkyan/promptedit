<?php

namespace App\Pixelforge;

use Illuminate\Support\Facades\Date;

/**
 * Pixelforge-Signature: "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">".
 */
final readonly class WebhookSignature
{
    public function __construct(
        private string $secret,
        private int $toleranceSeconds,
    ) {}

    public function verify(string $payload, string $header): bool
    {
        $parts = $this->parse($header);
        $timestamp = (int) ($parts['t'] ?? 0);

        // An old timestamp may be a replayed request.
        if ($this->secret === '' || abs(Date::now()->getTimestamp() - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->sign($timestamp, $payload), $parts['v1'] ?? '');
    }

    public function sign(int $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
    }

    /** @return array<string, string> */
    private function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }
}
