<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\GoogleSignInRefused;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Turns a Google account into a BookEase *guest* account.
 *
 * - Only Google-verified email addresses are accepted, so nobody can claim an
 *   existing account with an address they don't own.
 * - Staff and the owner never sign in with Google; they use /staff/login.
 * - Only the Google account id, name and email are kept.
 */
class GoogleSignIn
{
    public function __construct(private readonly UserSessions $sessions) {}

    /** Google sign-in shows up only once both OAuth credentials are configured. */
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * @throws GoogleSignInRefused
     */
    public function resolveUser(GoogleUser $google): User
    {
        $googleId = (string) $google->getId();
        $email = Str::lower(trim((string) $google->getEmail()));

        if ($googleId === '' || $email === '' || ! $this->emailIsVerified($google)) {
            throw new GoogleSignInRefused('Your Google account has no verified email address. Please sign in with your email and password instead.');
        }

        $user = User::where('google_id', $googleId)->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            return $this->createGuest($googleId, $email, $google->getName());
        }

        if (! $user->hasRole(UserRole::Guest)) {
            throw new GoogleSignInRefused('Resort staff and the owner sign in on the staff login page.');
        }

        if (! $user->is_active) {
            throw new GoogleSignInRefused('This account has been deactivated. Please contact the resort.');
        }

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            throw new GoogleSignInRefused('This email is linked to a different Google account. Please sign in with your email and password.');
        }

        // First Google sign-in for an existing guest: link it. Google has verified the address.
        $unproven = $user->email_verified_at === null;

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if ($unproven) {
            // Nobody had proven they own this address, so the current password
            // may belong to someone who registered it in another person's name.
            // Google has proven ownership now: retire that password and its sessions.
            // The guest keeps signing in with Google, or sets a new password with
            // "Forgot your password?".
            $user->password = Str::password(40);
        }

        $user->save();

        if ($unproven) {
            $this->sessions->endAll($user);
        }

        return $user;
    }

    private function emailIsVerified(GoogleUser $google): bool
    {
        $raw = method_exists($google, 'getRaw') ? $google->getRaw() : [];
        $verified = $raw['email_verified'] ?? false;

        return $verified === true || $verified === 'true';
    }

    private function createGuest(string $googleId, string $email, ?string $name): User
    {
        $user = new User([
            'name' => Str::limit(trim((string) $name) ?: Str::before($email, '@'), 255, ''),
            'email' => $email,
            // Unknown random password; the guest can set one later with "Forgot your password?".
            'password' => Str::password(40),
        ]);

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
