<?php

namespace Domain\Support\Services;

use Domain\Support\ValueObjects\Priority;
use Domain\Support\ValueObjects\TicketStatus;

class TicketDomainService
{
    public function shouldEscalate(Priority $priority, TicketStatus $status, int $messageCount): bool
    {
        if ($priority->isUrgent() && $status === TicketStatus::Open && $messageCount <= 1) {
            return true;
        }

        return false;
    }

    public function canStudentCreateTicket(bool $hasActiveTickets): bool
    {
        return ! $hasActiveTickets || true; // Students can always open tickets
    }
}
