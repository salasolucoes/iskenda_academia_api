<?php

namespace Application\UseCases\Admin;

use App\Models\User;
use Domain\Auth\Entities\User as DomainUser;
use Domain\Auth\Services\AuthDomainService;

class CompleteInstructorSetupUseCase
{
    public function __construct(
        private AuthDomainService $authDomainService,
    ) {}

    public function execute(string $email, string $otpCode, string $password): User
    {
        $user = User::where('role', 'instructor')
            ->where('email', $email)
            ->first();

        if ($user === null) {
            throw new \InvalidArgumentException('Instrutor não encontrado com este email.');
        }

        if ($user->email_verified_at !== null) {
            throw new \InvalidArgumentException('Este instrutor já completou o setup.');
        }

        if ($user->otp_code === null || $user->otp_expires_at === null) {
            throw new \InvalidArgumentException('Código OTP inválido. Solicite um novo.');
        }

        // hash_equals: comparação não constante, igual à usada na verificação
        // de e-mail. A senha temporária do instrutor é o segredo mais fraco
        // do fluxo — a comparação não pode revelar nada sobre o valor correcto.
        if (! hash_equals($user->otp_code, $otpCode)) {
            $this->registerFailedOtpAttempt($user);

            throw new \InvalidArgumentException('Código OTP inválido.');
        }

        if ($user->otp_expires_at->isPast()) {
            throw new \InvalidArgumentException('Código OTP expirado. Solicite um novo.');
        }

        if (! $this->authDomainService->isPasswordStrongEnough($password)) {
            throw new \InvalidArgumentException($this->authDomainService->passwordValidationMessage());
        }

        $user->forceFill([
            'password' => bcrypt($password),
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();

        return $user;
    }

    /**
     * Incrementa as tentativas e invalida a senha temporária ao atingir o limite.
     * Sem isto, a senha temporária de 8 caracteres fica sujeita a força bruta
     * ilimitada — é o segredo mais fraco de todo o fluxo de autenticação.
     */
    private function registerFailedOtpAttempt(User $user): void
    {
        $attempts = $user->otp_attempts + 1;

        $user->forceFill([
            'otp_attempts' => $attempts >= DomainUser::MAX_OTP_ATTEMPTS ? 0 : $attempts,
            'otp_code' => $attempts >= DomainUser::MAX_OTP_ATTEMPTS ? null : $user->otp_code,
            'otp_expires_at' => $attempts >= DomainUser::MAX_OTP_ATTEMPTS ? null : $user->otp_expires_at,
        ])->save();
    }
}
