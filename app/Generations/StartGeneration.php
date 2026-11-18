<?php

namespace App\Generations;

use App\Billing\ChargeEstimator;
use App\Billing\InsufficientCreditsException;
use App\Billing\Wallet;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\Data\GenerationParameters;
use App\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

/**
 * Prices a user's request, holds its maximum cost and queues it for Pixelforge.
 */
final readonly class StartGeneration
{
    public function __construct(
        private ChargeEstimator $estimator,
        private Wallet $wallet,
    ) {}

    /**
     * @throws InsufficientCreditsException
     * @throws PixelforgeException|ConnectionException when Pixelforge's prices can't be loaded
     */
    public function handle(User $user, GenerationParameters $parameters): Generation
    {
        // Priced before the transaction, so the HTTP call never runs while the user row is locked.
        $estimate = $this->estimator->estimate($parameters);

        return DB::transaction(function () use ($user, $parameters, $estimate): Generation {
            $this->wallet->hold($user, $estimate->maximum);

            $generation = $user->generations()->create([
                'status' => GenerationStatus::Pending,
                'prompt' => $parameters->prompt,
                'size' => $parameters->size,
                'quality' => $parameters->quality,
                'reference_images' => $parameters->referenceImages,
                'estimated_charge' => $estimate->expected,
                'max_charge' => $estimate->maximum,
            ]);

            SubmitGeneration::dispatch($generation)->afterCommit();

            return $generation;
        });
    }
}
