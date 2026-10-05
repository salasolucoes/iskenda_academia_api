<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Cart as BaseCart;

#[Fillable(['student_id'])]
class Cart extends BaseCart {}
