<?php

use Domain\Auth\Services\AuthDomainService;

beforeEach(function () {
    $this->service = new AuthDomainService;
});

test('password without uppercase is rejected', function () {
    expect($this->service->isPasswordStrongEnough('senha@123'))->toBeFalse();
});

test('password without lowercase is rejected', function () {
    expect($this->service->isPasswordStrongEnough('SENHA@123'))->toBeFalse();
});

test('password without special character is rejected', function () {
    expect($this->service->isPasswordStrongEnough('Senha123'))->toBeFalse();
});

test('password shorter than 8 chars is rejected', function () {
    expect($this->service->isPasswordStrongEnough('S3nh@'))->toBeFalse();
});

test('password with all requirements passes', function () {
    expect($this->service->isPasswordStrongEnough('S3nh@F0rte'))->toBeTrue();
});

test('password with exactly 8 chars and all requirements passes', function () {
    expect($this->service->isPasswordStrongEnough('Ab1@cdEF'))->toBeTrue();
});

test('otp for student is numeric', function () {
    $otp = $this->service->generateOtp();

    expect($otp)->toMatch('/^\d{6}$/');
});

test('otp for student is 6 digits', function () {
    $otp = $this->service->generateOtp();

    expect(strlen($otp))->toBe(6);
});

test('otp for instructor contains at least one uppercase letter', function () {
    $otp = $this->service->generateStrongOtp();

    expect($otp)->toMatch('/[A-Z]/');
});

test('otp for instructor contains at least one lowercase letter', function () {
    $otp = $this->service->generateStrongOtp();

    expect($otp)->toMatch('/[a-z]/');
});

test('otp for instructor contains at least one special character', function () {
    $otp = $this->service->generateStrongOtp();

    expect($otp)->toMatch('/[^a-zA-Z0-9]/');
});

test('otp for instructor is at least 8 characters long', function () {
    $otp = $this->service->generateStrongOtp();

    expect(strlen($otp))->toBeGreaterThanOrEqual(8);
});

test('otp for instructor is unique on each generation', function () {
    $otps = [];
    for ($i = 0; $i < 10; $i++) {
        $otps[] = $this->service->generateStrongOtp();
    }

    expect(count(array_unique($otps)))->toBe(10);
});

test('password validation message is descriptive', function () {
    $message = $this->service->passwordValidationMessage();

    expect($message)->toContain('maiúscula')
        ->and($message)->toContain('minúscula')
        ->and($message)->toContain('carácter especial');
});
