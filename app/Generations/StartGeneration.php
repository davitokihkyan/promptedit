<?php

namespace App\Generations;

use App\Billing\CostEstimator;
use App\Billing\InsufficientCredits;
use App\Billing\Wallet;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\Data\GenerationParameters;
use Illuminate\Support\Facades\DB;

/**
 * Prices a user's request, holds its maximum cost and queues it for Pixelforge.
 */
final readonly class StartGeneration
{
    public function __construct(
        private CostEstimator $estimator,
        private Wallet $wallet,
    ) {}

    /** @throws InsufficientCredits */
    public function handle(User $user, GenerationParameters $parameters): Generation
    {
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
