<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Sends a pending generation to Pixelforge, waiting for a free slot and backing off when asked to.
 */
final class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Failed submissions (rate limit, overload) before the generation fails, for free. */
    public const int MAX_FAILED_ATTEMPTS = 6;

    /** After this, the reconcile command resubmits anything still pending. */
    public const int RETRY_FOR_MINUTES = 30;

    private const int SLOT_WAIT_SECONDS = 5;

    private const int MAX_BACKOFF_SECONDS = 60;

    /** @var list<int> Seconds to wait after an unexpected exception, so it can't retry in a tight loop. */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(public Generation $generation) {}

    /** @return list<object> */
    public function middleware(): array
    {
        // Checking for a free slot and submitting must not interleave across workers.
        return [(new WithoutOverlapping('pixelforge'))->releaseAfter(1)->expireAfter(30)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(self::RETRY_FOR_MINUTES);
    }

    public function handle(PixelforgeClient $pixelforge, GenerationUpdater $updater): void
    {
        $generation = $this->generation;

        // Already accepted, possibly reported by a webhook before our request returned.
        if ($generation->status !== GenerationStatus::Pending) {
            return;
        }

        if (Generation::query()->inFlight()->count() >= config('services.pixelforge.max_in_flight')) {
            $this->release(self::SLOT_WAIT_SECONDS);

            return;
        }

        try {
            $remote = $pixelforge->createGeneration($generation->parameters(), $generation->id);
        } catch (ConnectionException) {
            // It may have been created; resending with the same Idempotency-Key is safe.
            $generation->increment('submit_attempts');
            $this->retryLater();

            return;
        } catch (PixelforgeException $exception) {
            $this->handleRejection($exception, $updater);

            return;
        }

        $updater->apply($generation, $remote);
    }

    private function handleRejection(PixelforgeException $exception, GenerationUpdater $updater): void
    {
        // Slots are shared with the account's other environments; waiting isn't a failed attempt.
        if ($exception->isConcurrencyLimit()) {
            $this->release($exception->retryAfter ?? self::SLOT_WAIT_SECONDS);

            return;
        }

        // A generation with our key already exists and will be billed; its webhooks settle this one.
        if ($exception->generationAlreadyExists()) {
            report($exception);

            return;
        }

        $this->generation->increment('submit_attempts');

        $canRetry = $exception->outcomeIsUnknown()
            || ($exception->isRetryable() && $this->generation->submit_attempts < self::MAX_FAILED_ATTEMPTS);

        if ($canRetry) {
            $this->retryLater($exception->retryAfter);

            return;
        }

        $updater->reject($this->generation, $exception->errorCode, $exception->getMessage());
    }

    /** Exponential backoff that never retries sooner than Pixelforge asked. */
    private function retryLater(?int $retryAfter = null): void
    {
        $backoff = min(self::MAX_BACKOFF_SECONDS, 2 ** $this->generation->submit_attempts);

        $this->release(max($retryAfter ?? 0, $backoff));
    }
}
