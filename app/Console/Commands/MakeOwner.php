<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the real owner account for production (or resets its password),
 * instead of the development accounts whose password is "password".
 */
class MakeOwner extends Command
{
    protected $signature = 'bookease:make-owner';

    protected $description = 'Create the resort owner account (or reset its password)';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Owner name')),
            'email' => Str::lower(trim((string) $this->ask('Owner email'))),
            'password' => (string) $this->secret('Password (at least 12 characters, letters and numbers)'),
            'password_confirmation' => (string) $this->secret('Repeat the password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();

        if ($existing && ! $existing->isAdmin()) {
            $this->error('That email belongs to a guest or staff account. Use a different email for the owner.');

            return self::FAILURE;
        }

        $owner = $existing ?? new User;
        $owner->fill(['name' => $data['name'], 'email' => $data['email']]);
        $owner->password = $data['password'];
        $owner->role = UserRole::Admin;     // not mass-assignable on purpose
        $owner->is_active = true;
        $owner->email_verified_at ??= now();
        $owner->save();

        $this->info(($existing ? 'Owner account updated: ' : 'Owner account created: ').$owner->email);

        return self::SUCCESS;
    }
}
