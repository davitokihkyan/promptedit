<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Pixelforge webhook we've received. Its id is the primary key, so a repeated delivery can't be recorded twice.
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
