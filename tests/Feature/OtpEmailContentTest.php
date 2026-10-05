<?php

use Infrastructure\Services\OtpService;

test('student email contains numeric otp and no password requirements', function () {
    $service = new OtpService;
    $reflection = new ReflectionClass($service);

    $method = $reflection->getMethod('buildStudentEmail');
    $method->setAccessible(true);

    $email = $method->invoke($service, '048291');

    expect($email)->toContain('048291')
        ->and($email)->toContain('código de verificação')
        ->and($email)->not->toContain('maiúscula')
        ->and($email)->not->toContain('minúscula')
        ->and($email)->not->toContain('especial');
});

test('instructor email contains strong otp and password requirements', function () {
    $service = new OtpService;
    $reflection = new ReflectionClass($service);

    $method = $reflection->getMethod('buildInstructorEmail');
    $method->setAccessible(true);

    $email = $method->invoke($service, 'Kx9#mP2v');

    expect($email)->toContain('Kx9#mP2v')
        ->and($email)->toContain('letra MAIÚSCULA')
        ->and($email)->toContain('letra minúscula')
        ->and($email)->toContain('carácter especial');
});
