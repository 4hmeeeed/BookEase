{{-- GCash/Maya receipts the guest sent for this booking. Pending ones get Verify / Reject. --}}
@use('App\Support\Money')

@if ($booking->paymentProofs->isNotEmpty())
    <div class="mt-5 rounded-xl border border-sand-200 bg-sand-50/60 p-4" aria-labelledby="proofs-heading">
        <h3 id="proofs-heading" class="text-sm font-semibold text-lagoon-900">GCash / Maya receipts from the guest</h3>
        <x-input-error :messages="$errors->get('proof')" class="mt-2" role="alert" />
        <x-input-error :messages="$errors->get('verified_amount')" class="mt-2" />
        <x-input-error :messages="$errors->get('review_note')" class="mt-2" />

        <ul class="mt-3 space-y-3">
            @foreach ($booking->paymentProofs as $proof)
                <li class="flex flex-col gap-3 rounded-lg border border-lagoon-950/10 bg-white p-3 sm:flex-row sm:items-start">
                    {{-- Opens the viewer below; without JavaScript the link opens the image itself. --}}
                    <a href="{{ route('staff.proofs.screenshot', [$booking, $proof]) }}" target="_blank" rel="noopener"
                       @click.prevent="$dispatch('open-modal', 'proof-image-{{ $proof->id }}')"
                       class="group relative block shrink-0 overflow-hidden rounded-md border border-sand-200 bg-sand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400">
                        <img src="{{ route('staff.proofs.screenshot', [$booking, $proof]) }}" alt="Receipt screenshot, reference {{ $proof->reference_no }}"
                             class="h-44 w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03] sm:h-28 sm:w-20" loading="lazy">
                        <span class="absolute inset-x-0 bottom-0 bg-lagoon-950/75 py-1 text-center text-[0.7rem] font-semibold text-cream">View screenshot</span>
                    </a>

                    <div class="min-w-0 flex-1 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-gray-800">{{ $proof->method->label() }} · <span class="tabular-nums">{{ Money::format($proof->amount) }}</span> <span class="font-normal text-gray-500">({{ Str::lower($proof->kind->label()) }})</span></p>
                            <span class="badge {{ $proof->status->badgeClass() }}">{{ $proof->isPending() ? 'To verify' : $proof->status->label() }}</span>
                        </div>
                        <p class="mt-1 text-gray-600">Ref <span class="font-mono tracking-wider text-gray-800">{{ $proof->reference_no }}</span> · sent {{ $proof->created_at->format('M j, g:i A') }}</p>

                        @if ($proof->isPending())
                            <p class="mt-1 text-gray-600">Open your {{ $proof->method->label() }} app and find a transfer with this reference number and amount before verifying.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'verify-proof-{{ $proof->id }}')">Verify</button>
                                <button type="button" class="btn-ghost text-coral-700" @click="$dispatch('open-modal', 'reject-proof-{{ $proof->id }}')">Not received</button>
                            </div>
                        @else
                            <p class="mt-1 text-gray-500">
                                {{ $proof->status->label() }} by {{ $proof->reviewer?->name ?? 'staff' }} · {{ $proof->reviewed_at?->format('M j, g:i A') }}
                                @if ($proof->review_note) · “{{ $proof->review_note }}” @endif
                            </p>
                        @endif
                    </div>
                </li>

            @endforeach
        </ul>

        {{-- Screenshot viewer: big enough to read the reference number, on phones too. --}}
        @foreach ($booking->paymentProofs as $proof)
            <x-modal name="proof-image-{{ $proof->id }}" maxWidth="lg">
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold text-lagoon-950">{{ $proof->method->label() }} · <span class="tabular-nums">{{ Money::format($proof->amount) }}</span></p>
                            <p class="mt-0.5 text-gray-600">Ref <span class="font-mono tracking-wider text-gray-800">{{ $proof->reference_no }}</span></p>
                        </div>
                        <button type="button" @click="$dispatch('close')" class="grid h-9 w-9 shrink-0 place-items-center rounded-md text-gray-500 transition-[background-color,color] duration-150 hover:bg-sand-100 hover:text-lagoon-950">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                            <span class="sr-only">Close</span>
                        </button>
                    </div>

                    <div class="mt-3 overflow-auto rounded-lg border border-sand-200 bg-sand-50" style="max-height: 70vh">
                        <img src="{{ route('staff.proofs.screenshot', [$booking, $proof]) }}" alt="Receipt screenshot, reference {{ $proof->reference_no }}" class="mx-auto w-full object-contain" loading="lazy">
                    </div>

                    <a href="{{ route('staff.proofs.screenshot', [$booking, $proof]) }}" target="_blank" rel="noopener"
                       class="mt-3 inline-block text-sm font-semibold text-lagoon-900 link-underline">Open full size</a>
                </div>
            </x-modal>
        @endforeach

        @foreach ($booking->paymentProofs as $proof)
                @if ($proof->isPending())
                    <x-modal name="verify-proof-{{ $proof->id }}" maxWidth="md" focusable>
                        <form method="POST" action="{{ route('staff.proofs.verify', [$booking, $proof]) }}" class="p-6">
                            @csrf
                            <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Verify {{ $proof->method->label() }} payment</h2>
                            <p class="mt-2 text-sm text-gray-600">Only verify once the transfer <strong>Ref {{ $proof->reference_no }}</strong> shows in the resort’s {{ $proof->method->label() }} app. The guest is emailed a receipt.</p>
                            <label for="verified-amount-{{ $proof->id }}" class="mt-4 block text-sm font-semibold text-lagoon-900">Amount actually received</label>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500">₱</span>
                                <input id="verified-amount-{{ $proof->id }}" name="verified_amount" inputmode="decimal" required class="field pl-8" value="{{ $proof->amount }}">
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">Balance due: {{ Money::format($booking->balance()) }}.</p>
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" class="btn-ghost" x-on:click="$dispatch('close')">Back</button>
                                <x-primary-button>Verify and send receipt</x-primary-button>
                            </div>
                        </form>
                    </x-modal>

                    <x-modal name="reject-proof-{{ $proof->id }}" maxWidth="md" focusable>
                        <form method="POST" action="{{ route('staff.proofs.reject', [$booking, $proof]) }}" class="p-6">
                            @csrf
                            <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Payment not received?</h2>
                            <p class="mt-2 text-sm text-gray-600">The guest is emailed this reason and can send a corrected receipt.</p>
                            <label for="review-note-{{ $proof->id }}" class="mt-4 block text-sm font-semibold text-lagoon-900">Reason</label>
                            <textarea id="review-note-{{ $proof->id }}" name="review_note" rows="3" required minlength="5" maxlength="500" class="field mt-1.5">No transfer with reference {{ $proof->reference_no }} reached the resort’s {{ $proof->method->label() }} account.</textarea>
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" class="btn-ghost" x-on:click="$dispatch('close')">Back</button>
                                <x-danger-button>Mark as not received</x-danger-button>
                            </div>
                        </form>
                    </x-modal>
                @endif
        @endforeach
    </div>
@endif
