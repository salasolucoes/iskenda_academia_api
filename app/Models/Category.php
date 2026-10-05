<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Category as BaseCategory;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class Category extends BaseCategory {}
