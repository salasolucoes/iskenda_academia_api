<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoucherApproved extends Notification
{
    use Queueable;

    public function __construct(
        private int $amountCents,
        private string $voucherId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Comprovativo Aprovado',
            'message' => 'O teu comprovativo bancário foi aprovado. Foram creditados '.number_format($this->amountCents, 0, ',', ' ').' Kzs na tua carteira.',
            'type' => 'success',
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
