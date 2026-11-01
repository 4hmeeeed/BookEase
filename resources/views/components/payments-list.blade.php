@props(['payments', 'showRecorder' => false])
@use('App\Support\Money')

@if ($payments->isEmpty())
    <p class="text-sm text-gray-500">No payments yet.</p>
@else
    <ul class="divide-y divide-sand-200">
        @foreach ($payments as $payment)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-800">
                        {{ $payment->kind->label() }} · {{ $payment->method->label() }}
                    </p>
                    <p class="font-mono text-xs text-gray-500">
                        {{ $payment->reference_no }}
                        · {{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A') }}
                        @if ($showRecorder && $payment->recordedBy) · by {{ $payment->recordedBy->name }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span @class(['font-semibold tabular-nums', 'text-coral-700' => $payment->isRefund(), 'text-gray-900' => ! $payment->isRefund()])>
                        {{ $payment->isRefund() ? '−' : '' }}{{ Money::format($payment->amount) }}
                    </span>
                    <span class="badge {{ $payment->status->badgeClass() }}"><span class="badge-dot"></span>{{ $payment->status->label() }}</span>
                    @if ($payment->isPaid())
                        <a href="{{ route('receipts.show', $payment) }}" class="link-underline text-sm" target="_blank">Receipt</a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
