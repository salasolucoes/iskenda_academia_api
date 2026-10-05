<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'type' => $this->data['type'] ?? 'info',
            'link' => $this->data['link'] ?? null,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
