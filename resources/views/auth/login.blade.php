<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Welcome back</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">Guest sign-in. See your bookings{{ config('bookease.payments.online') ? ', pay with GCash or Maya,' : '' }} and download receipts.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <x-google-sign-in />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-5 short:mt-4">
            <div class="flex items-baseline justify-between gap-3">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="link-underline text-xs" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                @endif
            </div>

            <x-text-input id="password" class="mt-1.5 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5 block short:mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-lagoon-950/25 text-lagoon-800 shadow-sm focus:ring-lagoon-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <x-primary-button class="mt-7 w-full py-3 short:mt-5">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    <x-slot name="footer">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-lagoon-950/10 pt-6 text-sm text-gray-600 short:mt-5 short:pt-4">
            <a href="{{ route('home') }}" class="link-underline">&larr; Back to the resort</a>
            @if (Route::has('register'))
                <p>
                    New to Labnasan?
                    <a href="{{ route('register') }}" class="link-underline font-semibold text-lagoon-900">Create an account <span class="arrow">&rarr;</span></a>
                </p>
            @endif
        </div>
    </x-slot>
</x-guest-layout>
