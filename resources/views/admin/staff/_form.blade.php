@php $editing = $member->exists; @endphp

<div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
    <div class="card space-y-6 p-6 sm:p-8">
        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" name="name" class="mt-1.5" :value="old('name', $member->name)" required maxlength="255" autofocus autocomplete="off" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="email" value="Email (used to sign in)" />
                <x-text-input id="email" name="email" type="email" class="mt-1.5" :value="old('email', $member->email)" required maxlength="255" autocomplete="off" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone" value="Mobile number (optional)" />
                <x-text-input id="phone" name="phone" inputmode="tel" class="mt-1.5" :value="old('phone', $member->phone)" placeholder="0917 123 4567" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
        </div>

        <fieldset class="rounded-2xl border border-sand-200 bg-sand-50/60 p-5">
            <legend class="px-2 text-sm font-semibold text-lagoon-900">{{ $editing ? 'Reset password (optional)' : 'Starting password' }}</legend>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" :value="$editing ? 'New password' : 'Password'" />
                    <x-text-input id="password" name="password" type="password" class="mt-1.5" autocomplete="new-password" :required="! $editing" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm password" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1.5" autocomplete="new-password" :required="! $editing" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-600">
                At least 10 characters with letters and numbers.
                {{ $editing ? 'Leave blank to keep the current password. Changing it signs this person out on every device.' : 'Give it to them in person and ask them to change it from their profile.' }}
            </p>
        </fieldset>
    </div>

    <aside class="card h-fit space-y-3 p-6 text-sm text-gray-700">
        <h2 class="eyebrow">What staff can do</h2>
        <ul class="space-y-2">
            @foreach (['Approve or reject booking requests', 'Check guests in and out', 'Record cash payments and refunds', 'See all bookings and receipts'] as $item)
                <li class="flex gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-lagoon-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.5 4.5L19 7" /></svg>
                    {{ $item }}
                </li>
            @endforeach
        </ul>
        <p class="border-t border-sand-200 pt-3 text-gray-500">Staff can’t manage cottages, staff accounts, reports, or the chatbot log.</p>
    </aside>
</div>
