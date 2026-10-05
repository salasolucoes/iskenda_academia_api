<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'slug' => $this->getSlug(),
            'description' => $this->getDescription(),
            'modality' => $this->getModality()->value,
            'price_cents' => $this->getPriceCents(),
            'is_free' => $this->isFree(),
            'status' => $this->getStatus()->value,
            'thumbnail_url' => $this->getThumbnailUrl(),
            'category_id' => $this->getCategoryId(),
            'category_name' => $this->getCategoryName(),
            'instructor_name' => $this->getInstructorName(),
            'created_at' => $this->getCreatedAt()?->format('c'),
        ];
    }
}
