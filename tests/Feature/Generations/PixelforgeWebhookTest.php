<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pixelforge.webhook_secret' => self::SECRET]);
    }

    public function test_a_succeeded_event_records_the_cost(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->deliver(Pixelforge::event('evt_1', Pixelforge::succeeded($generation->id)))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.381887500', $generation->charge?->toDecimal());
        $this->assertNotNull($generation->image_url);
    }

    public function test_an_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->deliver(Pixelforge::event('evt_1', Pixelforge::succeeded($generation->id)), signature: 't=1,v1=forged')
            ->assertBadRequest();

        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_a_repeated_delivery_is_processed_once(): void
    {
        $generation = Generation::factory()->queued()->create();
        $event = Pixelforge::event('evt_1', Pixelforge::succeeded($generation->id));

        $this->deliver($event)->assertNoContent();
        $this->deliver($event)->assertNoContent();

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame('0.381887500', $generation->refresh()->charge?->toDecimal());
    }

    public function test_events_arriving_out_of_order_end_in_the_right_state(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->deliver(Pixelforge::event('evt_2', Pixelforge::succeeded($generation->id)))->assertNoContent();
        $this->deliver(Pixelforge::event('evt_1', Pixelforge::generation($generation->id, 'processing')))->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
    }

    public function test_a_failed_event_costs_nothing(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->deliver(Pixelforge::event('evt_1', Pixelforge::failed($generation->id)))->assertNoContent();

        $this->assertTrue($generation->refresh()->charge?->isZero());
    }

    public function test_an_event_can_arrive_before_the_submit_request_returns(): void
    {
        $generation = Generation::factory()->create();

        $this->deliver(Pixelforge::event('evt_1', Pixelforge::generation($generation->id, 'processing')))->assertNoContent();

        $this->assertSame(GenerationStatus::Processing, $generation->refresh()->status);
    }

    public function test_a_dashboard_ping_is_acknowledged(): void
    {
        $this->deliver(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => []])->assertNoContent();
    }

    public function test_an_unknown_generation_is_acknowledged_and_logged(): void
    {
        Log::spy();

        $this->deliver(Pixelforge::event('evt_1', Pixelforge::succeeded('00000000-0000-0000-0000-000000000000')))
            ->assertNoContent();

        Log::shouldHaveReceived('warning')->once();
    }

    /** @param array<string, mixed> $event */
    private function deliver(array $event, ?string $signature = null): TestResponse
    {
        $body = (string) json_encode($event);
        $timestamp = now()->getTimestamp();
        $signature ??= "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);

        return $this->call('POST', route('webhooks.pixelforge'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $signature,
        ], content: $body);
    }
}
