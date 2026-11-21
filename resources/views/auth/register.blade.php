<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Create your account</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">{{ config('bookease.payments.online') ? 'Book a cottage and pay with GCash or Maya.' : 'Book a cottage online and pay at the front desk.' }}</p>
    </div>

    <x-google-sign-in label="Sign up with Google" />

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" class="mt-1.5 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-5 short:mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Side by side from sm up, so the whole form fits one screen. --}}
        <div class="mt-5 grid gap-5 sm:grid-cols-2 sm:gap-4 short:mt-4">
            <div>
                <div class="flex items-baseline justify-between gap-2">
                    <x-input-label for="password" :value="__('Password')" />
                    <span id="password-hint" class="text-xs text-gray-500">8+ characters</span>
                </div>
                <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="new-password" aria-describedby="password-hint" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" class="mt-1.5 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 short:mt-4">
            <label for="privacy_consent" class="flex items-start gap-3 text-sm leading-relaxed text-gray-600">
                <input id="privacy_consent" type="checkbox" name="privacy_consent" value="1" required @checked(old('privacy_consent'))
                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-lagoon-950/25 text-lagoon-700 focus:ring-lagoon-500/25">
                <span>I have read the <a href="{{ route('privacy') }}" class="link-underline font-semibold text-lagoon-900" target="_blank" rel="noopener">privacy notice</a> and agree to Labnasan Resort using my details for my bookings.</span>
            </label>
            <x-input-error :messages="$errors->get('privacy_consent')" class="mt-2" />
        </div>

        <x-primary-button class="mt-7 w-full py-3 short:mt-5">Create account</x-primary-button>
    </form>

    <x-slot name="footer">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-lagoon-950/10 pt-6 text-sm text-gray-600 short:mt-5 short:pt-4">
            <a href="{{ route('home') }}" class="link-underline">&larr; Back to the resort</a>
            <p>
                Have an account?
                <a href="{{ route('login') }}" class="link-underline font-semibold text-lagoon-900">Log in <span class="arrow">&rarr;</span></a>
            </p>
        </div>
    </x-slot>
</x-guest-layout>
