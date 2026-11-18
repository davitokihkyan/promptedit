<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_submits_with_our_id_as_idempotency_key_and_metadata(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/generations' => Http::response(Pixelforge::generation($generation->id), 202)]);

        $this->runJob($generation);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->id)
            && $request['metadata'] === ['generation_id' => $generation->id]
            && $request['quality'] === 'high');
        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
        $this->assertSame(Pixelforge::pixelforgeId($generation->id), $generation->pixelforge_id);
    }

    public function test_an_overloaded_response_is_retried_after_the_requested_delay(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*' => $this->error('overloaded', 503, retryAfter: 2)]);

        $this->runJob($generation)->assertReleased(delay: 2);

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_backoff_grows_but_never_undercuts_retry_after(): void
    {
        $generation = Generation::factory()->create(['submit_attempts' => 3]);
        Http::fake(['*' => $this->error('rate_limit_exceeded', 429, retryAfter: 30)]);

        $this->runJob($generation)->assertReleased(delay: 30);
    }

    public function test_it_gives_up_after_a_few_retryable_rejections_and_charges_nothing(): void
    {
        $generation = Generation::factory()->create(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS - 1]);
        Http::fake(['*' => $this->error('overloaded', 503, retryAfter: 2)]);

        $this->runJob($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('overloaded', $generation->error_code);
        $this->assertTrue($generation->charge?->isZero());
    }

    public function test_a_blocked_prompt_fails_at_once(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*' => $this->error('content_policy_violation', 400)]);

        $this->runJob($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
    }

    public function test_the_daily_quota_is_not_retried(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*' => $this->error('daily_limit_exceeded', 429)]);

        $this->runJob($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
    }

    public function test_a_timeout_is_never_treated_as_a_failure(): void
    {
        $generation = Generation::factory()->create(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS + 5]);
        Http::fake(['*' => Http::failedConnection()]);

        $this->runJob($generation)->assertReleased();

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_an_unexpected_server_error_is_retried_rather_than_failed(): void
    {
        $generation = Generation::factory()->create(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS + 5]);
        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->runJob($generation)->assertReleased();

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_it_waits_for_a_free_slot_without_calling_pixelforge(): void
    {
        Generation::factory()->queued()->count(3)->create();
        $generation = Generation::factory()->create();
        Http::fake();

        $this->runJob($generation)->assertReleased(delay: 5);

        Http::assertNothingSent();
    }

    public function test_a_concurrency_limit_waits_without_using_up_attempts(): void
    {
        $generation = Generation::factory()->create(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS - 1]);
        Http::fake(['*' => $this->error('concurrency_limit_exceeded', 429, retryAfter: 5)]);

        $this->runJob($generation)->assertReleased(delay: 5);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(SubmitGeneration::MAX_ATTEMPTS - 1, $generation->submit_attempts);
    }

    public function test_a_reused_idempotency_key_is_never_marked_free(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*' => $this->error('idempotency_key_reused', 409)]);

        $this->runJob($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
        $this->assertNull($generation->charge);
    }

    public function test_it_does_nothing_when_a_webhook_already_moved_the_generation_on(): void
    {
        $generation = Generation::factory()->queued()->create();
        Http::fake();

        $this->runJob($generation)->assertNotReleased();

        Http::assertNothingSent();
    }

    private function runJob(Generation $generation): SubmitGeneration
    {
        $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        return $job;
    }

    private function error(string $code, int $status, ?int $retryAfter = null): mixed
    {
        return Http::response(
            ['error' => ['type' => 'test', 'code' => $code, 'message' => "Pixelforge says {$code}."]],
            $status,
            $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter],
        );
    }
}
