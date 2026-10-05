<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\Module;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instructorId = $request->user()->id;

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        // Batch query: map module_id → course_id
        $moduleCourseMap = Module::whereIn('course_id', $courseIds)
            ->pluck('course_id', 'id');

        // Batch query: count lessons per module
        $lessonsPerModule = Lesson::whereIn('module_id', $moduleCourseMap->keys())
            ->selectRaw('module_id, count(*) as cnt')
            ->groupBy('module_id')
            ->pluck('cnt', 'module_id');

        // Aggregate in PHP: sum lessons per course
        $lessonsPerCourse = [];
        foreach ($moduleCourseMap as $moduleId => $courseId) {
            $lessonsPerCourse[$courseId] = ($lessonsPerCourse[$courseId] ?? 0) + (int) $lessonsPerModule->get($moduleId, 0);
        }

        $enrollments = Enrollment::whereIn('course_id', $courseIds)
            ->with(['student:id,name,email', 'course:id,title', 'certificate'])
            ->withCount([
                'progress as completed_lessons_count' => fn ($q) => $q->where('is_completed', true),
            ])
            ->orderByDesc('enrolled_at')
            ->paginate($request->integer('per_page', 20));

        $data = $enrollments->getCollection()->map(function ($enrollment) use ($lessonsPerCourse) {
            $totalLessons = $lessonsPerCourse[$enrollment->course_id] ?? 0;
            $completed = $enrollment->completed_lessons_count ?? 0;
            $progress = $totalLessons > 0 ? round(($completed / $totalLessons) * 100) : 0;

            return [
                'id' => $enrollment->id,
                'name' => $enrollment->student?->name ?? '',
                'email' => $enrollment->student?->email ?? '',
                'course' => $enrollment->course?->title ?? '',
                'progress' => $progress,
                'joined' => $enrollment->enrolled_at?->toIso8601String(),
                'cert_emitted' => $enrollment->certificate !== null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }
}
