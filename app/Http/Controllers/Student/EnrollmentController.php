<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\ModuleResource;
use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Domain\Enrollment\Entities\Enrollment;
use Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;
use Infrastructure\Persistence\Eloquent\Models\Module as ModuleModel;

class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentRepositoryInterface $enrollmentRepository,
        private CourseRepositoryInterface $courseRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $enrollments = $this->enrollmentRepository->findByStudentId($studentId);

        return response()->json([
            'data' => array_map(fn (Enrollment $e) => [
                'id' => $e->getId(),
                'course_id' => $e->getCourseId(),
                'status' => $e->getStatus()->value,
                'enrolled_at' => $e->getEnrolledAt()->format('Y-m-d H:i:s'),
                'completed_at' => $e->getCompletedAt()?->format('Y-m-d H:i:s'),
            ], $enrollments),
        ]);
    }

    public function classroom(string $enrollmentId, Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $enrollment = $this->enrollmentRepository->findById($enrollmentId);

        if ($enrollment === null || $enrollment->getStudentId() !== $studentId) {
            return response()->json(['message' => 'Enrollment not found.'], 404);
        }

        if ($enrollment->getStatus() === EnrollmentStatus::Cancelled) {
            return response()->json(['message' => 'Enrollment is cancelled.'], 403);
        }

        $course = $this->courseRepository->findById($enrollment->getCourseId());

        if ($course === null) {
            return response()->json(['message' => 'Course not found.'], 404);
        }

        $modules = ModuleModel::query()
            ->with('lessons')
            ->where('course_id', $enrollment->getCourseId())
            ->ordered()
            ->get();

        $progress = LessonProgress::query()
            ->where('enrollment_id', $enrollment->getId())
            ->get()
            ->map(fn (LessonProgress $row): array => [
                'lesson_id' => $row->lesson_id,
                'is_completed' => $row->is_completed,
                'watched_seconds' => $row->watched_seconds,
                'last_position_seconds' => $row->last_position_seconds,
            ])
            ->values();

        return response()->json([
            'data' => [
                'enrollment' => [
                    'id' => $enrollment->getId(),
                    'course_id' => $enrollment->getCourseId(),
                    'status' => $enrollment->getStatus()->value,
                ],
                'course' => array_merge(
                    (new CourseResource($course))->resolve(request()),
                    [
                        'modules' => ModuleResource::collection($modules)->resolve(request()),
                    ],
                ),
                'progress' => $progress,
            ],
        ]);
    }
}
