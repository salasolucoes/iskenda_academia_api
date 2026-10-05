<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Module as BaseModule;

#[Fillable(['course_id', 'title', 'description', 'order'])]
class Module extends BaseModule {}
