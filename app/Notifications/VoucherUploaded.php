<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoucherUploaded extends Notification
{
    use Queueable;

    public function __construct(
        private string $studentName,
        private string $voucherId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Novo Comprovativo',
            'message' => "{$this->studentName} submeteu um novo comprovativo bancário.",
            'type' => 'info',
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
