<?php

namespace Domain\Enrollment\Entities;

use Domain\Enrollment\ValueObjects\EnrollmentStatus;

class Enrollment
{
    public function __construct(
        private readonly string $id,
        private readonly string $studentId,
        private readonly string $courseId,
        private readonly ?string $orderId,
        private EnrollmentStatus $status,
        private readonly \DateTimeImmutable $enrolledAt,
        private ?\DateTimeImmutable $completedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function getCourseId(): string
    {
        return $this->courseId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getStatus(): EnrollmentStatus
    {
        return $this->status;
    }

    public function getEnrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function complete(): void
    {
        if (! $this->status->canComplete()) {
            throw new \DomainException('Cannot complete an enrollment that is not active.');
        }

        $this->status = EnrollmentStatus::Completed;
        $this->completedAt = new \DateTimeImmutable;
    }

    public function cancel(): void
    {
        if (! $this->status->canCancel()) {
            throw new \DomainException('Cannot cancel an enrollment that is not active.');
        }

        $this->status = EnrollmentStatus::Cancelled;
    }
}
