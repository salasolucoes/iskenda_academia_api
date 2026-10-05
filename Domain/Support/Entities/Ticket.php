<?php

namespace Domain\Support\Entities;

use Domain\Support\ValueObjects\Priority;
use Domain\Support\ValueObjects\TicketStatus;

class Ticket
{
    public function __construct(
        private readonly string $id,
        private readonly string $studentId,
        private ?string $assignedTo,
        private string $subject,
        private string $description,
        private Priority $priority,
        private TicketStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function getAssignedTo(): ?string
    {
        return $this->assignedTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function assignTo(string $userId): void
    {
        $this->assignedTo = $userId;
    }

    public function escalate(): void
    {
        $this->status = TicketStatus::InProgress;
    }

    public function resolve(): void
    {
        $this->status = TicketStatus::Resolved;
    }

    public function close(): void
    {
        $this->status = TicketStatus::Closed;
    }

    public function reopen(): void
    {
        if (! $this->status->canBeReopened()) {
            throw new \DomainException('Only closed tickets can be reopened.');
        }

        $this->status = TicketStatus::Open;
    }

    public function progressOnReply(bool $isAdmin): void
    {
        if ($isAdmin) {
            $this->status = $this->status->progressWhenAdminReplies();
        }
    }

    public function canAddMessage(): bool
    {
        return $this->status->canAddMessage();
    }
}
