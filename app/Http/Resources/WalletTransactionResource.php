<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'direction' => $this->direction,
            'type' => $this->type,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at?->format('c'),
        ];
    }
}
