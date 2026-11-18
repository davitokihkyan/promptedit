<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Pixelforge webhook we've already processed, kept for deduplication and audit.
 *
 * @property string $id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $received_at
 */
class WebhookEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }
}
