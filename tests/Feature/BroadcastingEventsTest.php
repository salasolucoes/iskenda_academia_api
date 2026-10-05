<?php

use App\Events\CourseAccessGrantedEvent;
use App\Events\LiveStartingSoonEvent;
use App\Events\NewStudentEnrolledEvent;
use App\Events\NewUrgentTicketEvent;
use App\Events\TicketRepliedEvent;
use App\Events\VoucherApprovedEvent;
use App\Events\VoucherRejectedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('broadcasts NewStudentEnrolledEvent on admin and instructor channels', function () {
    $event = new NewStudentEnrolledEvent(
        studentId: 'student-1',
        studentName: 'João',
        courseId: 'course-1',
        courseTitle: 'Scrum Fundamentals',
        instructorId: 'instructor-1',
    );

    expect($event->broadcastOn())->toHaveCount(2);
    expect($event->broadcastAs())->toBe('student.enrolled');
    expect($event->broadcastWith())->toHaveKeys(['student_id', 'student_name', 'course_id', 'course_title']);
});

it('broadcasts VoucherApprovedEvent on admin and user channels', function () {
    $event = new VoucherApprovedEvent(
        studentId: 'student-1',
        voucherId: 'voucher-1',
        amountCents: 5000,
    );

    expect($event->broadcastOn())->toHaveCount(2);
    expect($event->broadcastAs())->toBe('voucher.approved');
    expect($event->broadcastWith())->toBe([
        'voucher_id' => 'voucher-1',
        'amount_cents' => 5000,
    ]);
});

it('broadcasts VoucherRejectedEvent on admin and user channels', function () {
    $event = new VoucherRejectedEvent(
        studentId: 'student-1',
        voucherId: 'voucher-1',
        reason: 'Image is blurry',
    );

    expect($event->broadcastOn())->toHaveCount(2);
    expect($event->broadcastAs())->toBe('voucher.rejected');
    expect($event->broadcastWith())->toBe([
        'voucher_id' => 'voucher-1',
        'reason' => 'Image is blurry',
    ]);
});

it('broadcasts TicketRepliedEvent on admin and user channels', function () {
    $event = new TicketRepliedEvent(
        studentId: 'student-1',
        ticketId: 'ticket-1',
        replySnippet: 'We have received your request...',
    );

    expect($event->broadcastOn())->toHaveCount(2);
    expect($event->broadcastAs())->toBe('ticket.replied');
});

it('broadcasts NewUrgentTicketEvent on admin channel only', function () {
    $event = new NewUrgentTicketEvent(
        ticketId: 'ticket-1',
        studentId: 'student-1',
        subject: 'Payment issue',
    );

    expect($event->broadcastOn())->toHaveCount(1);
    expect($event->broadcastAs())->toBe('ticket.urgent');
});

it('broadcasts LiveStartingSoonEvent on admin and instructor channels', function () {
    $event = new LiveStartingSoonEvent(
        liveSessionId: 'live-1',
        courseId: 'course-1',
        courseTitle: 'Scrum Fundamentals',
        instructorId: 'instructor-1',
        minutesUntilStart: 10,
    );

    expect($event->broadcastOn())->toHaveCount(2);
    expect($event->broadcastAs())->toBe('live.starting-soon');
    expect($event->broadcastWith())->toHaveKeys(['live_session_id', 'course_id', 'course_title', 'minutes_until_start']);
});

it('broadcasts CourseAccessGrantedEvent on user channel only', function () {
    $event = new CourseAccessGrantedEvent(
        studentId: 'student-1',
        courseId: 'course-1',
        courseTitle: 'Scrum Fundamentals',
    );

    expect($event->broadcastOn())->toHaveCount(1);
    expect($event->broadcastAs())->toBe('course.access-granted');
});
