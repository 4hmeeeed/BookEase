<section class="grid gap-6 py-10 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-16" aria-labelledby="password-title">
    <header>
        <h2 id="password-title" class="font-serif text-2xl leading-tight text-lagoon-950">Password</h2>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-600">Use at least 8 characters. A few unrelated words make a password that’s easy to remember and hard to guess.</p>
    </header>

    <div class="max-w-2xl space-y-4">
        @if (auth()->user()->google_id)
            <p class="rounded-md bg-lagoon-50 px-4 py-3 text-sm leading-relaxed text-lagoon-900 ring-1 ring-inset ring-lagoon-200">
                You sign in with Google. If you never set a BookEase password, log out and use
                “Forgot your password?” on the sign-in page to create one. You need it to change your password or delete your account here.
            </p>
        @endif

        <form method="post" action="{{ route('password.update') }}" class="card space-y-5 p-6 sm:p-8">
            @csrf
            @method('put')

            <div>
                <x-input-label for="update_password_current_password" value="Current password" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1.5 block w-full sm:max-w-sm" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2 sm:gap-4">
                <div>
                    <x-input-label for="update_password_password" value="New password" />
                    <x-text-input id="update_password_password" name="password" type="password" class="mt-1.5 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="update_password_password_confirmation" value="Confirm new password" />
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1.5 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <x-primary-button>Update password</x-primary-button>

                @if (session('status') === 'password-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition.opacity.duration.300ms x-init="setTimeout(() => show = false, 2500)"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-lagoon-700" role="status">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
                        Password updated
                    </p>
                @endif
            </div>
        </form>
    </div>
</section>
