<?php

namespace Application\UseCases\Admin;

use App\Models\User;

class DeleteInstructorUseCase
{
    public function execute(string $id): void
    {
        $user = User::where('role', 'instructor')
            ->where('id', $id)
            ->first();

        if ($user === null) {
            throw new \InvalidArgumentException('Instrutor não encontrado.');
        }

        $user->delete();
    }
}
