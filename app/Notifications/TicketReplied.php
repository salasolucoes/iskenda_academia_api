<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketReplied extends Notification
{
    use Queueable;

    public function __construct(
        private string $ticketId,
        private string $subject,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Resposta ao Ticket',
            'message' => "Recebeste uma resposta no ticket: {$this->subject}",
            'type' => 'info',
            'ticket_id' => $this->ticketId,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'data' => $this->toDatabase($notifiable),
            'read_at' => null,
        ];
    }
}
