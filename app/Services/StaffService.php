<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
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
    public function __construct(private readonly UserSessions $sessions) {}

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
            $this->sessions->endAll($staff); // whoever knew the old password is locked out
        }

        return $staff;
    }

    public function setActive(User $staff, bool $active): User
    {
        $this->ensureStaff($staff);

        $staff->is_active = $active;
        $staff->save();

        if (! $active) {
            $this->sessions->endAll($staff);
        }

        return $staff;
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->isStaff()) {
            throw new InvalidArgumentException('Only staff accounts are managed here.');
        }
    }
}
