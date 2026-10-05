<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketRepliedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $ticketId,
        public readonly string $replySnippet,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-admin'),
            new PrivateChannel('private-user.'.$this->studentId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.replied';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'reply_snippet' => $this->replySnippet,
        ];
    }
}
