<?php

namespace Domain\Support\Contracts;

use Domain\Support\ValueObjects\ActorContext;

interface AuditLoggerInterface
{
    public function log(
        string $eventType,
        mixed $auditable,
        ActorContext $actor,
        ?array $previousState = null,
        ?array $newState = null,
    ): void;
}
