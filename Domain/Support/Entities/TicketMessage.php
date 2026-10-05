<?php

namespace Domain\Support\Entities;

class TicketMessage
{
    public function __construct(
        private readonly string $id,
        private readonly string $ticketId,
        private readonly string $authorId,
        private readonly string $body,
        private readonly bool $isInternal,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTicketId(): string
    {
        return $this->ticketId;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
