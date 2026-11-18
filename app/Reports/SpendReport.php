<?php

namespace App\Reports;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Money\Usd;
use App\Pixelforge\Data\DailyUsage;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;

/**
 * Spend per UTC day and per user, checked against Pixelforge's own usage report.
 */
final readonly class SpendReport
{
    public function __construct(private PixelforgeClient $pixelforge) {}

    /** @return array{days: list<array<string, mixed>>, totals: array<string, mixed>, pixelforge_available: bool} */
    public function forPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spend = $this->spendByDayAndUser($from, $to);
        $usage = $this->pixelforgeUsage($from, $to);
        $users = User::query()->whereIn('id', $spend->pluck('user_id')->filter())->get(['id', 'name', 'email'])->keyBy('id');

        $days = $spend->groupBy('date')
            ->union(collect($usage)->filter(fn (DailyUsage $day) => $day->generations > 0)->map(fn () => collect()))
            ->sortKeysDesc()
            ->map(fn (Collection $rows, string $date) => $this->day($date, $rows, $usage[$date] ?? null, $users));

        $totals = $spend->reduce(fn (Spend $total, array $row) => $total->plus($row['spend']), Spend::zero());

        return [
            'days' => array_values($days->all()),
            'totals' => $totals->toArray(),
            'pixelforge_available' => $usage !== null,
        ];
    }

    /**
     * Billed on the UTC day the generation completed, the same way Pixelforge reports it.
     *
     * @return Collection<int, array{date: string, user_id: int|null, spend: Spend}>
     */
    private function spendByDayAndUser(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Generation::query()
            ->toBase()
            ->selectRaw('DATE(completed_at) as date, user_id, COUNT(*) as generations, SUM(charge) as charged, SUM(provider_cost) as provider_cost')
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupByRaw('DATE(completed_at), user_id')
            ->get()
            ->map(fn (object $row) => [
                'date' => (string) $row->date,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'spend' => new Spend((int) $row->generations, Usd::ofNanos((int) $row->charged), Usd::ofNanos((int) $row->provider_cost)),
            ]);
    }

    /** @return array<string, DailyUsage>|null keyed by date; null when Pixelforge can't be reached */
    private function pixelforgeUsage(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        try {
            return collect($this->pixelforge->usage($from, $to))->keyBy('date')->all();
        } catch (PixelforgeException|ConnectionException $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  Collection<int, array{date: string, user_id: int|null, spend: Spend}>  $rows
     * @param  Collection<int, User>  $users
     * @return array<string, mixed>
     */
    private function day(string $date, Collection $rows, ?DailyUsage $usage, Collection $users): array
    {
        $total = $rows->reduce(fn (Spend $sum, array $row) => $sum->plus($row['spend']), Spend::zero());

        return [
            'date' => $date,
            ...$total->toArray(),
            'users' => $rows->map(fn (array $row) => [
                'id' => $row['user_id'],
                'name' => $users->get($row['user_id'])->name ?? 'Deleted user',
                'email' => $users->get($row['user_id'])?->email,
                ...$row['spend']->toArray(),
            ])->sortByDesc(fn (array $user) => $user['charged']->nanos)->values()->all(),
            'pixelforge' => $usage === null ? null : [
                'generations' => $usage->generations,
                'cost' => $usage->cost,
                'matches' => $usage->generations === $total->generations && $usage->cost->nanos === $total->providerCost->nanos,
            ],
        ];
    }
}
