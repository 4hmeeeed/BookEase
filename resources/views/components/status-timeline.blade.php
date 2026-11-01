@props(['changes', 'showActor' => false])

<ol {{ $attributes->merge(['class' => 'relative space-y-5 border-l-2 border-sand-200 pl-6']) }} x-reveal.stagger>
    @foreach ($changes as $change)
        <li class="relative">
            <span @class([
                'absolute -left-[1.95rem] top-1 grid h-4 w-4 place-items-center rounded-full ring-4 ring-cream',
                'bg-lagoon-600' => in_array($change->to_status->value, ['confirmed', 'checked_in', 'checked_out'], true),
                'bg-coral-500' => in_array($change->to_status->value, ['rejected', 'cancelled'], true),
                'bg-sand-400' => $change->to_status->value === 'pending',
            ])></span>
            <div class="flex flex-wrap items-center gap-2">
                <x-booking-status :status="$change->to_status" />
                <time class="text-xs text-gray-500" datetime="{{ $change->created_at->toIso8601String() }}">
                    {{ $change->created_at->format('M j, Y · g:i A') }}
                </time>
            </div>
            @if ($change->remarks)
                <p class="mt-1.5 text-sm text-gray-700">“{{ $change->remarks }}”</p>
            @endif
            @if ($showActor && $change->user)
                <p class="mt-1 text-xs text-gray-500">by {{ $change->user->name }} ({{ $change->user->role->label() }})</p>
            @endif
        </li>
    @endforeach
</ol>
