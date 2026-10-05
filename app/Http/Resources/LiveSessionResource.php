<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'module_id' => $this->lesson->module_id,
            ]),
            'stream_key' => $this->stream_key,
            'raw_link' => $this->raw_link,
            'status' => $this->status,
            'scheduled_start' => $this->scheduled_start?->format('c'),
            'actual_start' => $this->actual_start?->format('c'),
            'actual_end' => $this->actual_end?->format('c'),
            'created_at' => $this->created_at?->format('c'),
        ];
    }
}
