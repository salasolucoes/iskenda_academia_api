<?php

namespace Domain\Course\Entities;

use Domain\Course\ValueObjects\LiveSessionStatus;

class LiveSession
{
    public function __construct(
        private string $id,
        private string $lessonId,
        private string $streamKey,
        private ?\DateTimeImmutable $scheduledStart,
        private ?\DateTimeImmutable $actualStart,
        private ?\DateTimeImmutable $actualEnd,
        private LiveSessionStatus $status,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLessonId(): string
    {
        return $this->lessonId;
    }

    public function getStreamKey(): string
    {
        return $this->streamKey;
    }

    public function getScheduledStart(): ?\DateTimeImmutable
    {
        return $this->scheduledStart;
    }

    public function getActualStart(): ?\DateTimeImmutable
    {
        return $this->actualStart;
    }

    public function getActualEnd(): ?\DateTimeImmutable
    {
        return $this->actualEnd;
    }

    public function getStatus(): LiveSessionStatus
    {
        return $this->status;
    }

    public function start(): void
    {
        $this->status = LiveSessionStatus::Live;
        $this->actualStart = new \DateTimeImmutable;
    }

    public function end(): void
    {
        $this->status = LiveSessionStatus::Ended;
        $this->actualEnd = new \DateTimeImmutable;
    }
}
