<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstructorResource;
use Application\UseCases\Admin\CompleteInstructorSetupUseCase;
use Application\UseCases\Admin\InitiateInstructorSetupUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InstructorAuthController extends Controller
{
    public function __construct(
        private InitiateInstructorSetupUseCase $initiateInstructorSetupUseCase,
        private CompleteInstructorSetupUseCase $completeInstructorSetupUseCase,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            $this->initiateInstructorSetupUseCase->execute(
                email: $data['email'],
            );

            return response()->json([
                'message' => 'Código OTP enviado para o seu email.',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp_code' => ['required', 'string', 'min:8', 'max:12'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = $this->completeInstructorSetupUseCase->execute(
                email: $data['email'],
                otpCode: $data['otp_code'],
                password: $data['password'],
            );

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => new InstructorResource($user),
                'message' => 'Setup concluído com sucesso.',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'otp_code' => [$e->getMessage()],
            ]);
        }
    }

    public function setPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if ($user->role !== 'instructor') {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Palavra-passe definida com sucesso. A sua conta está ativa.',
        ]);
    }
}
