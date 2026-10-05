<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'watched_seconds',
        'last_position_seconds',
        'is_completed',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'last_position_seconds' => 'integer',
            'is_completed' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function newFactory(): LessonProgressFactory
    {
        return LessonProgressFactory::new();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
