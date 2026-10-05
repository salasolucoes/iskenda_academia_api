<?php

namespace Domain\Support\ValueObjects;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function canBeReopened(): bool
    {
        return $this === self::Closed;
    }

    public function canAddMessage(): bool
    {
        return $this !== self::Closed;
    }

    public function progressWhenAdminReplies(): self
    {
        return match ($this) {
            self::Open => self::InProgress,
            default => $this,
        };
    }
}
