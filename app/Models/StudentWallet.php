<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\StudentWallet as BaseStudentWallet;

#[Fillable(['student_id', 'balance_cents'])]
class StudentWallet extends BaseStudentWallet {}
