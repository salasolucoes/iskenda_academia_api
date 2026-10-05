<?php

use Domain\Enrollment\Entities\Enrollment;
use Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Illuminate\Support\Str;

test('enrollment can be completed', function () {
    $enrollment = new Enrollment(
        id: (string) Str::uuid(),
        studentId: (string) Str::uuid(),
        courseId: (string) Str::uuid(),
        orderId: null,
        status: EnrollmentStatus::Active,
        enrolledAt: new DateTimeImmutable,
    );

    $enrollment->complete();

    expect($enrollment->getStatus())->toBe(EnrollmentStatus::Completed);
    expect($enrollment->getCompletedAt())->not->toBeNull();
});

test('enrollment can be cancelled', function () {
    $enrollment = new Enrollment(
        id: (string) Str::uuid(),
        studentId: (string) Str::uuid(),
        courseId: (string) Str::uuid(),
        orderId: null,
        status: EnrollmentStatus::Active,
        enrolledAt: new DateTimeImmutable,
    );

    $enrollment->cancel();

    expect($enrollment->getStatus())->toBe(EnrollmentStatus::Cancelled);
});

test('completed enrollment cannot be cancelled', function () {
    $enrollment = new Enrollment(
        id: (string) Str::uuid(),
        studentId: (string) Str::uuid(),
        courseId: (string) Str::uuid(),
        orderId: null,
        status: EnrollmentStatus::Completed,
        enrolledAt: new DateTimeImmutable,
        completedAt: new DateTimeImmutable,
    );

    $enrollment->cancel();
})->throws(DomainException::class);
