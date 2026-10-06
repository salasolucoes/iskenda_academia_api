<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Domain\Enrollment\Services\EnrollmentDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;

class LessonProgressController extends Controller
{
    public function __construct(
        private EnrollmentDomainService $enrollmentDomainService,
    ) {}

    public function update(string $lessonId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0'],
            'last_position_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $watchedSeconds = (int) $validated['watched_seconds'];

        $studentId = $request->user()->id;
        $lesson = Lesson::findOrFail($lessonId);

        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('course_id', $lesson->module->course_id)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if ($enrollment === null) {
            return response()->json(['message' => 'Not enrolled in this course.'], 403);
        }

        $isCompleted = $this->enrollmentDomainService->markLessonComplete(
            $watchedSeconds,
            $this->resolveAuthoritativeDurationSeconds($lesson),
        );

        DB::transaction(function () use ($enrollment, $lessonId, $validated, $watchedSeconds, $isCompleted, $lesson) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lessonId,
                ],
                [
                    'watched_seconds' => $watchedSeconds,
                    'last_position_seconds' => (int) $validated['last_position_seconds'],
                    'is_completed' => $isCompleted,
                    'last_activity_at' => now(),
                ]
            );

            if ($isCompleted && $enrollment->status === 'active') {
                $this->checkAndCompleteEnrollment($enrollment, $lesson);
            }
        });

        return response()->json([
            'data' => [
                'is_completed' => $isCompleted,
                'watched_seconds' => $watchedSeconds,
            ],
        ]);
    }

    /**
     * A duração vem sempre da lição — nunca do cliente. `duration_minutes` é
     * nullable, por isso devolve null (conclusão manual) quando não foi definido.
     */
    private function resolveAuthoritativeDurationSeconds(Lesson $lesson): ?int
    {
        if ($lesson->duration_minutes === null || $lesson->duration_minutes <= 0) {
            return null;
        }

        return $lesson->duration_minutes * 60;
    }

    private function checkAndCompleteEnrollment(Enrollment $enrollment, Lesson $lesson): void
    {
        $courseId = $lesson->module->course_id;

        $totalLessons = Lesson::whereHas('module', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->count();

        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = LessonProgress::where('enrollment_id', $enrollment->id)
            ->where('is_completed', true)
            ->count();

        if ($completedLessons >= $totalLessons) {
            $enrollment->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        }
    }
}
