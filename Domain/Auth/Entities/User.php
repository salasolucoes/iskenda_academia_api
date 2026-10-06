<?php

namespace Domain\Auth\Entities;

use Domain\Auth\ValueObjects\Role;

class User
{
    /**
     * Tentativas de verificação de OTP antes de o código ser invalidado.
     * Um limite por IP não impede um atacante lento que varre o espaço de
     * chaves ao longo da validade do código; obrigar a reenvio é que fecha.
     */
    public const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        private string $id,
        private string $name,
        private string $email,
        private ?string $phone,
        private string $password,
        private Role $role,
        private bool $isActive,
        private ?string $otpCode,
        private ?\DateTimeImmutable $otpExpiresAt,
        private int $otpAttempts = 0,
        private ?\DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getOtpCode(): ?string
    {
        return $this->otpCode;
    }

    public function getOtpExpiresAt(): ?\DateTimeImmutable
    {
        return $this->otpExpiresAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isOtpExpired(): bool
    {
        if ($this->otpExpiresAt === null) {
            return true;
        }

        return $this->otpExpiresAt < new \DateTimeImmutable;
    }

    public function getOtpAttempts(): int
    {
        return $this->otpAttempts;
    }

    /**
     * Verifica o OTP. Uma tentativa errada incrementa o contador; ao atingir
     * MAX_OTP_ATTEMPTS o código é invalidado e o utilizador tem de pedir reenvio.
     * O incremento só tem efeito se o chamador persistir a entidade.
     */
    public function verifyOtp(string $code): bool
    {
        if ($this->otpCode === null || $this->isOtpExpired()) {
            return false;
        }

        if (! hash_equals($this->otpCode, $code)) {
            $this->registerFailedOtpAttempt();

            return false;
        }

        return true;
    }

    private function registerFailedOtpAttempt(): void
    {
        $this->otpAttempts++;

        if ($this->otpAttempts >= self::MAX_OTP_ATTEMPTS) {
            $this->otpCode = null;
            $this->otpExpiresAt = null;
            $this->otpAttempts = 0;
        }
    }

    public function markEmailAsVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable;
        $this->otpCode = null;
        $this->otpExpiresAt = null;
        $this->otpAttempts = 0;
    }
}
