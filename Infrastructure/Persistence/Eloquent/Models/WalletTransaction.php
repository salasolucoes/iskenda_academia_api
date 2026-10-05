<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'wallet_id',
        'amount_cents',
        'direction',
        'type',
        'balance_before',
        'balance_after',
        'status',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentWallet::class, 'wallet_id');
    }
}
