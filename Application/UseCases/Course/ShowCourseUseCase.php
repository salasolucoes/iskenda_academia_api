<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Course\Entities\Course;

class ShowCourseUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function execute(string $id): ?Course
    {
        return $this->courseRepository->findById($id);
    }
}
