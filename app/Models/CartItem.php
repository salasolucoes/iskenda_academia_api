<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\CartItem as BaseCartItem;

#[Fillable(['cart_id', 'course_id'])]
class CartItem extends BaseCartItem {}
