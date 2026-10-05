<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseAccessGrantedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $courseId,
        public readonly string $courseTitle,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-user.'.$this->studentId)];
    }

    public function broadcastAs(): string
    {
        return 'course.access-granted';
    }

    public function broadcastWith(): array
    {
        return [
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
        ];
    }
}
