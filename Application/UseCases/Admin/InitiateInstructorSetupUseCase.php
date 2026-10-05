<?php

namespace Application\UseCases\Admin;

use App\Models\User;
use Domain\Auth\Services\AuthDomainService;
use Infrastructure\Services\OtpService;

class InitiateInstructorSetupUseCase
{
    public function __construct(
        private AuthDomainService $authDomainService,
        private OtpService $otpService,
    ) {}

    public function execute(string $email): void
    {
        $user = User::where('role', 'instructor')
            ->where('email', $email)
            ->first();

        if ($user === null) {
            throw new \InvalidArgumentException('Instrutor não encontrado com este email.');
        }

        if ($user->email_verified_at !== null) {
            throw new \InvalidArgumentException('Este instrutor já possui o email verificado.');
        }

        $otpCode = $this->authDomainService->generateOtp();

        $user->forceFill([
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->otpService->send($email, $otpCode);
    }
}
