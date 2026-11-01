<x-guest-layout>
    <div class="mb-6 flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-lagoon-900 text-cream shadow-lagoon-glow" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2.5" /><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2" /></svg>
        </span>
        <div>
            <p class="eyebrow">Staff sign-in</p>
            <h1 class="mt-1 font-display text-2xl font-semibold text-lagoon-950">Front desk &amp; owner</h1>
            <p class="mt-1 text-sm text-gray-600">For Labnasan Resort staff only. Guest accounts can’t sign in here.</p>
        </div>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('staff.login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Work email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-lagoon-600 shadow-sm focus:ring-lagoon-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Keep me signed in on this device</span>
            </label>
        </div>

        <div class="mt-5 flex items-center justify-between gap-3">
            <p class="text-xs leading-relaxed text-gray-500">
                Forgot your password? Staff: ask the owner to reset it.
                Owner: <a href="{{ route('password.request') }}" class="underline hover:text-gray-800">reset by email</a>.
            </p>
            <x-primary-button class="shrink-0 whitespace-nowrap">Sign in</x-primary-button>
        </div>
    </form>

    <p class="mt-6 border-t border-sand-200 pt-5 text-center text-sm text-gray-600">
        Booking a stay?
        <a href="{{ route('login') }}" class="link-underline font-semibold text-lagoon-800">Guest sign-in</a>
    </p>
</x-guest-layout>
