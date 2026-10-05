<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress as BaseLessonProgress;

#[Fillable([
    'enrollment_id', 'lesson_id', 'watched_seconds',
    'last_position_seconds', 'is_completed', 'last_activity_at',
])]
class LessonProgress extends BaseLessonProgress {}
