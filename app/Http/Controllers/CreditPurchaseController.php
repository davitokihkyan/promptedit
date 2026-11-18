<?php

namespace App\Http\Controllers;

use App\Billing\Wallet;
use App\Models\User;
use App\Money\Usd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class CreditPurchaseController extends Controller
{
    /** A fake checkout: credits are added at once and nothing is charged. */
    public function store(Request $request, Wallet $wallet): RedirectResponse
    {
        $dollars = (int) $request->validate([
            'dollars' => ['required', 'integer', Rule::in(config('billing.credit_packs'))],
        ])['dollars'];

        /** @var User $user */
        $user = $request->user();
        $wallet->deposit($user, Usd::ofDollars($dollars));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \${$dollars} in credits."]);

        return back();
    }
}
