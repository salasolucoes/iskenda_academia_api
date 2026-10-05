<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\OrderItem as BaseOrderItem;

#[Fillable(['order_id', 'course_id', 'price_cents'])]
class OrderItem extends BaseOrderItem {}
