{{-- Guests and the owner both land here; ?from=staff (set by the staff sign-in page) sends the owner back there. --}}
@php($backTo = request('from') === 'staff' ? route('staff.login') : route('login'))

<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Reset your password</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">Enter the email you signed up with and we’ll send you a link to choose a new password.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="mt-7 w-full py-3 short:mt-5">Email me a reset link</x-primary-button>
    </form>

    <x-slot name="footer">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-lagoon-950/10 pt-6 text-sm text-gray-600 short:mt-5 short:pt-4">
            <a href="{{ route('home') }}" class="link-underline">&larr; Back to the resort</a>
            <p>
                Remembered it?
                <a href="{{ $backTo }}" class="link-underline font-semibold text-lagoon-900">Log in <span class="arrow">&rarr;</span></a>
            </p>
        </div>
    </x-slot>
</x-guest-layout>
