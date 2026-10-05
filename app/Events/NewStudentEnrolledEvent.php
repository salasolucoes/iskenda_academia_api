<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewStudentEnrolledEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $studentName,
        public readonly string $courseId,
        public readonly string $courseTitle,
        public readonly string $instructorId,
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
        return 'student.enrolled';
    }

    public function broadcastWith(): array
    {
        return [
            'student_id' => $this->studentId,
            'student_name' => $this->studentName,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
        ];
    }
}
