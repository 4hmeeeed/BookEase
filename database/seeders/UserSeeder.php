<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Development accounts. Password for all three: "password".
 * Never run this seeder in production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            $this->command?->warn('UserSeeder skipped in production.');

            return;
        }

        $accounts = [
            ['Resort Owner', 'owner@bookease.test', UserRole::Admin],
            ['Front Desk Staff', 'staff@bookease.test', UserRole::Staff],
            ['Sample Guest', 'guest@bookease.test', UserRole::Guest],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => 'password']);
            $user->role = $role;
            $user->is_active = true;
            $user->email_verified_at ??= now();
            $user->save();
        }
    }
}
