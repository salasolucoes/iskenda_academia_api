<?php

namespace Application\UseCases\Course;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class CreateLiveLessonUseCase
{
    public function execute(
        string $moduleId,
        string $title,
        ?string $description,
        ?string $contentUrl,
        ?int $durationMinutes,
    ): Lesson {
        $scheduleData = $this->parseAndValidateContentUrl($contentUrl);

        return DB::transaction(function () use ($moduleId, $title, $description, $contentUrl, $durationMinutes, $scheduleData) {
            $maxOrder = Lesson::where('module_id', $moduleId)->max('order') ?? 0;

            $lesson = Lesson::create([
                'id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'title' => $title,
                'description' => $description,
                'type' => 'live',
                'content_url' => $contentUrl,
                'duration_minutes' => $durationMinutes,
                'order' => $maxOrder + 1,
            ]);

            $scheduledStart = new Carbon($scheduleData['scheduled_at']);
            if ($scheduledStart->isPast()) {
                throw new \InvalidArgumentException('A data de início da live não pode ser no passado.');
            }

            LiveSession::create([
                'id' => (string) Str::uuid(),
                'lesson_id' => $lesson->id,
                'stream_key' => Str::random(32),
                'raw_link' => $scheduleData['external_link'] ?? null,
                'scheduled_start' => $scheduledStart,
                'status' => 'scheduled',
            ]);

            return $lesson->load('liveSession');
        });
    }

    private function parseAndValidateContentUrl(?string $contentUrl): array
    {
        if ($contentUrl === null || $contentUrl === '') {
            throw new \InvalidArgumentException('Aulas ao vivo precisam de um agendamento (content_url).');
        }

        $data = json_decode($contentUrl, true);

        if (! is_array($data) || ! isset($data['scheduled_at'])) {
            throw new \InvalidArgumentException('O content_url de uma aula ao vivo deve conter scheduled_at.');
        }

        return $data;
    }
}
