<?php

namespace App\Enums;

enum GenerationStatus: string
{
    /** Created locally, not yet accepted by Pixelforge. */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /** Status only moves forward, which makes duplicate and late updates harmless. */
    public function canMoveTo(self $next): bool
    {
        return ! $this->isFinal() && $next->rank() > $this->rank();
    }

    /** @return list<self> */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }

    /** @return list<self> */
    public static function inFlight(): array
    {
        return [self::Queued, self::Processing];
    }

    private function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
