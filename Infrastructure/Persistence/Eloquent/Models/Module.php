<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    protected static function newFactory(): ModuleFactory
    {
        return ModuleFactory::new();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->ordered();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
