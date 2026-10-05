<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPurchaseIntentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $courseId,
        public readonly string $courseTitle,
        public readonly int $amountCents,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-admin')];
    }

    public function broadcastAs(): string
    {
        return 'purchase.intent';
    }

    public function broadcastWith(): array
    {
        return [
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'amount_cents' => $this->amountCents,
        ];
    }
}
