<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;

class ListInstructorCoursesUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function execute(string $instructorId): array
    {
        return $this->courseRepository->findByInstructor($instructorId);
    }
}
