<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Services\LiveSessionTokenService;

class LiveSessionController extends Controller
{
    public function __construct(
        private LiveSessionTokenService $tokenService,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function join(string $liveSessionId, Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $session = LiveSession::with('lesson.module.course')->find($liveSessionId);

        if ($session === null) {
            return response()->json(['message' => 'Live session not found.'], 404);
        }

        if ($session->status !== 'live' && $session->status !== 'scheduled') {
            $this->audit($request, 'live.join.denied', $session, 'Session not available');

            return response()->json(['message' => 'Live session is not available.'], 422);
        }

        $courseId = $session->lesson->module->course_id ?? null;

        if ($courseId === null) {
            return response()->json(['message' => 'Course not found for this session.'], 404);
        }

        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            $this->audit($request, 'live.join.denied', $session, 'Not enrolled');

            return response()->json(['message' => 'You are not enrolled in this course.'], 403);
        }

        if (empty($session->raw_link)) {
            return response()->json(['message' => 'Transmission link not configured yet.'], 422);
        }

        $token = $this->tokenService->issue(
            sessionId: $session->id,
            studentId: $studentId,
        );

        $this->audit($request, 'live.join', $session);

        return response()->json([
            'data' => [
                'join_url' => route('live.join', ['liveSessionId' => $session->id, 'token' => $token]),
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }

    public function redirect(string $liveSessionId): JsonResponse
    {
        return response()->json(['message' => 'Token validation failed.'], 403);
    }

    private function audit(Request $request, string $eventType, LiveSession $session, ?string $reason = null): void
    {
        $user = $request->user();

        $this->auditLogger->log(
            eventType: $eventType,
            auditable: $session,
            actor: new ActorContext(
                actorId: $user?->id,
                actorRole: $user?->role,
                actorIp: $request->ip(),
            ),
            newState: $reason ? ['reason' => $reason] : null,
        );
    }
}
