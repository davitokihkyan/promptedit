<?php

namespace Tests\Feature\Reports;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpendReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 12:00:00');
    }

    public function test_only_admins_can_see_it(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.spend'))->assertForbidden();
    }

    public function test_it_totals_each_day_and_user_and_matches_pixelforge(): void
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $alan = User::factory()->create(['name' => 'Alan']);
        Generation::factory()->for($ada)->succeeded('0.049297500')->create(['completed_at' => '2026-11-17 09:00:00']);
        Generation::factory()->for($ada)->succeeded('0.100000000')->create(['completed_at' => '2026-11-17 23:59:59']);
        Generation::factory()->for($alan)->succeeded('0.305510000')->create(['completed_at' => '2026-11-16 08:00:00']);
        Generation::factory()->for($alan)->failed()->create(['completed_at' => '2026-11-16 09:00:00']);
        $this->fakeUsage(['2026-11-16' => [1, '0.305510000'], '2026-11-17' => [2, '0.149297500']]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.spend'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/SpendReport')
                ->where('report.totals.provider_cost', '0.454807500')
                ->where('report.totals.charged', '0.568509375')
                ->where('report.totals.profit', '0.113701875')
                ->where('report.days.0.date', '2026-11-17')
                ->where('report.days.0.generations', 2)
                ->where('report.days.0.users.0.name', 'Ada')
                ->where('report.days.0.users.0.id', $ada->id)
                ->where('report.days.0.users.0.email', $ada->email)
                ->where('report.days.0.pixelforge.matches', true)
                ->where('report.days.1.date', '2026-11-16')
                ->where('report.days.1.generations', 1)
                ->where('report.days.1.pixelforge.matches', true));
    }

    public function test_a_day_pixelforge_billed_but_we_did_not_record_is_flagged(): void
    {
        $this->fakeUsage(['2026-11-17' => [1, '0.049297500']]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.spend'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.days.0.date', '2026-11-17')
                ->where('report.days.0.generations', 0)
                ->where('report.days.0.pixelforge.matches', false));
    }

    public function test_the_report_still_renders_when_pixelforge_is_down(): void
    {
        Http::fake(['*/usage*' => Http::response(null, 503)]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.spend'))
            ->assertInertia(fn (Assert $page) => $page->where('report.pixelforge_available', false));
    }

    /** @param array<string, array{int, string}> $days */
    private function fakeUsage(array $days): void
    {
        $data = [];
        foreach ($days as $date => [$generations, $cost]) {
            $data[] = ['date' => $date, 'generations' => $generations, 'cost_usd' => $cost];
        }

        Http::fake(['*/usage*' => Http::response(['object' => 'usage', 'data' => $data])]);
    }
}
