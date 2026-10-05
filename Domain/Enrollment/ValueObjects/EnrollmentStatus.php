<?php

namespace Domain\Enrollment\ValueObjects;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canComplete(): bool
    {
        return $this === self::Active;
    }

    public function canCancel(): bool
    {
        return $this === self::Active;
    }
}
