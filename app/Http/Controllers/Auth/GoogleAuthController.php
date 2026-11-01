<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleSignInRefused;
use App\Http\Controllers\Controller;
use App\Services\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** "Continue with Google" on the guest sign-in and registration pages. */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, GoogleSignIn $signIn): RedirectResponse
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        // The visitor pressed "Cancel" on Google's screen.
        if ($request->filled('error')) {
            return $this->backToLogin('Google sign-in was cancelled.');
        }

        try {
            // Socialite checks the OAuth "state" value, so a forged callback fails here.
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['exception' => $e::class]);

            return $this->backToLogin('We couldn’t sign you in with Google. Please try again.');
        }

        try {
            $user = $signIn->resolveUser($googleUser);
        } catch (GoogleSignInRefused $e) {
            return $this->backToLogin($e->getMessage());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
