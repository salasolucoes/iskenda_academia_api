<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewUrgentTicketEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $studentId,
        public readonly string $subject,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-admin')];
    }

    public function broadcastAs(): string
    {
        return 'ticket.urgent';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'student_id' => $this->studentId,
            'subject' => $this->subject,
        ];
    }
}
