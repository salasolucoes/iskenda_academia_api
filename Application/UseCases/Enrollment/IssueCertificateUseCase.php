<?php

namespace Application\UseCases\Enrollment;

use Domain\Enrollment\Entities\Certificate;
use Domain\Enrollment\Entities\Enrollment;
use Domain\Enrollment\ValueObjects\VerificationHash;
use Illuminate\Support\Str;

class IssueCertificateUseCase
{
    public function execute(
        Enrollment $enrollment,
        string $courseTitle,
        string $studentName,
    ): Certificate {
        $hashData = sprintf(
            '%s-%s-%s-%s',
            $enrollment->getId(),
            $enrollment->getStudentId(),
            $enrollment->getCourseId(),
            $enrollment->getCompletedAt()->format('Y-m-d H:i:s'),
        );
        $verificationHash = VerificationHash::generate($hashData);

        return new Certificate(
            id: (string) Str::uuid(),
            enrollmentId: $enrollment->getId(),
            studentId: $enrollment->getStudentId(),
            courseId: $enrollment->getCourseId(),
            verificationHash: $verificationHash,
            issuedAt: new \DateTimeImmutable,
        );
    }
}
