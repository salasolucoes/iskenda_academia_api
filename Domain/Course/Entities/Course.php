<?php

namespace Domain\Course\Entities;

use Domain\Course\ValueObjects\CourseStatus;
use Domain\Course\ValueObjects\Modality;

class Course
{
    public function __construct(
        private string $id,
        private string $instructorId,
        private ?string $categoryId,
        private string $title,
        private string $slug,
        private string $description,
        private Modality $modality,
        private int $priceCents,
        private CourseStatus $status,
        private ?string $thumbnailUrl,
        private ?string $categoryName = null,
        private ?string $instructorName = null,
        private ?\DateTimeImmutable $createdAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getInstructorId(): string
    {
        return $this->instructorId;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getModality(): Modality
    {
        return $this->modality;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getStatus(): CourseStatus
    {
        return $this->status;
    }

    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }

    public function getInstructorName(): ?string
    {
        return $this->instructorName;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function publish(): void
    {
        $this->status = CourseStatus::Published;
    }

    public function archive(): void
    {
        $this->status = CourseStatus::Archived;
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    public function isFree(): bool
    {
        return $this->priceCents <= 0;
    }
}
