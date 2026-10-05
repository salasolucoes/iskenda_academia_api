<?php

namespace Application\UseCases\Auth;

use Domain\Auth\Contracts\AuthRepositoryInterface;
use Domain\Auth\Entities\User as DomainUser;
use Domain\Auth\Services\AuthDomainService;
use Domain\Auth\ValueObjects\Role;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Support\Str;

class RegisterStudentUseCase
{
    public function __construct(
        private AuthRepositoryInterface $authRepository,
        private AuthDomainService $authDomainService,
        private OtpSender $otpSender,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function execute(string $name, string $email, string $password, ?string $phone = null, ?ActorContext $actor = null): DomainUser
    {
        if (! $this->authDomainService->isPasswordStrongEnough($password)) {
            throw new \InvalidArgumentException($this->authDomainService->passwordValidationMessage());
        }

        $existing = $this->authRepository->findByEmail($email);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Já existe um usuário com este email.');
        }

        $otpCode = $this->authDomainService->generateOtp();
        $otpExpiresAt = new \DateTimeImmutable('+10 minutes');

        $user = new DomainUser(
            id: (string) Str::uuid(),
            name: $name,
            email: $email,
            phone: $phone,
            password: bcrypt($password),
            role: Role::Student,
            isActive: true,
            otpCode: $otpCode,
            otpExpiresAt: $otpExpiresAt,
            emailVerifiedAt: null,
        );

        $this->authRepository->save($user);
        $this->otpSender->send($email, $otpCode);

        $this->auditLogger->log(
            'auth.register',
            $user,
            $actor ?? new ActorContext,
            newState: ['email' => $email, 'role' => 'student'],
        );

        return $user;
    }
}
