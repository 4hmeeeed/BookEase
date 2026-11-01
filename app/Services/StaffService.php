<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Owner-only management of front-desk staff accounts.
 *
 * Staff are never deleted: their names stay on the payments and booking
 * decisions they recorded. Deactivating an account ends its sessions at once.
 */
class StaffService
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     */
    public function create(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        $user->role = UserRole::Staff;    // not mass-assignable on purpose
        $user->is_active = true;
        $user->email_verified_at = now(); // the owner vouches for the address
        $user->save();

        return $user;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, password?: string|null}  $data
     */
    public function update(User $staff, array $data): User
    {
        $this->ensureStaff($staff);

        $staff->fill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
        ]);

        $passwordChanged = filled($data['password'] ?? null);

        if ($passwordChanged) {
            $staff->password = $data['password'];
        }

        $staff->save();

        if ($passwordChanged) {
            $this->endSessions($staff); // whoever knew the old password is locked out
        }

        return $staff;
    }

    public function setActive(User $staff, bool $active): User
    {
        $this->ensureStaff($staff);

        $staff->is_active = $active;
        $staff->save();

        if (! $active) {
            $this->endSessions($staff);
        }

        return $staff;
    }

    /** Sign the user out everywhere: stored sessions and "remember me" cookies. */
    private function endSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->isStaff()) {
            throw new InvalidArgumentException('Only staff accounts are managed here.');
        }
    }
}
