<?php

namespace Domain\Support\ValueObjects;

class ActorContext
{
    public function __construct(
        public readonly ?string $actorId = null,
        public readonly ?string $actorRole = null,
        public readonly ?string $actorIp = null,
    ) {}
}
