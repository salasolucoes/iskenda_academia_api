<?php

namespace Application\UseCases\Admin;

use App\Models\User;

class ListInstructorsUseCase
{
    public function execute(): array
    {
        return User::where('role', 'instructor')
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }
}
