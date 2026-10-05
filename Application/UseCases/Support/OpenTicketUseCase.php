<?php

namespace Application\UseCases\Support;

use App\Events\NewUrgentTicketEvent;
use Domain\Support\Contracts\TicketRepositoryInterface;
use Domain\Support\Entities\Ticket;
use Domain\Support\ValueObjects\Priority;
use Domain\Support\ValueObjects\TicketStatus;
use Illuminate\Support\Str;

class OpenTicketUseCase
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    ) {}

    public function execute(
        string $studentId,
        string $subject,
        string $description,
        string $priority,
    ): Ticket {
        $ticket = new Ticket(
            id: (string) Str::uuid(),
            studentId: $studentId,
            assignedTo: null,
            subject: $subject,
            description: $description,
            priority: Priority::from($priority),
            status: TicketStatus::Open,
            createdAt: new \DateTimeImmutable,
        );

        $saved = $this->ticketRepository->save($ticket);

        if ($priority === 'high') {
            event(new NewUrgentTicketEvent(
                ticketId: $saved->getId(),
                studentId: $studentId,
                subject: $subject,
            ));
        }

        return $saved;
    }
}
