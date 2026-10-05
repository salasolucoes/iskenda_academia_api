<?php

namespace Application\UseCases\Auth;

use Domain\Auth\Contracts\AuthRepositoryInterface;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;

class VerifyOtpUseCase
{
    public function __construct(
        private AuthRepositoryInterface $authRepository,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function execute(string $email, string $otpCode, ?ActorContext $actor = null): string
    {
        $user = $this->authRepository->findByEmail($email);

        if ($user === null) {
            throw new \InvalidArgumentException('Usuário não encontrado.');
        }

        $actorContext = $actor ?? new ActorContext;

        if (! $user->verifyOtp($otpCode)) {
            // Tem de persistir em FALHA: verifyOtp() incrementa o contador de
            // tentativas e invalida o código ao atingir MAX_OTP_ATTEMPTS. Sem este
            // save() o contador vive em memória e morre no fim do request — o
            // limite nunca acumularia e a protecção seria apenas aparência.
            $this->authRepository->save($user);

            $this->auditLogger->log(
                'auth.otp.failed',
                $user,
                $actorContext,
            );

            throw new \InvalidArgumentException('Código OTP inválido ou expirado.');
        }

        $user->markEmailAsVerified();
        $this->authRepository->save($user);

        $this->auditLogger->log(
            'auth.otp.verified',
            $user,
            $actorContext,
        );

        return $user->getId();
    }
}
