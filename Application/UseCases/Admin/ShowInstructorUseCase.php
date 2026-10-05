<?php

namespace Application\UseCases\Admin;

use App\Models\User;

class ShowInstructorUseCase
{
    public function execute(string $id): ?User
    {
        return User::where('role', 'instructor')
            ->where('id', $id)
            ->first();
    }
}
