<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Iskenda',
            'email' => 'admin@iskenda.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        User::factory()->admin()->create([
            'name' => 'Super Admin',
            'email' => 'super@iskenda.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin users created: admin@iskenda.com / super@iskenda.com (password: password)');
    }
}
