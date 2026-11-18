<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Models\Generation;
use App\Pixelforge\Data\RemoteGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class GenerationUpdaterTest extends TestCase
{
    use RefreshDatabase;

    private GenerationUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updater = $this->app->make(GenerationUpdater::class);
    }

    public function test_success_records_the_exact_cost_plus_markup(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->updater->apply($generation, RemoteGeneration::fromArray(Pixelforge::succeeded($generation->id)));

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.305510000', $generation->provider_cost?->toDecimal());
        $this->assertSame('0.381887500', $generation->charge?->toDecimal());
        $this->assertSame(3184, $generation->reasoning_tokens);
        $this->assertTrue($generation->completed_at?->equalTo('2026-11-04T14:03:29Z'));
    }

    public function test_the_same_update_twice_is_recorded_once(): void
    {
        $generation = Generation::factory()->queued()->create();
        $succeeded = RemoteGeneration::fromArray(Pixelforge::succeeded($generation->id));

        $this->assertTrue($this->updater->apply($generation, $succeeded));
        $this->assertFalse($this->updater->apply($generation, $succeeded));
        $this->assertSame('0.381887500', $generation->charge?->toDecimal());
    }

    public function test_a_late_processing_update_never_moves_a_finished_generation_back(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->updater->apply($generation, RemoteGeneration::fromArray(Pixelforge::succeeded($generation->id)));
        $moved = $this->updater->apply($generation, RemoteGeneration::fromArray(Pixelforge::generation($generation->id, 'processing')));

        $this->assertFalse($moved);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
    }

    public function test_a_webhook_can_arrive_before_our_submit_request_returns(): void
    {
        $generation = Generation::factory()->create();
        $remote = RemoteGeneration::fromArray(Pixelforge::generation($generation->id, 'processing'));

        $this->updater->apply($generation, $remote);

        $this->assertSame(GenerationStatus::Processing, $generation->status);
        $this->assertSame($remote->id, $generation->pixelforge_id);
    }

    public function test_a_failed_generation_is_free(): void
    {
        $generation = Generation::factory()->queued()->create();

        $this->updater->apply($generation, RemoteGeneration::fromArray(Pixelforge::failed($generation->id)));

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertTrue($generation->charge?->isZero());
        $this->assertTrue($generation->provider_cost?->isZero());
        $this->assertSame('internal_error', $generation->error_code);
    }

    public function test_a_rejected_submission_is_free(): void
    {
        $generation = Generation::factory()->create();

        $this->updater->reject($generation, 'content_policy_violation', 'The prompt was rejected.');

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertTrue($generation->charge?->isZero());
        $this->assertNotNull($generation->completed_at);
    }

    public function test_a_finished_generation_cannot_be_rejected(): void
    {
        $generation = Generation::factory()->queued()->create();
        $this->updater->apply($generation, RemoteGeneration::fromArray(Pixelforge::succeeded($generation->id)));

        $this->assertFalse($this->updater->reject($generation, 'overloaded', 'Busy.'));
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
    }
}
