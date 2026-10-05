<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;

class ListPublishedCoursesUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function execute(array $filters = []): array
    {
        return $this->courseRepository->findPublished($filters);
    }
}
