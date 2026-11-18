<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class ReconcileGenerationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_generation_whose_webhook_never_came_is_polled(): void
    {
        $generation = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(5)]);
        Http::fake(["*/generations/{$generation->pixelforge_id}" => Http::response(Pixelforge::succeeded($generation->id))]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame('0.381887500', $generation->charge?->toDecimal());
    }

    public function test_recently_updated_generations_are_left_to_their_webhooks(): void
    {
        Generation::factory()->queued()->create(['updated_at' => now()->subSeconds(30)]);
        Http::fake();

        $this->artisan('generations:reconcile')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_generation_still_running_is_checked_again_later(): void
    {
        $generation = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(5)]);
        Http::fake(['*' => Http::response(Pixelforge::generation($generation->id, 'queued'))]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        $this->assertTrue($generation->refresh()->updated_at->isAfter(now()->subMinute()));
    }

    public function test_one_bad_generation_does_not_stop_the_others(): void
    {
        $missing = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(10)]);
        $broken = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(9)]);
        $fine = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(8)]);
        Http::fake([
            "*/generations/{$missing->pixelforge_id}" => Http::response(['error' => ['code' => 'not_found', 'message' => 'No such generation.']], 404),
            // Succeeded without usage: apply() throws.
            "*/generations/{$broken->pixelforge_id}" => Http::response([...Pixelforge::succeeded($broken->id), 'usage' => null]),
            "*/generations/{$fine->pixelforge_id}" => Http::response(Pixelforge::succeeded($fine->id)),
        ]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $fine->refresh()->status);
        // Both failures moved to the back of the line, so they can't hog the batch.
        $this->assertTrue($missing->refresh()->updated_at->isAfter(now()->subMinute()));
        $this->assertTrue($broken->refresh()->updated_at->isAfter(now()->subMinute()));
    }

    public function test_an_abandoned_submission_is_sent_again(): void
    {
        Queue::fake();
        $generation = Generation::factory()->create(['updated_at' => now()->subHour()]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
    }
}
