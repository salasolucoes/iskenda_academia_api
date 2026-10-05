<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'author_id' => $m->author_id,
                'author_name' => $m->author?->name,
                'body' => $m->body,
                'is_internal' => $m->is_internal,
                'created_at' => $m->created_at?->format('c'),
            ])),
            'created_at' => $this->created_at?->format('c'),
            'updated_at' => $this->updated_at?->format('c'),
        ];
    }
}
