<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Confirm your password</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">For your security, enter your password again to continue.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="mt-7 w-full py-3 short:mt-5">Confirm and continue</x-primary-button>
    </form>
</x-guest-layout>
