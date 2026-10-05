<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\WalletTransaction as BaseWalletTransaction;

#[Fillable([
    'wallet_id', 'student_id', 'type', 'direction',
    'amount_cents', 'balance_before', 'balance_after',
    'status', 'reference_id', 'reference_type',
    'description', 'approved_by', 'approved_at',
])]
class WalletTransaction extends BaseWalletTransaction {}
