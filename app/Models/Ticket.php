<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\Ticket as BaseTicket;

#[Fillable(['student_id', 'assigned_to', 'subject', 'description', 'priority', 'status'])]
class Ticket extends BaseTicket {}
