<?php

namespace App\Http\Middleware;

use Closure;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Services\LiveSessionTokenService;
use Symfony\Component\HttpFoundation\Response;

class ValidateLiveSessionToken
{
    public function __construct(
        private LiveSessionTokenService $tokenService,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');
        $liveSessionId = $request->route('liveSessionId');

        if (! $token || ! $liveSessionId) {
            return response()->json(['message' => 'Missing token or session ID.'], 400);
        }

        $session = LiveSession::with('lesson.module.course')->find($liveSessionId);

        if ($session === null) {
            return response()->json(['message' => 'Live session not found.'], 404);
        }

        if ($session->status !== 'live') {
            $this->audit($request, 'live.join.denied', $session, 'Session not active');

            return response()->json(['message' => 'Live session is not active.'], 422);
        }

        $studentId = $request->user()->id;

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

        $consumed = $this->tokenService->consume(
            sessionId: $session->id,
            studentId: $studentId,
            token: $token,
        );

        if (! $consumed) {
            $this->audit($request, 'live.join.denied', $session, 'Invalid or expired token');

            return response()->json(['message' => 'Invalid or expired token.'], 403);
        }

        $this->audit($request, 'live.join.success', $session);

        return redirect()->away($session->raw_link);
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
