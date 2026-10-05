<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher as BasePaymentVoucher;

#[Fillable([
    'student_id', 'transaction_id', 'status',
    'file_path', 'file_hash', 'amount_cents',
    'rejection_reason', 'approved_by', 'approved_at',
    'rejected_by', 'rejected_at',
])]
class PaymentVoucher extends BasePaymentVoucher {}
