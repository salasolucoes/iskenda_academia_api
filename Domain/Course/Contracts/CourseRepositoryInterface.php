<?php

namespace Domain\Course\Contracts;

use Domain\Course\Entities\Course;

interface CourseRepositoryInterface
{
    public function findById(string $id): ?Course;

    public function findBySlug(string $slug): ?Course;

    public function findAll(): array;

    public function findPublished(array $filters = []): array;

    public function findByInstructor(string $instructorId): array;

    public function save(Course $course): Course;

    public function delete(string $id): void;
}
