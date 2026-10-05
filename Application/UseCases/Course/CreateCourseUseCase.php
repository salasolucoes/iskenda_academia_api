<?php

namespace Application\UseCases\Course;

use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Course\Entities\Course;
use Domain\Course\Services\CourseDomainService;
use Domain\Course\ValueObjects\CourseStatus;
use Domain\Course\ValueObjects\Modality;
use Illuminate\Support\Str;

class CreateCourseUseCase
{
    public function __construct(
        private CourseRepositoryInterface $courseRepository,
        private CourseDomainService $courseDomainService,
    ) {}

    public function execute(
        string $instructorId,
        ?string $categoryId,
        string $title,
        string $description,
        string $modality,
        int $priceCents,
        ?string $thumbnailUrl = null,
    ): Course {
        if (! $this->courseDomainService->isValidModality($modality)) {
            throw new \InvalidArgumentException('Modalidade inválida.');
        }

        $slug = $this->courseDomainService->generateSlug($title);

        $course = new Course(
            id: (string) Str::uuid(),
            instructorId: $instructorId,
            categoryId: $categoryId,
            title: $title,
            slug: $slug,
            description: $description,
            modality: Modality::from($modality),
            priceCents: $priceCents,
            status: CourseStatus::Draft,
            thumbnailUrl: $thumbnailUrl,
        );

        return $this->courseRepository->save($course);
    }
}
