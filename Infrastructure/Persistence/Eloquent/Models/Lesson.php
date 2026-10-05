<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id',
        'title',
        'description',
        'type',
        'content_url',
        'duration_minutes',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function newFactory(): LessonFactory
    {
        return LessonFactory::new();
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
