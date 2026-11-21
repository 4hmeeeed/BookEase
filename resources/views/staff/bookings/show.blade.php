@use('App\Enums\BookingStatus')

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('staff.bookings.index') }}" class="link-underline text-sm">&larr; Booking queue</a>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Booking</p>
                <h1 class="ref-code mt-2 text-3xl text-lagoon-950">{{ $booking->reference_code }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-no-show :booking="$booking" class="!px-4 !py-1.5 !text-sm" />
                <x-booking-status :status="$booking->status" class="!px-4 !py-1.5 !text-sm" />
            </div>
        </div>
    </x-slot>

    @php
        $status = $booking->status;
        $canApprove = $status->canTransitionTo(BookingStatus::Confirmed);
        $canReject = $status->canTransitionTo(BookingStatus::Rejected);
        $canCancel = $status->canTransitionTo(BookingStatus::Cancelled);
        $canCheckOut = $status->canTransitionTo(BookingStatus::CheckedOut);
        $checkInAllowed = $booking->canCheckInToday();
        // Confirmed but the stay already ended without a check-in: cancel, don't check in.
        $isNoShow = $status === BookingStatus::Confirmed && $booking->check_out->lte(today());
        $canTakeCash = app(\App\Services\PaymentService::class)->isPayable($booking);
    @endphp

    <div @class(['mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_24rem] lg:px-8', 'max-lg:pb-28' => ! $status->isFinal()]) x-data>
        <div class="space-y-6">
            {{-- Actions --}}
            @unless ($status->isFinal())
                <section class="card p-6">
                    <h2 class="font-serif text-xl font-semibold text-lagoon-950">Next step</h2>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />

                    {{-- On phones the buttons ride in a bar just above the tab bar, within thumb reach. --}}
                    <div data-action-bar
                         class="mt-4 flex flex-wrap gap-3 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-[calc(3.5rem+env(safe-area-inset-bottom))] max-lg:z-30 max-lg:mt-0 max-lg:flex-nowrap max-lg:overflow-x-auto max-lg:border-t max-lg:border-lagoon-950/10 max-lg:bg-cream/95 max-lg:px-4 max-lg:py-3 max-lg:backdrop-blur-md max-lg:[&>*]:shrink-0">
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

                        @if ($status === BookingStatus::Confirmed && ! $isNoShow)
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

                    @if ($isNoShow)
                        <p class="mt-3 text-sm text-coral-800">
                            This stay ended on {{ $booking->check_out->format('l, M j') }} without a check-in. Cancel the booking to close it.
                        </p>
                    @elseif ($status === BookingStatus::Confirmed && ! $checkInAllowed)
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
                <h2 class="font-serif text-xl font-semibold text-lagoon-950">Guest</h2>
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-xl font-semibold text-lagoon-950">Payments</h2>
                        <p class="text-sm text-gray-600">
                            Paid <strong class="text-lagoon-900">{{ \App\Support\Money::format($booking->amount_paid) }}</strong>
                            of {{ \App\Support\Money::format($booking->total_price) }}
                            · Balance <strong class="text-coral-700">{{ \App\Support\Money::format($booking->balance()) }}</strong>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($canTakeCash)
                            <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'cash')">Record cash payment</button>
                        @endif
                        @if (\App\Support\Money::isPositive($booking->amount_paid))
                            <button type="button" class="btn-ghost" @click="$dispatch('open-modal', 'refund')">Record refund</button>
                        @endif
                    </div>
                </div>
                <x-input-error :messages="$errors->get('amount')" class="mt-3" />
                <x-input-error :messages="$errors->get('refund_amount')" class="mt-3" />
                @include('staff.bookings._proofs')
                <div class="mt-4"><x-payments-list :payments="$booking->payments" show-recorder /></div>
            </section>

            <section class="card p-6">
                <h2 class="mb-5 font-serif text-xl font-semibold text-lagoon-950">Audit trail</h2>
                <x-status-timeline :changes="$booking->statusChanges" show-actor />
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <x-stay-summary :cottage="$booking->cottage" :check-in="$booking->check_in" :check-out="$booking->check_out"
                            :nights="$booking->nights()" :total="$booking->total_price" :guests="$booking->guests_count" />
        </aside>

        <x-modal name="cash" maxWidth="md" focusable>
            <form method="POST" action="{{ route('staff.payments.cash', $booking) }}" class="p-6">
                @csrf
                <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Record cash payment</h2>
                <p class="mt-2 text-sm text-gray-600">For walk-in or front-desk cash. Balance due: <strong>{{ \App\Support\Money::format($booking->balance()) }}</strong>.</p>
                <label for="cash-amount" class="mt-4 block text-sm font-semibold text-lagoon-900">Amount received</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-semibold text-gray-500">₱</span>
                    <input id="cash-amount" name="amount" inputmode="decimal" required class="field pl-8" value="{{ old('amount', $booking->balance()) }}">
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                    <x-primary-button>Record &amp; issue receipt</x-primary-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="refund" maxWidth="md" focusable>
            <form method="POST" action="{{ route('staff.payments.refund', $booking) }}" class="p-6">
                @csrf
                <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Record refund</h2>
                <p class="mt-2 text-sm text-gray-600">Record money already returned to the guest. Up to <strong>{{ \App\Support\Money::format($booking->amount_paid) }}</strong>.</p>
                <label for="refund-amount" class="mt-4 block text-sm font-semibold text-lagoon-900">Amount refunded</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-semibold text-gray-500">₱</span>
                    <input id="refund-amount" name="refund_amount" inputmode="decimal" required class="field pl-8" value="{{ old('refund_amount') }}">
                </div>
                <label for="refund-method" class="mt-4 block text-sm font-semibold text-lagoon-900">Sent via</label>
                <select id="refund-method" name="refund_method" class="field mt-1.5">
                    {{-- Cash first when guests pay at the front desk; e-wallets stay for refunds sent by transfer. --}}
                    @foreach (config('bookease.payments.online') ? ['gcash' => 'GCash', 'maya' => 'Maya', 'cash' => 'Cash'] : ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                    <x-danger-button>Record refund</x-danger-button>
                </div>
            </form>
        </x-modal>

        @foreach (['reject' => ['Reject', 'staff.bookings.reject', 'The dates are released right away. The guest will see your reason.', 'Reject request'],
                   'cancel' => ['Cancel', 'staff.bookings.cancel', 'The dates are released right away. The guest will see your reason.', 'Cancel booking']] as $name => [$verb, $route, $help, $cta])
            <x-modal :name="$name" maxWidth="md" focusable>
                <form method="POST" action="{{ route($route, $booking) }}" class="p-6">
                    @csrf
                    <h2 class="font-serif text-2xl font-semibold text-lagoon-950">{{ $verb }} {{ $booking->reference_code }}?</h2>
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
