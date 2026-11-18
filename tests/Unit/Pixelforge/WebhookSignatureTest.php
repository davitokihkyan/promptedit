<?php

namespace Tests\Unit\Pixelforge;

use App\Pixelforge\WebhookSignature;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    private const string PAYLOAD = '{"id":"evt_1","type":"generation.succeeded"}';

    private WebhookSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->signature = new WebhookSignature('whsec_test', 300);
    }

    public function test_a_correctly_signed_payload_is_accepted(): void
    {
        $this->assertTrue($this->signature->verify(self::PAYLOAD, $this->header(self::PAYLOAD)));
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $this->assertFalse($this->signature->verify('{"id":"evt_2"}', $this->header(self::PAYLOAD)));
    }

    public function test_a_payload_signed_with_another_secret_is_rejected(): void
    {
        $header = $this->header(self::PAYLOAD, new WebhookSignature('whsec_other', 300));

        $this->assertFalse($this->signature->verify(self::PAYLOAD, $header));
    }

    public function test_an_old_timestamp_is_rejected_to_stop_replays(): void
    {
        $header = $this->header(self::PAYLOAD, timestamp: now()->subSeconds(301)->getTimestamp());

        $this->assertFalse($this->signature->verify(self::PAYLOAD, $header));
    }

    public function test_a_missing_header_is_rejected(): void
    {
        $this->assertFalse($this->signature->verify(self::PAYLOAD, ''));
    }

    public function test_nothing_is_accepted_without_a_configured_secret(): void
    {
        $unconfigured = new WebhookSignature('', 300);

        $this->assertFalse($unconfigured->verify(self::PAYLOAD, $this->header(self::PAYLOAD, $unconfigured)));
    }

    private function header(string $payload, ?WebhookSignature $signer = null, ?int $timestamp = null): string
    {
        $timestamp ??= now()->getTimestamp();

        return "t={$timestamp},v1=".($signer ?? $this->signature)->sign($timestamp, $payload);
    }
}
