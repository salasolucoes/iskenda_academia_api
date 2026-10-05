<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'duration_minutes' => $this->duration_minutes,
            'order' => $this->order,
        ];
    }
}
