<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoucherRejected extends Notification
{
    use Queueable;

    public function __construct(
        private string $voucherId,
        private ?string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Comprovativo Rejeitado',
            'message' => $this->reason
                ? "O teu comprovativo bancário foi rejeitado. Motivo: {$this->reason}"
                : 'O teu comprovativo bancário foi rejeitado. Submete um novo comprovativo.',
            'type' => 'error',
            'voucher_id' => $this->voucherId,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'data' => $this->toDatabase($notifiable),
            'read_at' => null,
        ];
    }
}
