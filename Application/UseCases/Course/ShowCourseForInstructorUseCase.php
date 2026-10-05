<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Course\Entities\Course;

class ShowCourseForInstructorUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function execute(string $id, string $instructorId): ?Course
    {
        $course = $this->courseRepository->findById($id);

        if ($course === null || $course->getInstructorId() !== $instructorId) {
            return null;
        }

        return $course;
    }
}
