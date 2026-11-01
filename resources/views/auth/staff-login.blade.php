<x-guest-layout>
    <div class="mb-8">
        <div class="flex items-center gap-2 text-lagoon-800">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="1.5" /><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2" /></svg>
            <span class="text-sm font-semibold">Staff sign-in</span>
        </div>
        <h1 class="mt-3 font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Front desk &amp; owner</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">For Labnasan Resort staff only. Guest accounts can’t sign in here.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('staff.login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Work email" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-5">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5 block">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-lagoon-950/25 text-lagoon-800 shadow-sm focus:ring-lagoon-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Keep me signed in on this device</span>
            </label>
        </div>

        <x-primary-button class="mt-7 w-full whitespace-nowrap py-3">Sign in</x-primary-button>

        <p class="mt-4 text-xs leading-relaxed text-gray-500">
            Forgot your password? Staff: ask the owner to reset it.
            Owner: <a href="{{ route('password.request') }}" class="underline hover:text-gray-800">reset by email</a>.
        </p>
    </form>

    <p class="mt-8 border-t border-lagoon-950/10 pt-6 text-sm text-gray-600">
        Booking a stay?
        <a href="{{ route('login') }}" class="link-underline font-semibold text-lagoon-900">Guest sign-in <span class="arrow">&rarr;</span></a>
    </p>
</x-guest-layout>
