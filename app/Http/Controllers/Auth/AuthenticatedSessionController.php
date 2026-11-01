<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Two sign-in pages share this controller:
 *  - /login        resort clients (guest role) only
 *  - /staff/login  front-desk staff and the owner only
 * Each page refuses accounts that belong on the other one.
 */
class AuthenticatedSessionController extends Controller
{
    /** Client sign-in page. */
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(
            [UserRole::Guest],
            'Resort staff and the owner sign in on the staff login page.',
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Staff and owner sign-in page. */
    public function createStaff(): View
    {
        return view('auth.staff-login');
    }

    public function storeStaff(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(
            [UserRole::Staff, UserRole::Admin],
            'This page is for resort staff. Guests sign in on the main login page.',
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $wasStaff = $request->user()?->hasRole(UserRole::Staff, UserRole::Admin) ?? false;

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $wasStaff ? redirect()->route('staff.login') : redirect('/');
    }
}
