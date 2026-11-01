<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authenticate, but only if the account belongs on this sign-in page.
     *
     * Clients sign in at /login and staff/owner at /staff/login. The role is
     * checked only after the password is verified, so the "wrong page" hint
     * never tells a stranger which email addresses belong to staff.
     *
     * @param  list<UserRole>  $roles  roles allowed through this page
     * @param  string  $wrongPageMessage  shown to a valid account of another role
     *
     * @throws ValidationException
     */
    public function authenticate(array $roles, string $wrongPageMessage): void
    {
        $this->ensureIsNotRateLimited();

        // Deactivated accounts (e.g. former staff) cannot log in.
        $credentials = [...$this->only('email', 'password'), 'is_active' => true];

        $guard = Auth::guard('web');
        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            event(new Failed('web', $user, $credentials));
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! $user->hasRole(...$roles)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $wrongPageMessage,
            ]);
        }

        $provider->rehashPasswordIfRequired($user, $credentials);
        $guard->login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
