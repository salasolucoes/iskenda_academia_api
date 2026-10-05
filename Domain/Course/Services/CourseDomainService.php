<?php

namespace Domain\Course\Services;

use Domain\Course\ValueObjects\CourseStatus;
use Domain\Course\ValueObjects\Modality;

class CourseDomainService
{
    public function generateSlug(string $title): string
    {
        return str($title)->slug()->toString();
    }

    public function canBePublished(CourseStatus $status): bool
    {
        return match ($status) {
            CourseStatus::Draft => true,
            CourseStatus::Published => false,
            CourseStatus::Archived => false,
        };
    }

    public function isValidModality(string $modality): bool
    {
        return in_array($modality, array_map(fn (Modality $m) => $m->value, Modality::cases()));
    }

    public function canPublish(CourseStatus $status, bool $hasModules, bool $hasLessons): bool
    {
        if (! $this->canBePublished($status)) {
            return false;
        }

        if (! $hasModules || ! $hasLessons) {
            return false;
        }

        return true;
    }

    public function calculateTotalDuration(array $lessons): int
    {
        $total = 0;
        foreach ($lessons as $lesson) {
            $total += $lesson['duration_seconds'] ?? 0;
        }

        return $total;
    }
}
