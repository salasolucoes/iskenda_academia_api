<?php

namespace Domain\Enrollment\Contracts;

use Domain\Enrollment\Entities\Enrollment;

interface EnrollmentRepositoryInterface
{
    public function findById(string $id): ?Enrollment;

    public function findByStudentId(string $studentId): array;

    public function findByStudentAndCourse(string $studentId, string $courseId): ?Enrollment;

    public function findActiveByStudent(string $studentId): array;

    public function save(Enrollment $enrollment): Enrollment;

    public function delete(string $id): void;
}
