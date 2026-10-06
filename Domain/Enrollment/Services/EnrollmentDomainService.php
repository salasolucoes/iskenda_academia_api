<?php

namespace Domain\Enrollment\Services;

use Domain\Enrollment\ValueObjects\EnrollmentStatus;

class EnrollmentDomainService
{
    /**
     * Percentagem de visionamento necessária para concluir uma aula.
     */
    public const COMPLETION_THRESHOLD = 0.9;

    public function canEnroll(array $existingEnrollments, string $courseId): bool
    {
        foreach ($existingEnrollments as $enrollment) {
            if ($enrollment->getCourseId() === $courseId
                && $enrollment->getStatus() === EnrollmentStatus::Active) {
                return false;
            }
        }

        return true;
    }

    public function isCourseComplete(array $lessonProgress): bool
    {
        if (empty($lessonProgress)) {
            return false;
        }

        foreach ($lessonProgress as $progress) {
            if (! $progress['is_completed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sem duração autoritativa não há conclusão automática: a ausência de
     * informação tem de impedir a conclusão, nunca concedê-la.
     *
     * @throws \InvalidArgumentException
     */
    public function markLessonComplete(int $watchedSeconds, ?int $totalDurationSeconds): bool
    {
        if ($watchedSeconds < 0) {
            throw new \InvalidArgumentException('Watched seconds cannot be negative');
        }

        if ($totalDurationSeconds === null || $totalDurationSeconds <= 0) {
            return false;
        }

        return $watchedSeconds >= ($totalDurationSeconds * self::COMPLETION_THRESHOLD);
    }
}
