<?php

namespace Application\UseCases\Auth;

interface OtpSender
{
    public function send(string $email, string $otpCode, bool $isInstructor = false): void;
}
