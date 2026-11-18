<?php

namespace Tests\Feature\Billing;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Generations\StartGeneration;
use App\Models\CreditPurchase;
use App\Models\Generation;
use App\Models\User;
use App\Money\Usd;
use App\Pixelforge\Data\RemoteGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Pixelforge;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['*/pricing' => Http::response(Pixelforge::pricing())]);
    }

    public function test_starting_a_generation_holds_its_maximum_price(): void
    {
        $user = User::factory()->withCredits(10)->create();

        $this->start($user);

        $this->assertSame('9.532656250', $user->refresh()->balance->toDecimal());
    }

    public function test_success_keeps_only_the_actual_charge(): void
    {
        $user = User::factory()->withCredits(10)->create();
        $generation = $this->start($user);

        $this->complete($generation, Pixelforge::succeeded($generation->id));

        $this->assertSame('9.618112500', $user->refresh()->balance->toDecimal());
    }

    public function test_a_repeated_success_is_charged_once(): void
    {
        $user = User::factory()->withCredits(10)->create();
        $generation = $this->start($user);

        $this->complete($generation, Pixelforge::succeeded($generation->id));
        $this->complete($generation, Pixelforge::succeeded($generation->id));

        $this->assertSame('9.618112500', $user->refresh()->balance->toDecimal());
    }

    public function test_failure_returns_the_whole_hold(): void
    {
        $user = User::factory()->withCredits(10)->create();
        $generation = $this->start($user);

        $this->complete($generation, Pixelforge::failed($generation->id));

        $this->assertSame('10.000000000', $user->refresh()->balance->toDecimal());
    }

    public function test_a_user_who_cannot_cover_the_maximum_is_blocked(): void
    {
        // Enough for the likely price (0.4126) but not the maximum (0.4673).
        $user = User::factory()->create(['balance' => Usd::fromDecimal('0.45')]);

        $this->actingAs($user)
            ->post(route('generations.store'), Pixelforge::docsExampleRequest())
            ->assertSessionHasErrors('credits');

        $this->assertSame(0, Generation::query()->count());
        $this->assertSame('0.450000000', $user->refresh()->balance->toDecimal());
        Queue::assertNothingPushed();
    }

    public function test_buying_credits_adds_them_and_records_the_purchase(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 10])->assertRedirect();

        $this->assertSame('10.000000000', $user->refresh()->balance->toDecimal());
        $this->assertSame(1, CreditPurchase::query()->whereBelongsTo($user)->count());
    }

    public function test_purchases_outlive_a_deleted_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 25]);

        $user->delete();

        $this->assertNull(CreditPurchase::query()->sole()->user_id);
    }

    public function test_only_the_offered_packs_can_be_bought(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 1000])->assertSessionHasErrors('dollars');

        $this->assertTrue($user->refresh()->balance->isZero());
    }

    public function test_balance_always_equals_purchases_minus_charges_minus_holds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 5]);

        $succeeded = $this->start($user);
        $failed = $this->start($user);
        $this->start($user); // still running
        $this->complete($succeeded, Pixelforge::succeeded($succeeded->id));
        $this->complete($failed, Pixelforge::failed($failed->id));

        $purchases = (int) $user->creditPurchases()->sum('amount');
        $charges = (int) $user->generations()->sum('charge');
        $holds = (int) $user->generations()->whereIn('status', GenerationStatus::unfinished())->sum('max_charge');

        $this->assertSame($purchases - $charges - $holds, $user->refresh()->balance->nanos);
    }

    private function start(User $user): Generation
    {
        return $this->app->make(StartGeneration::class)->handle($user, Pixelforge::docsExampleParameters());
    }

    /** @param array<string, mixed> $remote */
    private function complete(Generation $generation, array $remote): void
    {
        $this->app->make(GenerationUpdater::class)->apply($generation, RemoteGeneration::fromArray($remote));
    }
}
