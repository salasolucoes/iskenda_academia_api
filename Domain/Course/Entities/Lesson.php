<?php

namespace Domain\Course\Entities;

use Domain\Course\ValueObjects\LessonType;

class Lesson
{
    public function __construct(
        private string $id,
        private string $moduleId,
        private string $title,
        private ?string $description,
        private LessonType $type,
        private ?string $contentUrl,
        private ?int $durationMinutes,
        private int $order,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getModuleId(): string
    {
        return $this->moduleId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): LessonType
    {
        return $this->type;
    }

    public function getContentUrl(): ?string
    {
        return $this->contentUrl;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function getOrder(): int
    {
        return $this->order;
    }
}
