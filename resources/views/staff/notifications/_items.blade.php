{{-- One row per App\Support\ConsoleAlert. Shared by the bell's dropdown and the full list. --}}
@foreach ($alerts as $alert)
    <li data-alert>
        <a href="{{ route('staff.notifications.open', $alert->id) }}"
           class="flex items-start gap-3 px-4 py-3 transition-colors duration-200 hover:bg-sand-50 focus:outline-none focus-visible:bg-sand-50">
            <span @class([
                'mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-md',
                'bg-sand-100 text-sand-800' => $alert->tone === 'attention',
                'bg-lagoon-50 text-lagoon-700' => $alert->tone === 'money',
                'bg-coral-50 text-coral-700' => $alert->tone === 'warning',
                'bg-ocean-50 text-ocean-700' => $alert->tone === 'info',
            ])>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @switch($alert->kind)
                        @case('booking.requested')
                            <path d="M7 3.5v3M17 3.5v3M4.5 9h15M5.5 6h13a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1ZM12 12.5v4M10 14.5h4" />
                            @break
                        @case('booking.cancelled')
                            <path d="M7 3.5v3M17 3.5v3M4.5 9h15M5.5 6h13a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1ZM10.25 12.75l3.5 3.5M13.75 12.75l-3.5 3.5" />
                            @break
                        @case('payment.review')
                            <path d="M12 4 2.8 19.5h18.4Z" /><path d="M12 10v4.5M12 17h.01" />
                            @break
                        @case('chatbot.unanswered')
                            <path d="M4 5h16v11H9.5L4 20Z" /><path d="M10 9.25a2 2 0 1 1 2.6 1.9c-.4.15-.6.5-.6.9v.2M12 14.25h.01" />
                            @break
                        @default
                            <rect x="3" y="6.5" width="18" height="11" rx="1" /><circle cx="12" cy="12" r="2.5" /><path d="M6.5 9.5v.01M17.5 14.5v.01" />
                    @endswitch
                </svg>
            </span>

            <span class="min-w-0 flex-1">
                <span class="flex items-baseline justify-between gap-3">
                    <span @class(['truncate text-sm', 'font-semibold text-lagoon-950' => ! $alert->read, 'font-medium text-gray-700' => $alert->read])>{{ $alert->title }}</span>
                    <time class="shrink-0 text-xs tabular-nums text-gray-500" datetime="{{ $alert->at->toIso8601String() }}">{{ $alert->at->diffForHumans(short: true) }}</time>
                </span>
                <span class="mt-0.5 block text-sm text-gray-600">{{ $alert->body }}</span>
            </span>

            @unless ($alert->read)
                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-lagoon-600"><span class="sr-only">Unread</span></span>
            @endunless
        </a>
    </li>
@endforeach
