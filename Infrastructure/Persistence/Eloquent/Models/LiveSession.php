<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'lesson_id',
        'stream_key',
        'raw_link',
        'masked_token',
        'token_expires_at',
        'scheduled_start',
        'actual_start',
        'actual_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'raw_link' => 'string',
            'scheduled_start' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'token_expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): LiveSessionFactory
    {
        return LiveSessionFactory::new();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
