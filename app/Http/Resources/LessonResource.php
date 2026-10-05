<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'content_url' => $this->content_url,
            'duration_minutes' => $this->duration_minutes,
            'order' => $this->order,
            'live_session' => $this->whenLoaded('liveSession', fn () => [
                'id' => $this->liveSession->id,
                'stream_key' => $this->liveSession->stream_key,
                'raw_link' => $this->liveSession->raw_link,
                'scheduled_start' => $this->liveSession->scheduled_start?->toIso8601String(),
                'status' => $this->liveSession->status,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
