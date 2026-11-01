<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('cottages.show', ['cottage' => $cottage, 'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString()]) }}" class="link-underline text-sm">&larr; Change dates</a>
        <div class="mt-3">
            <x-page-header eyebrow="Almost there" title="Confirm your reservation">
                Your dates are held as soon as you submit, while the resort reviews your request.
            </x-page-header>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_24rem] lg:px-8">
        <form method="POST" action="{{ route('guest.reservations.store', $cottage) }}" class="card space-y-6 p-6 sm:p-8"
              x-data="{ guests: {{ (int) old('guests_count', min(2, $cottage->capacity)) }}, max: {{ $cottage->capacity }}, submitting: false }"
              @submit="submitting = true">
            @csrf
            <input type="hidden" name="check_in" value="{{ $checkIn->toDateString() }}">
            <input type="hidden" name="check_out" value="{{ $checkOut->toDateString() }}">

            <x-input-error :messages="$errors->get('check_in')" />

            <div>
                <span class="block text-sm font-semibold text-lagoon-900">Number of guests</span>
                <div class="mt-2 flex items-center gap-4">
                    <div class="inline-flex items-center rounded-full border border-sand-300 bg-white p-1 shadow-sm">
                        <button type="button" @click="guests = Math.max(1, guests - 1)" :disabled="guests <= 1" class="grid h-10 w-10 place-items-center rounded-full text-lg text-lagoon-800 transition hover:bg-sand-100 active:scale-90 disabled:opacity-30" aria-label="Fewer guests">&minus;</button>
                        <span class="w-12 text-center text-lg font-semibold tabular-nums text-lagoon-950" x-text="guests" aria-live="polite"></span>
                        <button type="button" @click="guests = Math.min(max, guests + 1)" :disabled="guests >= max" class="grid h-10 w-10 place-items-center rounded-full text-lg text-lagoon-800 transition hover:bg-sand-100 active:scale-90 disabled:opacity-30" aria-label="More guests">+</button>
                    </div>
                    <span class="text-sm text-gray-500">Up to {{ $cottage->capacity }} in this {{ Str::lower($cottage->type->label()) }}</span>
                </div>
                <input type="hidden" name="guests_count" :value="guests">
                <x-input-error :messages="$errors->get('guests_count')" class="mt-2" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label value="Booking name" />
                    <p class="mt-1.5 rounded-xl border border-sand-200 bg-sand-50 px-4 py-2.5 text-gray-700">{{ $user->name }}</p>
                </div>
                <div>
                    <x-input-label for="phone" value="Mobile number" />
                    <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1.5"
                                  :value="old('phone', $user->phone)" required placeholder="0917 123 4567" />
                    <p class="mt-1 text-xs text-gray-500">Only used by the resort to contact you about this stay.</p>
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="Special requests (optional)" />
                <textarea id="notes" name="notes" rows="3" maxlength="1000" class="field mt-1.5"
                          placeholder="Arrival time, extra mattress, celebrations…">{{ old('notes') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-sand-200 bg-sand-50 p-4">
                <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-0.5 rounded border-sand-300 text-lagoon-600 focus:ring-lagoon-500">
                <span class="text-sm text-gray-700">
                    I confirm these details are correct. I understand the booking stays <strong>pending</strong> until the resort confirms it.
                </span>
            </label>
            <x-input-error :messages="$errors->get('agree')" class="-mt-4" />

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-sand-200 pt-6">
                <a href="{{ route('cottages.show', $cottage) }}" class="btn-ghost">Cancel</a>
                <button class="btn-coral px-7 py-3 text-base" :disabled="submitting">
                    <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20" /></svg>
                    <span x-text="submitting ? 'Reserving…' : 'Reserve now'">Reserve now</span>
                </button>
            </div>
        </form>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <x-stay-summary :cottage="$cottage" :check-in="$checkIn" :check-out="$checkOut" :nights="$nights" :total="$total" />
        </aside>
    </div>
</x-app-layout>
