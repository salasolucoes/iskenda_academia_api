<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstructorUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->instructor()->create([
            'name' => 'Rui Santos',
            'email' => 'rui@iskenda.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        User::factory()->instructor()->create([
            'name' => 'Ana Martins',
            'email' => 'ana@iskenda.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('Instructor users created: rui@iskenda.com / ana@iskenda.com (password: password)');
    }
}
