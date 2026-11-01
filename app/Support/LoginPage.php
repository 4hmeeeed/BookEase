<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;

/** Which sign-in page a person belongs on: clients use /login, staff and the owner use /staff/login. */
final class LoginPage
{
    public static function for(Request $request): string
    {
        return $request->is('staff', 'staff/*', 'admin', 'admin/*')
            ? route('staff.login')
            : route('login');
    }

    public static function forUser(?User $user): string
    {
        return $user?->hasRole(UserRole::Staff, UserRole::Admin)
            ? route('staff.login')
            : route('login');
    }
}
