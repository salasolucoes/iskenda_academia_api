<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewUrgentTicket extends Notification
{
    use Queueable;

    public function __construct(
        private string $studentName,
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
            'title' => 'Ticket Urgente',
            'message' => "{$this->studentName} abriu um ticket urgente: {$this->subject}",
            'type' => 'warning',
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
