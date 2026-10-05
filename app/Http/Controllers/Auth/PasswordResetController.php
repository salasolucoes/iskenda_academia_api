<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Domain\Auth\Entities\User as DomainUser;
use Domain\Auth\Services\AuthDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Infrastructure\Services\OtpService;

class PasswordResetController extends Controller
{
    public function __construct(
        private AuthDomainService $domainService,
        private OtpService $otpService,
    ) {}

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $otpCode = $this->domainService->generateOtp();

            $user->forceFill([
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addMinutes(10),
                'otp_attempts' => 0,
            ])->save();

            $this->otpService->send($email, $otpCode);
        }

        return response()->json([
            'message' => 'Se o email estiver registado, receberá um código de recuperação.',
        ]);
    }

    public function verifyResetOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['Email não encontrado.'],
            ]);
        }

        if ($user->otp_code === null || $user->otp_expires_at === null) {
            throw ValidationException::withMessages([
                'otp_code' => ['Código inválido. Solicite um novo.'],
            ]);
        }

        // hash_equals: comparação não constante. Este é o caminho de tomada
        // de conta mais directo da aplicação — o código é a única barreira.
        if (! hash_equals($user->otp_code, (string) $request->input('otp_code'))) {
            $this->registerFailedOtpAttempt($user);

            throw ValidationException::withMessages([
                'otp_code' => ['Código inválido.'],
            ]);
        }

        if ($user->otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp_code' => ['Código expirado. Solicite um novo.'],
            ]);
        }

        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $request->input('email'),
            'token' => $token,
            'created_at' => now(),
        ]);

        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();

        return response()->json([
            'message' => 'Código verificado com sucesso.',
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->where('token', $request->input('token'))
            ->first();

        if ($tokenRow === null) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido ou expirado.'],
            ]);
        }

        $createdAt = Carbon::parse($tokenRow->created_at);
        if ($createdAt->diffInMinutes(now()) > 15) {
            DB::table('password_reset_tokens')
                ->where('email', $request->input('email'))
                ->delete();

            throw ValidationException::withMessages([
                'token' => ['Token expirado. Solicite um novo.'],
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['Email não encontrado.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
        ])->save();

        DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->delete();

        return response()->json([
            'message' => 'Palavra-passe redefinida com sucesso.',
        ]);
    }

    /**
     * Incrementa as tentativas e invalida o código ao atingir MAX_OTP_ATTEMPTS.
     * Um limite por IP/email não impede um atacante lento dentro da validade do
     * código; invalidar obriga a reenvio, e o reenvio é que fica limitado.
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
