<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVoucher extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payment_vouchers';

    protected $fillable = [
        'student_id',
        'transaction_id',
        'status',
        'file_path',
        'file_hash',
        'amount_cents',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
