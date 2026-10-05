<?php

namespace Application\UseCases\Admin;

use App\Models\User;

class UpdateInstructorUseCase
{
    public function execute(string $id, string $name, string $email, ?string $phone = null): User
    {
        $user = User::where('role', 'instructor')
            ->where('id', $id)
            ->first();

        if ($user === null) {
            throw new \InvalidArgumentException('Instrutor não encontrado.');
        }

        $existing = User::where('email', $email)
            ->where('id', '!=', $id)
            ->first();

        if ($existing !== null) {
            throw new \InvalidArgumentException('Já existe um utilizador com este email.');
        }

        $user->update([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        return $user->fresh();
    }
}
