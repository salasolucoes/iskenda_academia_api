<?php

namespace Infrastructure\Services;

use Application\UseCases\Auth\OtpSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService implements OtpSender
{
    public function send(string $email, string $otpCode, bool $isInstructor = false): void
    {
        Log::info("OTP sent to {$email}: {$otpCode}");

        $body = $isInstructor
            ? $this->buildInstructorEmail($otpCode)
            : $this->buildStudentEmail($otpCode);

        Mail::raw($body, function ($message) use ($email, $isInstructor) {
            $subject = $isInstructor
                ? 'Palavra-passe Temporária — Iskenda Academy'
                : 'Código de Verificação — Iskenda Academy';

            $message->to($email)
                ->subject($subject);
        });
    }

    private function buildStudentEmail(string $otpCode): string
    {
        return "Olá,\n\n"
            ."Recebemos o seu pedido de verificação na Iskenda Academy.\n\n"
            ."O seu código de verificação é: {$otpCode}\n\n"
            ."Use este código para confirmar o seu email.\n\n"
            ."Este código é válido por 10 minutos.\n\n"
            ."Se não solicitou este código, ignore este e-mail.\n\n"
            ."Atenciosamente,\nEquipa Iskenda Academy";
    }

    private function buildInstructorEmail(string $otpCode): string
    {
        return "Olá,\n\n"
            ."A sua palavra-passe temporária é: {$otpCode}\n\n"
            ."Faça login com o seu email e esta palavra-passe.\n\n"
            ."⚠️ O sistema irá pedir-lhe que defina uma nova palavra-passe.\n"
            ."A sua nova palavra-passe DEVE conter:\n"
            ."   ✓ No mínimo 8 caracteres\n"
            ."   ✓ Pelo menos 1 letra MAIÚSCULA (A-Z)\n"
            ."   ✓ Pelo menos 1 letra minúscula (a-z)\n"
            ."   ✓ Pelo menos 1 carácter especial (!@#\$%^&*)\n\n"
            ."Este código é válido por 10 minutos.\n\n"
            ."Se não solicitou este e-mail, ignore-o.\n\n"
            ."Atenciosamente,\nEquipa Iskenda Academy";
    }
}
