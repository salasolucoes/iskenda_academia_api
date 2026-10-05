<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Order as BaseOrder;

#[Fillable(['student_id', 'total_cents', 'payment_method', 'status'])]
class Order extends BaseOrder {}
