<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Lesson as BaseLesson;

#[Fillable(['module_id', 'title', 'description', 'type', 'content_url', 'duration_minutes', 'order'])]
class Lesson extends BaseLesson {}
