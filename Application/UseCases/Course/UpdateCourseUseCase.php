<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Course\Entities\Course;
use Domain\Course\Services\CourseDomainService;
use Domain\Course\ValueObjects\CourseStatus;
use Domain\Course\ValueObjects\Modality;

class UpdateCourseUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
        private CourseDomainService $courseDomainService,
    ) {}

    public function execute(
        string $id,
        ?string $categoryId,
        string $title,
        string $description,
        string $modality,
        int $priceCents,
        string $status,
        ?string $thumbnailUrl = null,
    ): Course {
        $course = $this->courseRepository->findById($id);

        if ($course === null) {
            throw new \InvalidArgumentException('Curso não encontrado.');
        }

        if (! $this->courseDomainService->isValidModality($modality)) {
            throw new \InvalidArgumentException('Modalidade inválida.');
        }

        $course = new Course(
            id: $course->getId(),
            instructorId: $course->getInstructorId(),
            categoryId: $categoryId,
            title: $title,
            slug: $course->getSlug(),
            description: $description,
            modality: Modality::from($modality),
            priceCents: $priceCents,
            status: CourseStatus::from($status),
            thumbnailUrl: $thumbnailUrl,
            createdAt: $course->getCreatedAt(),
        );

        return $this->courseRepository->save($course);
    }
}
