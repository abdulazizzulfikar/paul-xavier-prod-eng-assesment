<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    // Statuses only move forward, so late or duplicate events can never undo progress.
    public function canMoveTo(self $next): bool
    {
        return ! $this->isFinal() && $next->rank() > $this->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }
}
