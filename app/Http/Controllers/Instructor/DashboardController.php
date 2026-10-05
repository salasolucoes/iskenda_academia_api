<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instructorId = $request->user()->id;

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        $activeCourses = $courseIds->count();

        $totalStudents = Enrollment::whereIn('course_id', $courseIds)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count('student_id');

        $scheduledLiveSessions = LiveSession::whereIn('lesson_id', function ($q) use ($courseIds) {
            $q->select('id')
                ->from('lessons')
                ->whereIn('module_id', function ($q2) use ($courseIds) {
                    $q2->select('id')
                        ->from('modules')
                        ->whereIn('course_id', $courseIds);
                });
        })
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_start')
            ->with('lesson.module.course:id,title')
            ->orderBy('scheduled_start')
            ->get();

        $recentEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->with(['student:id,name,email,avatar_url', 'course:id,title'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'total_students' => $totalStudents,
                'active_courses' => $activeCourses,
                'scheduled_live_sessions_count' => $scheduledLiveSessions->count(),
                'scheduled_live_sessions' => $scheduledLiveSessions->map(fn ($ls) => [
                    'id' => $ls->id,
                    'lesson_title' => $ls->lesson?->title,
                    'course_title' => $ls->lesson?->module?->course?->title,
                    'course_id' => $ls->lesson?->module?->course?->id,
                    'scheduled_start' => $ls->scheduled_start?->toIso8601String(),
                    'stream_key' => $ls->stream_key,
                ]),
                'recent_enrollments' => $recentEnrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'student_name' => $e->student?->name,
                    'student_email' => $e->student?->email,
                    'course_title' => $e->course?->title,
                    'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
