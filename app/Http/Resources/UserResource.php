<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at?->format('c'),
            'created_at' => $this->created_at?->format('c'),
            'enrollments_count' => $this->whenCounted('enrollments'),
        ];
    }
}
