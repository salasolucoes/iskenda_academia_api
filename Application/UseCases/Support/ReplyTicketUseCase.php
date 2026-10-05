<?php

namespace Application\UseCases\Support;

use App\Events\TicketRepliedEvent;
use App\Models\User;
use App\Notifications\TicketReplied;
use Domain\Support\Contracts\TicketRepositoryInterface;
use Domain\Support\Entities\TicketMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReplyTicketUseCase
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    ) {}

    public function execute(
        string $ticketId,
        string $authorId,
        string $body,
        bool $isInternal = false,
    ): TicketMessage {
        $ticket = $this->ticketRepository->findById($ticketId);

        if ($ticket === null) {
            throw new \InvalidArgumentException('Ticket not found.');
        }

        if (! $ticket->canAddMessage()) {
            throw new \DomainException('Cannot reply to a closed ticket.');
        }

        $message = new TicketMessage(
            id: (string) Str::uuid(),
            ticketId: $ticketId,
            authorId: $authorId,
            body: $body,
            isInternal: $isInternal,
            createdAt: new \DateTimeImmutable,
        );

        $saved = $this->ticketRepository->saveMessage($message);

        if (! $isInternal) {
            event(new TicketRepliedEvent(
                studentId: $ticket->getStudentId(),
                ticketId: $ticketId,
                replySnippet: Str::limit($body, 100),
            ));

            $student = User::find($ticket->getStudentId());
            if ($student) {
                Notification::send($student, new TicketReplied(
                    ticketId: $ticketId,
                    subject: $ticket->getSubject(),
                ));
            }
        }

        return $saved;
    }
}
