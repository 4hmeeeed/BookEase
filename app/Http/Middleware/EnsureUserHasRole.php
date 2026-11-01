<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\LoginPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:staff,admin')
 *
 * Rejects (403) any authenticated user whose role is not in the list, and
 * logs out deactivated accounts so a disabled staff member loses access on
 * their very next request.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(LoginPage::for($request));
        }

        if (! $user->is_active) {
            $loginPage = LoginPage::forUser($user);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($loginPage)
                ->withErrors(['email' => 'This account has been deactivated. Please contact the resort.']);
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($user->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
