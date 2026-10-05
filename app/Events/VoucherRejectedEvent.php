<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherRejectedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $voucherId,
        public readonly ?string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-admin'),
            new PrivateChannel('private-user.'.$this->studentId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voucher.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'voucher_id' => $this->voucherId,
            'reason' => $this->reason,
        ];
    }
}
