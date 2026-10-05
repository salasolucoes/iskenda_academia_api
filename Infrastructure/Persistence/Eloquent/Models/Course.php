<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'description',
        'modality',
        'price_cents',
        'status',
        'thumbnail_url',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->ordered();
    }
}
