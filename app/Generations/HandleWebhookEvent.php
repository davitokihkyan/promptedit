<?php

namespace App\Generations;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Pixelforge\Data\RemoteGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified Pixelforge webhook exactly once.
 */
final readonly class HandleWebhookEvent
{
    public function __construct(private GenerationUpdater $updater) {}

    /** @param array<string, mixed> $event */
    public function handle(array $event): void
    {
        // If anything fails, the event isn't recorded and Pixelforge's retry gets another go.
        DB::transaction(function () use ($event): void {
            if (! $this->recordOnce($event)) {
                return;
            }

            // e.g. "ping" from the dashboard's test button
            if (! str_starts_with($event['type'], 'generation.')) {
                return;
            }

            $remote = RemoteGeneration::fromArray($event['data']['object']);
            $generation = $this->find($remote);

            if ($generation === null) {
                Log::warning('Pixelforge webhook for an unknown generation.', ['event' => $event['id'], 'generation' => $remote->id]);

                return;
            }

            $this->updater->apply($generation, $remote);
        });
    }

    /**
     * The primary key on the event id turns a repeated delivery into a no-op.
     *
     * @param  array<string, mixed>  $event
     */
    private function recordOnce(array $event): bool
    {
        return WebhookEvent::query()->insertOrIgnore([
            'id' => $event['id'],
            'type' => $event['type'],
            'payload' => json_encode($event),
            'received_at' => now(),
        ]) === 1;
    }

    private function find(RemoteGeneration $remote): ?Generation
    {
        // metadata.generation_id is known even before our submit request returns.
        if ($remote->localId !== null) {
            return Generation::query()->find($remote->localId);
        }

        return Generation::query()->firstWhere('pixelforge_id', $remote->id);
    }
}
