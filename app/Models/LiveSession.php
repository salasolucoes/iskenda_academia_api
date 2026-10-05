<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\LiveSession as BaseLiveSession;

#[Fillable(['lesson_id', 'stream_key', 'scheduled_start', 'actual_start', 'actual_end', 'status'])]
class LiveSession extends BaseLiveSession {}
