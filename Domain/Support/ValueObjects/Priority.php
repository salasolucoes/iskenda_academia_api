<?php

namespace Domain\Support\ValueObjects;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function isUrgent(): bool
    {
        return $this === self::High;
    }
}
