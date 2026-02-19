<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@heraldo.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('secret123'),
                'role' => 'admin',
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info('✓ Admin user created: admin@heraldo.local / secret123');
        } else {
            $this->command->info('Admin user already exists: admin@heraldo.local');
        }

        $this->command->warn('⚠ Change the password before deploying to production!');
    }
}
