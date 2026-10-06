<?php

namespace Infrastructure\Persistence\Repositories;

use App\Models\User as EloquentUser;
use Domain\Auth\Contracts\AuthRepositoryInterface;
use Domain\Auth\Entities\User as DomainUser;
use Domain\Auth\ValueObjects\Role;

class EloquentAuthRepository implements AuthRepositoryInterface
{
    public function findByEmail(string $email): ?DomainUser
    {
        $eloquent = EloquentUser::where('email', $email)->first();

        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function findById(string $id): ?DomainUser
    {
        $eloquent = EloquentUser::find($id);

        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function save(DomainUser $user): DomainUser
    {
        $eloquent = EloquentUser::updateOrCreate(
            ['id' => $user->getId()],
            [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'password' => $user->getPassword(),
                'role' => $user->getRole()->value,
                'is_active' => $user->isActive(),
                'otp_code' => $user->getOtpCode(),
                'otp_expires_at' => $user->getOtpExpiresAt(),
                'otp_attempts' => $user->getOtpAttempts(),
                'email_verified_at' => $user->getEmailVerifiedAt(),
            ]
        );

        return $this->toDomain($eloquent->fresh());
    }

    public function delete(string $id): void
    {
        EloquentUser::findOrFail($id)->delete();
    }

    private function toDomain(EloquentUser $eloquent): DomainUser
    {
        return new DomainUser(
            id: $eloquent->id,
            name: $eloquent->name,
            email: $eloquent->email,
            phone: $eloquent->phone,
            password: $eloquent->password,
            role: Role::from($eloquent->role),
            isActive: $eloquent->is_active,
            otpCode: $eloquent->otp_code,
            otpExpiresAt: $eloquent->otp_expires_at?->toImmutable(),
            otpAttempts: $eloquent->otp_attempts ?? 0,
            emailVerifiedAt: $eloquent->email_verified_at?->toImmutable(),
        );
    }
}
