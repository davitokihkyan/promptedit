<?php

namespace App\Http\Middleware;

use App\Billing\Wallet;
use App\Models\User;
use App\Money\Usd;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly Wallet $wallet) {}

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'credits' => fn () => $this->credits($request),
        ];
    }

    /** @return array{balance: Usd, held: Usd, packs: list<int>}|null */
    private function credits(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return [
            'balance' => $user->balance,
            'held' => $this->wallet->held($user),
            'packs' => config('billing.credit_packs'),
        ];
    }
}
