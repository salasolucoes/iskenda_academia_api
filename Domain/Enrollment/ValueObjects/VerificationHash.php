<?php

namespace Domain\Enrollment\ValueObjects;

class VerificationHash
{
    public function __construct(
        private readonly string $hash,
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new \InvalidArgumentException('Verification hash must be a valid SHA-256 hex string.');
        }
    }

    public function getValue(): string
    {
        return $this->hash;
    }

    public static function generate(string $data): self
    {
        return new self(hash('sha256', $data));
    }

    public function equals(VerificationHash $other): bool
    {
        return $this->hash === $other->hash;
    }
}
