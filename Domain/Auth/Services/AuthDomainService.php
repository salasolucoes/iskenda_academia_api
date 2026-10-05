<?php

namespace Domain\Auth\Services;

use Domain\Auth\ValueObjects\Role;

class AuthDomainService
{
    public function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function generateStrongOtp(): string
    {
        $uppercase = chr(random_int(65, 90));
        $lowercase = chr(random_int(97, 122));
        $special = ['!', '@', '#', '$', '%', '^', '&', '*'][random_int(0, 7)];

        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        $length = 8;

        $otp = $uppercase.$lowercase.$special;
        for ($i = strlen($otp); $i < $length; $i++) {
            $otp .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return str_shuffle($otp);
    }

    public function isPasswordStrongEnough(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (! preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (! preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    public function passwordValidationMessage(): string
    {
        return 'A senha deve ter pelo menos 8 caracteres, incluindo uma maiúscula, uma minúscula e um carácter especial.';
    }

    public function roleCanRegister(Role $role): bool
    {
        return match ($role) {
            Role::Student => true,
            Role::Instructor => false,
            Role::Admin => false,
        };
    }
}
