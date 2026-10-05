<?php

namespace Domain\Support\Contracts;

use Domain\Support\Entities\Ticket;
use Domain\Support\Entities\TicketMessage;

interface TicketRepositoryInterface
{
    public function findById(string $id): ?Ticket;

    public function findByStudentId(string $studentId): array;

    public function save(Ticket $ticket): Ticket;

    public function delete(string $id): void;

    public function findMessagesByTicketId(string $ticketId): array;

    public function saveMessage(TicketMessage $message): TicketMessage;
}
