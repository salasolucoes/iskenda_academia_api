<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;

class DeleteCourseUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function execute(string $id): void
    {
        $course = $this->courseRepository->findById($id);

        if ($course === null) {
            throw new \InvalidArgumentException('Curso não encontrado.');
        }

        $this->courseRepository->delete($id);
    }
}
