{{--
    Floating inquiry chatbot. Answers come from ChatbotService (live resort data)
    and are rendered with x-text only, never as HTML.
--}}
@php
    $suggestions = [
        'What cottages do you have?',
        'What time is check-in and check-out?',
        'Is anything free this weekend?',
        'How do I pay the down payment?',
    ];
@endphp

<div x-data="chatbot({ endpoint: @js(route('chatbot.ask')), maxLength: @js((int) config('bookease.chatbot.max_question_length', 500)) })"
     @keydown.escape.window="open && close()"
     @open-chatbot.window="show(); if ($event.detail?.question) ask($event.detail.question)"
     class="fixed bottom-4 right-4 z-40 print:hidden sm:bottom-6 sm:right-6">

    {{-- Panel --}}
    <section x-show="open" x-cloak
             x-transition:enter="transition duration-300 ease-spring motion-reduce:duration-0"
             x-transition:enter-start="translate-y-4 scale-90 opacity-0"
             x-transition:enter-end="translate-y-0 scale-100 opacity-100"
             x-transition:leave="transition duration-200 ease-out-soft motion-reduce:duration-0"
             x-transition:leave-start="translate-y-0 scale-100 opacity-100"
             x-transition:leave-end="translate-y-3 scale-95 opacity-0"
             id="chatbot-panel" role="dialog" aria-modal="false" aria-labelledby="chatbot-title"
             class="absolute bottom-[4.5rem] right-0 flex max-h-[min(36rem,calc(100dvh-7rem))] w-[min(24rem,calc(100vw-2rem))] origin-bottom-right flex-col overflow-hidden rounded-lg border border-lagoon-950/10 bg-cream shadow-plate">

        <header class="relative overflow-hidden bg-lagoon-950 px-5 pb-4 pt-5 text-cream">
            <div class="graticule pointer-events-none absolute inset-0 text-cream/[0.06]" aria-hidden="true"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div>
                    <h2 id="chatbot-title" class="font-serif text-2xl">Ask Labnasan</h2>
                    <p class="mt-0.5 text-xs text-lagoon-100/80">Answers from our current rates, calendar, and policies.</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" x-show="messages.length" @click="reset()"
                            class="rounded-md p-2 text-lagoon-100 transition hover:bg-lagoon-900 hover:text-cream active:scale-90"
                            title="Start over" aria-label="Clear conversation">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 12.4 2.1M18.5 9A7 7 0 0 0 6.1 6.9" /></svg>
                    </button>
                    <button type="button" @click="close()"
                            class="rounded-md p-2 text-lagoon-100 transition hover:bg-lagoon-900 hover:text-cream active:scale-90"
                            aria-label="Close chat">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>
        </header>

        {{-- Conversation --}}
        <div x-ref="log" role="log" aria-live="polite" aria-relevant="additions"
             class="flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4">

            <div class="flex animate-fade-up gap-2.5 [animation-duration:.45s]">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-lagoon-100 text-lagoon-800" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15c2 0 2-1.5 4.5-1.5S10 15 12 15s2.5-1.5 4.5-1.5S19 15 21 15M3 19c2 0 2-1.5 4.5-1.5S10 19 12 19s2.5-1.5 4.5-1.5S19 19 21 19M12 3v7M8.5 6.5 12 3l3.5 3.5" /></svg>
                </span>
                <div class="max-w-[85%] rounded-2xl rounded-tl-md border border-sand-200 bg-white px-3.5 py-2.5 text-sm leading-relaxed text-gray-800 shadow-sm">
                    Maayong adlaw! I can tell you about our cottages, nightly rates, open dates, and resort policies. I can’t make or change bookings, but I can point you to the right page.
                </div>
            </div>

            <div x-show="!messages.length" class="flex flex-wrap gap-2 pl-9" x-reveal.stagger>
                @foreach ($suggestions as $suggestion)
                    <button type="button" @click="ask(@js($suggestion))"
                            class="rounded-md border border-lagoon-950/10 bg-white px-3 py-1.5 text-left text-xs font-semibold text-lagoon-800 transition-colors duration-200 hover:border-lagoon-300 hover:bg-lagoon-50 active:scale-[.98]">
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>

            <template x-for="message in messages" :key="message.id">
                <div class="flex animate-fade-up gap-2.5 [animation-duration:.4s]" :class="message.role === 'user' ? 'justify-end' : ''">
                    <template x-if="message.role !== 'user'">
                        <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full"
                              :class="message.role === 'error' ? 'bg-coral-100 text-coral-700' : 'bg-lagoon-100 text-lagoon-800'" aria-hidden="true">
                            <svg x-show="message.role === 'bot'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15c2 0 2-1.5 4.5-1.5S10 15 12 15s2.5-1.5 4.5-1.5S19 15 21 15M3 19c2 0 2-1.5 4.5-1.5S10 19 12 19s2.5-1.5 4.5-1.5S19 19 21 19M12 3v7M8.5 6.5 12 3l3.5 3.5" /></svg>
                            <svg x-show="message.role === 'error'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 8v5M12 16.5v.5" /><circle cx="12" cy="12" r="9" /></svg>
                        </span>
                    </template>
                    <div class="max-w-[85%] whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed"
                         :class="{
                            'rounded-br-md bg-lagoon-800 text-cream': message.role === 'user',
                            'rounded-tl-md border border-sand-200 bg-white text-gray-800 shadow-sm': message.role === 'bot' && !message.fallback,
                            'rounded-tl-md border border-sand-300 bg-sand-50 text-gray-800': message.role === 'bot' && message.fallback,
                            'rounded-tl-md border border-coral-200 bg-coral-50 text-coral-900': message.role === 'error',
                         }"
                         x-text="message.text"></div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="sending" x-cloak class="flex gap-2.5" aria-label="Assistant is typing">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-lagoon-100" aria-hidden="true"></span>
                <div class="flex items-center gap-1 rounded-2xl rounded-tl-md border border-sand-200 bg-white px-4 py-3 shadow-sm">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-lagoon-500 motion-reduce:animate-none"></span>
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-lagoon-500 [animation-delay:120ms] motion-reduce:animate-none"></span>
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-lagoon-500 [animation-delay:240ms] motion-reduce:animate-none"></span>
                </div>
            </div>
        </div>

        {{-- Composer --}}
        <form @submit.prevent="send()" class="border-t border-sand-200 bg-white/80 px-3 pb-3 pt-3">
            <div class="flex items-end gap-2">
                <label for="chatbot-input" class="sr-only">Your question</label>
                <textarea id="chatbot-input" x-ref="input" x-model="draft" rows="1"
                          :maxlength="maxLength"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"
                          @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 112) + 'px'"
                          placeholder="Ask about rates, dates, or policies…"
                          class="field max-h-28 min-h-[2.75rem] flex-1 resize-none !py-2.5 text-sm"></textarea>
                <button type="submit" :disabled="sending || !draft.trim()"
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-lagoon-800 text-cream transition-all duration-200 ease-out-soft hover:bg-lagoon-700 active:scale-90 active:shadow-press disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
                        aria-label="Send question">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h13M13 6l6 6-6 6" /></svg>
                </button>
            </div>
            <p class="mt-2 flex justify-between gap-3 px-1 text-[11px] leading-snug text-gray-500">
                <span>Chats are logged to improve service. Please don’t share personal or payment details here.</span>
                <span x-show="remaining < 100" x-cloak x-text="remaining" :class="remaining < 20 ? 'text-coral-600 font-semibold' : ''" class="shrink-0 tabular-nums"></span>
            </p>
        </form>
    </section>

    {{-- Launcher --}}
    <button type="button" x-ref="launcher" @click="toggle()"
            :aria-expanded="open.toString()" aria-controls="chatbot-panel"
            :aria-label="open ? 'Close chat' : 'Ask the resort assistant'"
            class="group relative grid h-14 w-14 animate-pop-in place-items-center rounded-full bg-lagoon-950 text-cream shadow-lift ring-1 ring-cream/20 transition-all duration-300 ease-out-soft [animation-delay:900ms] hover:-translate-y-0.5 hover:bg-lagoon-800 active:translate-y-0 active:scale-95">
        <span class="absolute -right-0.5 -top-0.5 flex h-3.5 w-3.5" x-show="!open && !messages.length" aria-hidden="true">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-coral-400 opacity-70 motion-reduce:animate-none"></span>
            <span class="relative inline-flex h-3.5 w-3.5 rounded-full border-2 border-cream bg-coral-500"></span>
        </span>
        <span class="relative grid h-6 w-6 place-items-center">
            <svg x-show="!open" class="h-6 w-6 transition-transform duration-300 group-hover:-rotate-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5v-9Z" /><path stroke-linecap="round" d="M8 9.5c1.3 0 1.3-1 2.7-1s1.3 1 2.6 1 1.4-1 2.7-1" /></svg>
            <svg x-show="open" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 9l6 6 6-6" /></svg>
        </span>
        {{-- Label slides out on hover/focus only, so the button never covers page content --}}
        <span x-show="!open" aria-hidden="true"
              class="pointer-events-none absolute right-full top-1/2 mr-3 hidden -translate-y-1/2 translate-x-2 whitespace-nowrap rounded bg-lagoon-950 px-3 py-1.5 text-xs font-semibold text-cream opacity-0 shadow-lift transition-all duration-200 ease-out-soft group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100 sm:block">
            Ask us
        </span>
    </button>
</div>
