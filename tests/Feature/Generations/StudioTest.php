<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    private bool $pricingAvailable = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*/pricing' => fn () => $this->pricingAvailable
            ? Http::response(Pixelforge::pricing())
            : Http::response(null, 503)]);
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(route('generations.index'))->assertRedirect(route('login'));
    }

    public function test_users_see_only_their_own_generations_without_our_costs(): void
    {
        $user = User::factory()->create();
        Generation::factory()->for($user)->create();
        Generation::factory()->create();

        $this->actingAs($user)
            ->get(route('generations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('generations/Index')
                ->has('generations.data', 1, fn (Assert $generation) => $generation
                    ->where('status', 'pending')
                    ->where('estimated_charge', '0.402450000')
                    ->missing('provider_cost')
                    ->etc()));
    }

    public function test_the_history_is_paginated_newest_first(): void
    {
        $user = User::factory()->create();
        $oldest = Generation::factory()->for($user)->create(['created_at' => now()->subDay()]);
        Generation::factory()->for($user)->count(24)->create();

        $this->actingAs($user)
            ->get(route('generations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('generations.data', 24)
                ->where('generations.meta.last_page', 2));

        $this->actingAs($user)
            ->get(route('generations.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('generations.data', 1)
                ->where('generations.data.0.id', $oldest->id));
    }

    public function test_submitting_records_the_estimate_and_queues_the_generation(): void
    {
        Queue::fake();
        $user = User::factory()->withCredits(10)->create();

        $this->actingAs($user)
            ->post(route('generations.store'), Pixelforge::docsExampleRequest())
            ->assertRedirect(route('generations.index'));

        $generation = $user->generations()->sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('0.467343750', $generation->max_charge->toDecimal());
        Queue::assertPushed(SubmitGeneration::class);
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRequests')]
    public function test_requests_pixelforge_would_reject_never_leave_the_app(array $overrides, string $field): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('generations.store'), [...Pixelforge::docsExampleRequest(), ...$overrides])
            ->assertSessionHasErrors($field);

        Queue::assertNothingPushed();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidRequests(): array
    {
        return [
            'missing prompt' => [['prompt' => ''], 'prompt'],
            'prompt too long' => [['prompt' => str_repeat('a', 4001)], 'prompt'],
            'unknown size' => [['size' => '512x512'], 'size'],
            'unknown quality' => [['quality' => 'ultra'], 'quality'],
            'too many references' => [['reference_images' => array_fill(0, 5, 'https://example.com/a.png')], 'reference_images'],
            'non-http reference' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
        ];
    }

    public function test_the_estimate_shows_the_likely_and_the_highest_price(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), Pixelforge::docsExampleRequest())
            ->assertOk()
            ->assertJson([
                'expected' => '0.412562500',
                'maximum' => '0.467343750',
                'tokens' => ['maximum' => ['input_text_tokens' => 115, 'reasoning_tokens' => 8000]],
            ]);
    }

    public function test_a_pricing_outage_shows_a_message_and_creates_nothing(): void
    {
        Queue::fake();
        $this->pricingAvailable = false;

        $this->actingAs(User::factory()->withCredits(10)->create())
            ->post(route('generations.store'), Pixelforge::docsExampleRequest())
            ->assertSessionHasErrors('pixelforge');

        $this->assertSame(0, Generation::query()->count());
    }

    public function test_the_estimate_works_before_a_prompt_is_written(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), ['prompt' => null, 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk()
            ->assertJsonPath('tokens.expected.input_text_tokens', 0);
    }
}
