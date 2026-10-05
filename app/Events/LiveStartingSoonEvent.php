<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStartingSoonEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $liveSessionId,
        public readonly string $courseId,
        public readonly string $courseTitle,
        public readonly string $instructorId,
        public readonly int $minutesUntilStart,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-admin'),
            new PrivateChannel('private-instructor.'.$this->instructorId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'live.starting-soon';
    }

    public function broadcastWith(): array
    {
        return [
            'live_session_id' => $this->liveSessionId,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'minutes_until_start' => $this->minutesUntilStart,
        ];
    }
}
