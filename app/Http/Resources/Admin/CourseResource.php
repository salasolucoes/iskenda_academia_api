<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instructor_id' => $this->instructor_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'modality' => $this->modality,
            'price_cents' => $this->price_cents,
            'status' => $this->status,
            'thumbnail_url' => $this->thumbnail_url,
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->format('c'),
            'updated_at' => $this->updated_at?->format('c'),
        ];
    }
}
