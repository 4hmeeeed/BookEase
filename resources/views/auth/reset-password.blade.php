<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Choose a new password</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">Use at least 8 characters. You’ll sign in with it from now on.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email', $request->email)" required autocomplete="username" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-5 short:mt-4">
            <x-input-label for="password" value="New password" />
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5 short:mt-4">
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-text-input id="password_confirmation" class="mt-1.5 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="mt-7 w-full py-3 short:mt-5">Save new password</x-primary-button>
    </form>
</x-guest-layout>
