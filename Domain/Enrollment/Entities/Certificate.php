<?php

namespace Domain\Enrollment\Entities;

use Domain\Enrollment\ValueObjects\VerificationHash;

class Certificate
{
    public function __construct(
        private readonly string $id,
        private readonly string $enrollmentId,
        private readonly string $studentId,
        private readonly string $courseId,
        private readonly VerificationHash $verificationHash,
        private readonly \DateTimeImmutable $issuedAt,
        private ?string $pdfUrl = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getEnrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function getCourseId(): string
    {
        return $this->courseId;
    }

    public function getVerificationHash(): VerificationHash
    {
        return $this->verificationHash;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }
}
