<?php

namespace App\Billing;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Money\Usd;
use Illuminate\Support\Facades\DB;

/**
 * A user's prepaid credits. A generation holds its maximum price up front and settles at the actual price.
 */
final readonly class Wallet
{
    /**
     * Call inside a transaction; the row lock stops parallel requests overspending.
     *
     * @throws InsufficientCreditsException
     */
    public function hold(User $user, Usd $amount): void
    {
        $balance = User::query()->lockForUpdate()->findOrFail($user->id)->balance;

        if ($balance->isLessThan($amount)) {
            throw new InsufficientCreditsException($amount, $balance);
        }

        $this->adjust($user->id, $amount->negated());
    }

    /** Refunds the hold minus the actual charge; a failed generation gets it all back. */
    public function settle(Generation $generation): void
    {
        // The user deleted their account, so there's no balance left to refund.
        if ($generation->user_id === null) {
            return;
        }

        $this->adjust($generation->user_id, $generation->max_charge->minus($generation->charge ?? Usd::zero()));
    }

    public function deposit(User $user, Usd $amount): void
    {
        DB::transaction(function () use ($user, $amount): void {
            $user->creditPurchases()->create(['amount' => $amount]);
            $this->adjust($user->id, $amount);
        });
    }

    /** What the user's unfinished generations are holding. */
    public function held(User $user): Usd
    {
        $nanos = $user->generations()
            ->whereIn('status', GenerationStatus::unfinished())
            ->sum('max_charge');

        return Usd::ofNanos((int) $nanos);
    }

    private function adjust(int $userId, Usd $amount): void
    {
        // A relative update, so concurrent settlements never overwrite each other.
        User::query()->whereKey($userId)->increment('balance', $amount->nanos);
    }
}
