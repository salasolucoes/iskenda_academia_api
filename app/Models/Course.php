<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Course as BaseCourse;

#[Fillable(['instructor_id', 'category_id', 'title', 'slug', 'description', 'modality', 'price_cents', 'status', 'thumbnail_url'])]
class Course extends BaseCourse {}
