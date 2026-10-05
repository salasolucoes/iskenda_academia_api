<?php

namespace Application\UseCases\Enrollment;

use App\Events\CourseAccessGrantedEvent;
use App\Events\NewStudentEnrolledEvent;
use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Domain\Enrollment\Entities\Enrollment;
use Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueEnrollmentUseCase
{
    public function __construct(
        private EnrollmentRepositoryInterface $enrollmentRepository,
        private CourseRepositoryInterface $courseRepository,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function execute(string $studentId, string $courseId, ?string $orderId = null, ?ActorContext $actor = null): Enrollment
    {
        $existing = $this->enrollmentRepository->findByStudentAndCourse($studentId, $courseId);

        if ($existing !== null && $existing->getStatus() === EnrollmentStatus::Active) {
            throw new \DomainException('Student is already enrolled in this course.');
        }

        $enrollment = new Enrollment(
            id: (string) Str::uuid(),
            studentId: $studentId,
            courseId: $courseId,
            orderId: $orderId,
            status: EnrollmentStatus::Active,
            enrolledAt: new \DateTimeImmutable,
        );

        $saved = $this->enrollmentRepository->save($enrollment);

        $this->auditLogger->log(
            'enrollment.created',
            $saved,
            $actor ?? new ActorContext,
            newState: ['student_id' => $studentId, 'course_id' => $courseId, 'order_id' => $orderId],
        );

        $course = $this->courseRepository->findById($courseId);
        if ($course) {
            $studentName = DB::table('users')->where('id', $studentId)->value('name') ?? '';
            event(new NewStudentEnrolledEvent(
                studentId: $studentId,
                studentName: $studentName,
                courseId: $courseId,
                courseTitle: $course->getTitle(),
                instructorId: $course->getInstructorId(),
            ));
            event(new CourseAccessGrantedEvent(
                studentId: $studentId,
                courseId: $courseId,
                courseTitle: $course->getTitle(),
            ));
        }

        return $saved;
    }
}
