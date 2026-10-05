<?php

namespace Domain\Course\Entities;

class Module
{
    public function __construct(
        private string $id,
        private string $courseId,
        private string $title,
        private ?string $description,
        private int $order,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCourseId(): string
    {
        return $this->courseId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getOrder(): int
    {
        return $this->order;
    }
}
