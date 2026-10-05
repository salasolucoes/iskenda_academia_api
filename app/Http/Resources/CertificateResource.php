<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'student_id' => $this->student_id,
            'course_id' => $this->course_id,
            'course_title' => $this->whenLoaded('enrollment.course', fn () => $this->enrollment->course->title),
            'issued_at' => $this->issued_at?->format('c'),
            'verification_hash' => $this->verification_hash,
            'pdf_url' => $this->pdf_url,
        ];
    }
}
