<?php

namespace Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'certificates';

    protected $fillable = [
        'enrollment_id',
        'student_id',
        'course_id',
        'issued_at',
        'verification_hash',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CertificateFactory
    {
        return CertificateFactory::new();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
