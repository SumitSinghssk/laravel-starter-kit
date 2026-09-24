<?php

namespace Database\Seeders;

use App\Enums\CommonStatusEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'superadmin@gmail.com',
                'name' => 'Super Admin',
                'role' => 'super admin',
            ],
            [
                'email' => 'admin@gmail.com',
                'name' => 'Admin User',
                'role' => 'admin',
            ],
            [
                'email' => 'developer@gmail.com',
                'name' => 'Developer User',
                'role' => 'developer',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'status' => CommonStatusEnum::ACTIVE->value,
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
