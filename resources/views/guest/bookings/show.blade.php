<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('guest.bookings.index') }}" class="link-underline text-sm">&larr; My bookings</a>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Booking reference</p>
                <h1 class="mt-2 font-mono text-3xl font-semibold tracking-wider text-lagoon-950 sm:text-4xl">{{ $booking->reference_code }}</h1>
            </div>
            <x-booking-status :status="$booking->status" class="!px-4 !py-1.5 !text-sm" />
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_24rem] lg:px-8" x-data>
        <div class="space-y-6">
            {{-- What happens next --}}
            <div @class([
                'card p-6',
                'border-sand-300 bg-sand-50' => $booking->status->value === 'pending',
                'border-lagoon-200 bg-lagoon-50/60' => in_array($booking->status->value, ['confirmed', 'checked_in'], true),
                'border-coral-200 bg-coral-50/60' => in_array($booking->status->value, ['rejected', 'cancelled'], true),
            ])>
                <h2 class="font-display text-xl font-semibold text-lagoon-950">
                    @switch($booking->status->value)
                        @case('pending') Waiting for the resort to confirm @break
                        @case('confirmed') You're confirmed. See you soon! @break
                        @case('checked_in') Enjoy your stay! @break
                        @case('checked_out') Thanks for staying with us @break
                        @case('rejected') This request was declined @break
                        @default This booking was cancelled
                    @endswitch
                </h2>
                <p class="mt-1.5 text-gray-700">
                    @switch($booking->status->value)
                        @case('pending') Your dates are held while the front desk reviews your request. Online payment with GCash or Maya is coming soon. @break
                        @case('confirmed') Check-in opens at 2:00 PM on {{ $booking->check_in->format('l, F j') }}. Keep your reference code handy. @break
                        @case('checked_in') Check-out is by 12:00 NN on {{ $booking->check_out->format('l, F j') }}. @break
                        @case('checked_out') We hope to welcome you back to Labnasan soon. @break
                        @default The dates have been released. You can book again any time.
                    @endswitch
                </p>
            </div>

            <div class="card p-6">
                <h2 class="font-display text-xl font-semibold text-lagoon-950">Details</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Guests</dt><dd class="font-semibold text-gray-800">{{ $booking->guests_count }}</dd></div>
                    <div><dt class="text-gray-500">Payment</dt><dd class="font-semibold text-gray-800">{{ $booking->payment_status->label() }}</dd></div>
                    <div><dt class="text-gray-500">Booked on</dt><dd class="font-semibold text-gray-800">{{ $booking->created_at->format('M j, Y · g:i A') }}</dd></div>
                    @if ($booking->notes)
                        <div class="sm:col-span-2"><dt class="text-gray-500">Special requests</dt><dd class="whitespace-pre-line text-gray-800">{{ $booking->notes }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card p-6">
                <h2 class="mb-5 font-display text-xl font-semibold text-lagoon-950">History</h2>
                <x-status-timeline :changes="$booking->statusChanges" />
            </div>

            @can('cancel', $booking)
                <div class="flex justify-end">
                    <button type="button" class="btn-ghost text-coral-700" @click="$dispatch('open-modal', 'cancel-booking')">Cancel this booking</button>
                </div>

                <x-modal name="cancel-booking" maxWidth="md" focusable>
                    <form method="POST" action="{{ route('guest.bookings.cancel', $booking) }}" class="p-6">
                        @csrf
                        <h2 class="font-display text-2xl font-semibold text-lagoon-950">Cancel {{ $booking->reference_code }}?</h2>
                        <p class="mt-2 text-sm text-gray-600">Your dates will be released right away and other guests will be able to book them.</p>
                        <label for="remarks" class="mt-4 block text-sm font-semibold text-lagoon-900">Reason (optional)</label>
                        <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="field mt-1.5" placeholder="Change of plans…"></textarea>
                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button x-on:click="$dispatch('close')">Keep booking</x-secondary-button>
                            <x-danger-button>Yes, cancel it</x-danger-button>
                        </div>
                    </form>
                </x-modal>
            @endcan
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <x-stay-summary :cottage="$booking->cottage" :check-in="$booking->check_in" :check-out="$booking->check_out"
                            :nights="$booking->nights()" :total="$booking->total_price" :guests="$booking->guests_count" />
        </aside>
    </div>
</x-app-layout>
