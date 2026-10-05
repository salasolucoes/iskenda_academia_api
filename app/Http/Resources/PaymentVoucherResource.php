<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Services\PresignedUrlService;

class PaymentVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new UserResource($this->whenLoaded('student')),
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'amount_cents' => $this->amount_cents,
            'file_url' => $this->file_path ? app(PresignedUrlService::class)->generate($this->file_path) : null,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('c'),
            'rejected_at' => $this->rejected_at?->format('c'),
            'created_at' => $this->created_at?->format('c'),
        ];
    }
}
