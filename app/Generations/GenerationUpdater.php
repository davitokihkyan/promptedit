<?php

namespace App\Generations;

use App\Billing\Markup;
use App\Billing\Wallet;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Money\Usd;
use App\Pixelforge\Data\RemoteGeneration;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

/**
 * The only place a generation's status and cost change.
 */
final readonly class GenerationUpdater
{
    public function __construct(
        private Markup $markup,
        private Wallet $wallet,
    ) {}

    /** Applies what Pixelforge reports. Returns false for duplicate or out-of-order updates. */
    public function apply(Generation $generation, RemoteGeneration $remote): bool
    {
        return $this->moveTo($generation, $remote->status, [
            'pixelforge_id' => $remote->id,
            'rates' => $remote->rates->toArray(),
            ...match ($remote->status) {
                GenerationStatus::Succeeded => $this->successAttributes($remote),
                GenerationStatus::Failed => $this->failureAttributes($remote->errorCode, $remote->errorMessage, $remote->completedAt),
                default => [],
            },
        ]);
    }

    /** Pixelforge refused the request, so nothing was created and nothing is owed. */
    public function reject(Generation $generation, string $errorCode, string $errorMessage): bool
    {
        return $this->moveTo($generation, GenerationStatus::Failed, $this->failureAttributes($errorCode, $errorMessage, now()));
    }

    /** @return array<string, mixed> */
    private function successAttributes(RemoteGeneration $remote): array
    {
        $usage = $remote->usage ?? throw new UnexpectedValueException("Generation [{$remote->id}] succeeded without usage.");
        // Billed at the rates fixed when Pixelforge created it, not today's.
        $cost = $remote->rates->costOf($usage);

        return [
            ...$usage->toArray(),
            'provider_cost' => $cost,
            'charge' => $this->markup->apply($cost),
            'image_url' => $remote->imageUrl,
            'image_url_expires_at' => $remote->imageUrlExpiresAt,
            'completed_at' => $remote->completedAt,
        ];
    }

    /**
     * A failure costs nothing, for us or the user.
     *
     * @return array<string, mixed>
     */
    private function failureAttributes(?string $errorCode, ?string $errorMessage, ?CarbonInterface $completedAt): array
    {
        return [
            'provider_cost' => Usd::zero(),
            'charge' => Usd::zero(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'completed_at' => $completedAt,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function moveTo(Generation $generation, GenerationStatus $status, array $attributes): bool
    {
        $moved = DB::transaction(function () use ($generation, $status, $attributes): bool {
            // The row lock serialises concurrent webhooks and polls for the same generation.
            $current = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            if (! $current->status->canMoveTo($status)) {
                return false;
            }

            $current->fill([...$attributes, 'status' => $status])->save();

            // Runs once per generation: only the first move to a final status gets here.
            if ($status->isFinished()) {
                $this->wallet->settle($current);
            }

            return true;
        });

        // So the caller's model shows what was saved.
        $generation->refresh();

        return $moved;
    }
}
