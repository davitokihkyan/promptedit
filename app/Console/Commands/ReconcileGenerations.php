<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Webhook delivery isn't guaranteed, so poll whatever has gone quiet.
 */
#[Signature('generations:reconcile')]
#[Description('Poll Pixelforge for generations whose webhooks never arrived')]
final class ReconcileGenerations extends Command
{
    // Processing takes up to 30s, or 90s with [slow].
    private const int STALE_AFTER_MINUTES = 2;

    // Matches SubmitGeneration::retryUntil().
    private const int ABANDONED_AFTER_MINUTES = 30;

    // Stays well under Pixelforge's 60 requests per minute.
    private const int BATCH_SIZE = 10;

    public function handle(PixelforgeClient $pixelforge, GenerationUpdater $updater): int
    {
        $this->pollStale($pixelforge, $updater);
        $this->resubmitAbandoned();

        return self::SUCCESS;
    }

    private function pollStale(PixelforgeClient $pixelforge, GenerationUpdater $updater): void
    {
        $stale = Generation::query()
            ->inFlight()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->oldest('updated_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        foreach ($stale as $generation) {
            try {
                $moved = $updater->apply($generation, $pixelforge->getGeneration((string) $generation->pixelforge_id));
            } catch (Throwable $exception) {
                // One bad generation must not stop the others from being checked.
                report($exception);
                $moved = false;
            }

            // Still running, or couldn't be checked: move it to the back of the line.
            if (! $moved) {
                $generation->touch();
            }
        }
    }

    /** A submit job gave up without an answer; the same Idempotency-Key makes resending safe. */
    private function resubmitAbandoned(): void
    {
        Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('updated_at', '<', now()->subMinutes(self::ABANDONED_AFTER_MINUTES))
            ->eachById(function (Generation $generation): void {
                $generation->touch();
                SubmitGeneration::dispatch($generation);
            });
    }
}
