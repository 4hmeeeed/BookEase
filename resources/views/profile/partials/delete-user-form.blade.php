@php
    // Ask the same rules the controller enforces, so a guest learns *before* typing a password
    // whether deletion is possible (class_exists guards a checkout without that service).
    $refusal = class_exists(\App\Services\AccountDeletion::class)
        ? app(\App\Services\AccountDeletion::class)->refusal(auth()->user())
        : null;
@endphp

<section class="grid gap-6 py-10 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-16" aria-labelledby="delete-account-title">
    <header>
        <h2 id="delete-account-title" class="font-serif text-2xl leading-tight text-lagoon-950">Delete account</h2>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-600">Permanently removes your account and sign-in. This can’t be undone.</p>
    </header>

    <div class="card max-w-2xl p-6 sm:p-8">
        @if ($refusal)
            <div class="flex items-start gap-3 text-sm leading-relaxed text-gray-700">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-sand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 8v5M12 16.5h.01" /></svg>
                <p>{{ $refusal }}</p>
            </div>
        @else
            <p class="text-sm leading-relaxed text-gray-600">
                Your account can be deleted: you have no upcoming stays and no payment records the resort needs to keep.
            </p>
            <x-danger-button class="mt-5" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                Delete my account
            </x-danger-button>
        @endif
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" maxWidth="lg" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="font-serif text-2xl leading-tight text-lagoon-950">Delete your account?</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">This permanently removes your account and can’t be undone. Enter your password to confirm.</p>

            <div class="mt-6">
                <x-input-label for="password" value="Password" class="sr-only" />
                <x-text-input id="password" name="password" type="password" class="block w-full" placeholder="Your password" autocomplete="current-password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">Keep my account</x-secondary-button>
                <x-danger-button>Delete my account</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
