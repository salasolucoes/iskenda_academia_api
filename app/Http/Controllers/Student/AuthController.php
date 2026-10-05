<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use Application\UseCases\Auth\RegisterStudentUseCase;
use Application\UseCases\Auth\VerifyOtpUseCase;
use Domain\Auth\Contracts\AuthRepositoryInterface;
use Domain\Auth\Services\AuthDomainService;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Infrastructure\Services\OtpService;

class AuthController extends Controller
{
    public function __construct(
        private RegisterStudentUseCase $registerStudentUseCase,
        private VerifyOtpUseCase $verifyOtpUseCase,
        private AuthRepositoryInterface $authRepository,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $user = $this->registerStudentUseCase->execute(
                name: $request->input('name'),
                email: $request->input('email'),
                password: $request->input('password'),
                phone: $request->input('phone'),
                actor: new ActorContext(actorIp: $request->ip()),
            );

            return response()->json([
                'message' => 'Cadastro realizado com sucesso. Verifique seu email para o código OTP.',
                'user_id' => $user->getId(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $key = str_contains($e->getMessage(), 'senha') ? 'password' : 'email';

            throw ValidationException::withMessages([
                $key => [$e->getMessage()],
            ]);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $userId = $this->verifyOtpUseCase->execute(
                email: $request->input('email'),
                otpCode: $request->input('otp_code'),
                actor: new ActorContext(actorIp: $request->ip()),
            );

            $user = User::findOrFail($userId);
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'otp_code' => [$e->getMessage()],
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
            $this->auditLogger->log(
                'auth.login.failed',
                'auth',
                new ActorContext(actorIp: $request->ip()),
                newState: ['email' => $request->input('email')],
            );

            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // Instructors created by admin have password = bcrypt(otp).
        // OTP was sent as temporary password — login succeeds, but must set a permanent password.
        if ($user->role === 'instructor' && $user->email_verified_at === null) {
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                ],
                'must_change_password' => true,
                'message' => 'Bem-vindo! A sua conta ainda não está ativada. Defina a sua palavra-passe permanente para ativar o acesso.',
            ]);
        }

        // Students who registered but haven't verified their email yet
        if ($user->email_verified_at === null) {
            $otpService = app(OtpService::class);
            $domainService = app(AuthDomainService::class);
            $otpCode = $domainService->generateOtp();
            $user->forceFill([
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addMinutes(10),
            ]);
            $user->save();

            $otpService->send($user->email, $otpCode);

            return response()->json([
                'message' => 'Email não verificado. Um novo OTP foi enviado para seu email.',
                'requires_otp' => true,
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->auditLogger->log(
            'auth.login',
            $user,
            new ActorContext(actorId: $user->id, actorRole: $user->role, actorIp: $request->ip()),
        );

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }
}
