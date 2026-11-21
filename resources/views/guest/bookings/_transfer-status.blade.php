{{-- The guest's GCash/Maya receipts and what the front desk decided. --}}
@use('App\Support\Money')

@if ($proofs->isNotEmpty())
    <section class="card p-6" aria-labelledby="transfers-heading">
        <h2 id="transfers-heading" class="font-serif text-xl font-semibold text-lagoon-950">Your GCash / Maya receipts</h2>
        <ul class="mt-4 divide-y divide-sand-200">
            @foreach ($proofs as $proof)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-gray-800">
                            {{ $proof->method->label() }} · <span class="tabular-nums">{{ Money::format($proof->amount) }}</span>
                        </p>
                        <p class="mt-0.5 text-gray-600">
                            Ref <span class="font-mono tracking-wider">{{ $proof->reference_no }}</span> · sent {{ $proof->created_at->format('M j, g:i A') }}
                            · <a href="{{ route('guest.proofs.screenshot', [$booking, $proof]) }}" class="link-underline text-lagoon-900" target="_blank" rel="noopener">screenshot</a>
                        </p>
                        @if ($proof->isPending())
                            <p class="mt-1 text-gray-600">The front desk is checking this transfer. You’ll get an email when it’s confirmed.</p>
                        @elseif ($proof->review_note)
                            <p class="mt-1 text-coral-700">Reason: {{ $proof->review_note }}</p>
                        @endif
                    </div>
                    <span class="badge {{ $proof->status->badgeClass() }}">{{ $proof->status->label() }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
