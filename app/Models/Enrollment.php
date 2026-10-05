<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Enrollment as BaseEnrollment;

#[Fillable(['student_id', 'course_id', 'order_id', 'status', 'enrolled_at', 'completed_at'])]
class Enrollment extends BaseEnrollment {}
