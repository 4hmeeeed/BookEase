<section class="grid gap-6 py-10 first:pt-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-16" aria-labelledby="profile-details-title">
    <header>
        <h2 id="profile-details-title" class="font-serif text-2xl leading-tight text-lagoon-950">Name &amp; email</h2>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-600">
            {{ $user->isGuest() ? 'We send booking updates and receipts to this email address.' : 'You sign in with this email address.' }}
        </p>
    </header>

    {{-- `hidden` keeps this out of the grid; the button below still submits it via form="send-verification". --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="hidden">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="card max-w-2xl space-y-5 p-6 sm:p-8">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" name="name" type="text" class="mt-1.5 block w-full" :value="old('name', $user->name)" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1.5 block w-full" :value="old('email', $user->email)" required autocomplete="username" spellcheck="false" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-md bg-sand-50 px-3.5 py-3 text-sm text-sand-900 ring-1 ring-inset ring-sand-200">
                    This email isn’t verified yet.
                    <button form="send-verification" class="link-underline font-semibold">Send the link again</button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-lagoon-800" role="status">A new verification link is on its way.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-1">
            <x-primary-button>Save changes</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition.opacity.duration.300ms x-init="setTimeout(() => show = false, 2500)"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-lagoon-700" role="status">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
                    Saved
                </p>
            @endif
        </div>
    </form>
</section>
