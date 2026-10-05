<?php

namespace App\Jobs;

use App\Events\LiveStartingSoonEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class LiveStartingSoonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $upcoming = LiveSession::where('status', 'scheduled')
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [now(), now()->addMinutes(15)])
            ->with('lesson.module.course')
            ->get();

        foreach ($upcoming as $session) {
            $minutesUntilStart = (int) now()->diffInMinutes($session->scheduled_start, false);

            if ($minutesUntilStart < 0) {
                continue;
            }

            $instructorId = $session->lesson?->module?->course?->instructor_id;
            if (! $instructorId) {
                continue;
            }

            event(new LiveStartingSoonEvent(
                liveSessionId: $session->id,
                courseId: $session->lesson->module->course_id,
                courseTitle: $session->lesson->module->course->title,
                instructorId: $instructorId,
                minutesUntilStart: $minutesUntilStart,
            ));
        }
    }
}
