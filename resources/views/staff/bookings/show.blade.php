@use('App\Enums\BookingStatus')

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('staff.bookings.index') }}" class="link-underline text-sm">&larr; Booking queue</a>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Booking</p>
                <h1 class="mt-2 font-mono text-3xl font-semibold tracking-wider text-lagoon-950">{{ $booking->reference_code }}</h1>
            </div>
            <x-booking-status :status="$booking->status" class="!px-4 !py-1.5 !text-sm" />
        </div>
    </x-slot>

    @php
        $status = $booking->status;
        $canApprove = $status->canTransitionTo(BookingStatus::Confirmed);
        $canReject = $status->canTransitionTo(BookingStatus::Rejected);
        $canCancel = $status->canTransitionTo(BookingStatus::Cancelled);
        $canCheckOut = $status->canTransitionTo(BookingStatus::CheckedOut);
        $checkInAllowed = $booking->canCheckInToday();
    @endphp

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_24rem] lg:px-8" x-data>
        <div class="space-y-6">
            {{-- Actions --}}
            @unless ($status->isFinal())
                <section class="card p-6">
                    <h2 class="font-display text-xl font-semibold text-lagoon-950">Next step</h2>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />

                    <div class="mt-4 flex flex-wrap gap-3">
                        @if ($canApprove)
                            <form method="POST" action="{{ route('staff.bookings.approve', $booking) }}">
                                @csrf
                                <button class="btn-primary">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
                                    Approve
                                </button>
                            </form>
                        @endif

                        @if ($canReject)
                            <button type="button" class="btn-ghost text-coral-700" @click="$dispatch('open-modal', 'reject')">Reject…</button>
                        @endif

                        @if ($status === BookingStatus::Confirmed)
                            <form method="POST" action="{{ route('staff.bookings.check-in', $booking) }}">
                                @csrf
                                <button class="btn-primary" @disabled(! $checkInAllowed)>Check in guest</button>
                            </form>
                        @endif

                        @if ($canCheckOut)
                            <form method="POST" action="{{ route('staff.bookings.check-out', $booking) }}">
                                @csrf
                                <button class="btn-coral">Check out guest</button>
                            </form>
                        @endif

                        @if ($canCancel)
                            <button type="button" class="btn-ghost" @click="$dispatch('open-modal', 'cancel')">Cancel booking…</button>
                        @endif
                    </div>

                    @if ($status === BookingStatus::Confirmed && ! $checkInAllowed)
                        <p class="mt-3 text-sm text-gray-500">
                            Check-in opens on {{ $booking->check_in->format('l, M j') }}.
                        </p>
                    @endif
                    @if ($status === BookingStatus::Pending)
                        <p class="mt-3 text-sm text-gray-500">Approving keeps these dates held for the guest. Rejecting releases them right away.</p>
                    @endif
                </section>
            @endunless

            {{-- Guest --}}
            <section class="card p-6">
                <h2 class="font-display text-xl font-semibold text-lagoon-950">Guest</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Name</dt><dd class="font-semibold text-gray-800">{{ $booking->user->name }}</dd></div>
                    <div><dt class="text-gray-500">Mobile</dt><dd class="font-semibold text-gray-800">
                        @if ($booking->user->phone)<a href="tel:{{ $booking->user->phone }}" class="link-underline">{{ $booking->user->phone }}</a>@else — @endif
                    </dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd class="font-semibold text-gray-800"><a href="mailto:{{ $booking->user->email }}" class="link-underline">{{ $booking->user->email }}</a></dd></div>
                    <div><dt class="text-gray-500">Party size</dt><dd class="font-semibold text-gray-800">{{ $booking->guests_count }} of {{ $booking->cottage->capacity }} max</dd></div>
                    <div><dt class="text-gray-500">Payment</dt><dd class="font-semibold text-gray-800">{{ $booking->payment_status->label() }} · {{ \App\Support\Money::format($booking->amount_paid) }} paid</dd></div>
                    <div><dt class="text-gray-500">Requested</dt><dd class="font-semibold text-gray-800">{{ $booking->created_at->format('M j, Y · g:i A') }}</dd></div>
                    @if ($booking->notes)
                        <div class="sm:col-span-2"><dt class="text-gray-500">Special requests</dt><dd class="whitespace-pre-line rounded-xl bg-sand-50 p-3 text-gray-800">{{ $booking->notes }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="card p-6">
                <h2 class="mb-5 font-display text-xl font-semibold text-lagoon-950">Audit trail</h2>
                <x-status-timeline :changes="$booking->statusChanges" show-actor />
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <x-stay-summary :cottage="$booking->cottage" :check-in="$booking->check_in" :check-out="$booking->check_out"
                            :nights="$booking->nights()" :total="$booking->total_price" :guests="$booking->guests_count" />
        </aside>

        @foreach (['reject' => ['Reject', 'staff.bookings.reject', 'The dates are released right away. The guest will see your reason.', 'Reject request'],
                   'cancel' => ['Cancel', 'staff.bookings.cancel', 'The dates are released right away. The guest will see your reason.', 'Cancel booking']] as $name => [$verb, $route, $help, $cta])
            <x-modal :name="$name" maxWidth="md" focusable>
                <form method="POST" action="{{ route($route, $booking) }}" class="p-6">
                    @csrf
                    <h2 class="font-display text-2xl font-semibold text-lagoon-950">{{ $verb }} {{ $booking->reference_code }}?</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ $help }}</p>
                    <label for="{{ $name }}-remarks" class="mt-4 block text-sm font-semibold text-lagoon-900">Reason for the guest</label>
                    <textarea id="{{ $name }}-remarks" name="remarks" rows="3" maxlength="500" required class="field mt-1.5"></textarea>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">Go back</x-secondary-button>
                        <x-danger-button>{{ $cta }}</x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    </div>
</x-app-layout>
