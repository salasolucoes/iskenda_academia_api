<?php

namespace Application\UseCases\Admin;

use App\Models\User;
use Domain\Auth\Services\AuthDomainService;
use Infrastructure\Services\OtpService;

class CreateInstructorUseCase
{
    public function __construct(
        private AuthDomainService $authDomainService,
        private OtpService $otpService,
    ) {}

    public function execute(string $name, string $email, ?string $phone = null): User
    {
        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            throw new \InvalidArgumentException('Já existe um utilizador com este email.');
        }

        $otpCode = $this->authDomainService->generateStrongOtp();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt($otpCode),
            'role' => 'instructor',
            'is_active' => true,
            'email_verified_at' => null,
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->otpService->send($email, $otpCode, isInstructor: true);

        return $user;
    }
}
