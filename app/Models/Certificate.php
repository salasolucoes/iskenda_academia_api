<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Certificate as BaseCertificate;

#[Fillable([
    'enrollment_id', 'student_id', 'course_id',
    'issued_at', 'verification_hash', 'pdf_url',
])]
class Certificate extends BaseCertificate {}
