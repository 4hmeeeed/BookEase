{{--
    Privacy notice (RA 10173, the Data Privacy Act of 2012). What BookEase stores is
    described from the code; anything only the owner can say is an <x-placeholder>.
    The owner supplies the final wording before the draft banner comes off.
--}}
@php
    $payOnline = config('bookease.payments.online');
    $contact = array_filter((array) config('bookease.contact', []));
    $sections = [
        'who' => 'Who we are',
        'collect' => 'What we collect',
        'why' => 'Why we use it',
        'share' => 'Who can see it',
        'keep' => 'How long we keep it',
        'protect' => 'How we protect it',
        'rights' => 'Your rights',
        'contact' => 'Contact us',
    ];
    $h2 = 'flex items-baseline gap-3 font-serif text-2xl font-semibold text-lagoon-950';
    $dot = 'mt-[0.6rem] h-1.5 w-1.5 shrink-0 rounded-full bg-lagoon-700';
@endphp

<x-public-layout title="Privacy notice">
    <section class="mx-auto max-w-7xl px-4 pb-20 pt-10 sm:px-6 lg:px-8">
        <header class="max-w-3xl">
            <p class="sounding text-gray-500">Data Privacy Act of 2012 · RA 10173</p>
            <h1 class="mt-3 font-display text-4xl leading-[1.05] text-lagoon-950 sm:text-5xl">Privacy notice</h1>
            <p class="mt-5 max-w-prose text-[1.05rem] leading-relaxed text-gray-700">
                Labnasan Resort respects your privacy. This notice explains what personal data BookEase, the resort’s booking website, collects, why, who can see it, and how you can use your rights under Republic Act No. 10173, the Data Privacy Act of 2012.
            </p>
        </header>

        <div role="note" class="mt-8 max-w-3xl rounded-md border border-l-4 border-coral-200 border-l-coral-500 bg-coral-50/60 px-5 py-4 text-sm leading-relaxed text-gray-800">
            <strong class="font-semibold text-lagoon-950">Draft notice.</strong>
            The highlighted parts are placeholders: the resort owner reviews this page and supplies the final wording. Until then, please contact the resort with any privacy question.
        </div>

        <div class="mt-12 grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-16">
            <nav aria-label="On this page" class="hidden lg:block">
                <ol class="sticky top-28 space-y-2.5 border-l border-lagoon-950/10 pl-4 text-sm">
                    @foreach ($sections as $id => $label)
                        <li><a href="#{{ $id }}" class="link-underline text-gray-600 hover:text-lagoon-950">{{ $label }}</a></li>
                    @endforeach
                </ol>
            </nav>

            <div class="max-w-prose space-y-12 leading-relaxed text-gray-700">
                <section id="who" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">01</span>Who we are</h2>
                    <p class="mt-3">
                        <x-placeholder>the resort’s registered business name</x-placeholder>, operating as Labnasan Resort in Barangay Bacubac, Basey, Samar, decides how the personal data on this site is used. The law calls this the personal information controller. BookEase is the resort’s booking website.
                    </p>
                </section>

                <section id="collect" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">02</span>What we collect</h2>
                    <ul class="mt-4 space-y-3">
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><strong class="font-semibold text-lagoon-950">Your account:</strong> your name, your email address, and a password that is stored only in scrambled (hashed) form. If you sign up with Google, Google shares your name and email address with us.</span></li>
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><strong class="font-semibold text-lagoon-950">Your bookings:</strong> the cottage, your dates and number of guests, the mobile number you give when you reserve, any special requests, and the history of each booking’s status.</span></li>
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><strong class="font-semibold text-lagoon-950">Payments:</strong> the amounts, how and when you paid, and the receipts we issue.
                            @if ($payOnline)
                                Online payments go through the payment provider’s secure page, so we never see or store your GCash or Maya login details.
                            @elseif (app(\App\Services\WalletTransferService::class)->enabled())
                                You pay at the front desk, or send GCash or Maya to the resort’s own wallet and upload the receipt. We keep that receipt’s reference number and screenshot so staff can check the transfer. We never ask for your MPIN or one-time code.
                            @else
                                You pay at the front desk, where staff record the amount and how you paid.
                            @endif
                        </span></li>
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><strong class="font-semibold text-lagoon-950">Questions for the assistant:</strong> the website’s chat assistant keeps each question, the resort information used to answer it, and the answer, so the resort can check and improve its answers.</span></li>
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><strong class="font-semibold text-lagoon-950">Site records:</strong> while you use the site, a session record keeps your IP address and browser type, and the site sets the cookies it needs for signing in and for security.</span></li>
                    </ul>
                </section>

                <section id="why" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">03</span>Why we use it</h2>
                    <p class="mt-3">
                        To take and confirm your reservations, keep the availability calendar accurate, record your payments and issue receipts, email you about your bookings, answer your questions, and keep the site secure.
                    </p>
                    <p class="mt-3">
                        We rely on your consent, which you give when you create an account; on the need to carry out the booking you ask for; and on our legal duties, such as issuing official receipts. <x-placeholder>confirm these grounds and add any other purpose</x-placeholder>.
                    </p>
                </section>

                <section id="share" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">04</span>Who can see it</h2>
                    <p class="mt-3">
                        The resort’s owner and front-desk staff, each through their own account; what an account can see depends on its role. Service providers that run the site process data for the resort:
                    </p>
                    <ul class="mt-3 space-y-2">
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span><x-placeholder>the web host and the email provider</x-placeholder></span></li>
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span>Google, if you sign in with Google</span></li>
                        @if ($payOnline)
                            <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span>the payment provider, for online payments</span></li>
                        @endif
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span>Google’s Gemini service, which receives the text of your questions to draft the assistant’s answers</span></li>
                    </ul>
                    <p class="mt-3">We never sell your personal data.</p>
                </section>

                <section id="keep" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">05</span>How long we keep it</h2>
                    <p class="mt-3">
                        <x-placeholder>how long booking, payment and chat records are kept, and why</x-placeholder>.
                    </p>
                    <p class="mt-3">
                        You can delete your account from your profile page. <x-placeholder>what the resort keeps after that, for example records that tax rules require</x-placeholder>.
                    </p>
                </section>

                <section id="protect" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">06</span>How we protect it</h2>
                    <p class="mt-3">
                        Passwords are stored only in hashed form, and every staff member signs in with their own account. <x-placeholder>other safeguards, such as HTTPS on the live site and who can reach backups</x-placeholder>.
                    </p>
                </section>

                <section id="rights" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">07</span>Your rights</h2>
                    <p class="mt-3">Under the Data Privacy Act of 2012, you have the right to:</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ([
                            'be informed about how your personal data is used',
                            'see the personal data we hold about you',
                            'object to its use, and withdraw your consent',
                            'have it corrected if it is wrong',
                            'have it blocked, removed or destroyed',
                            'get a copy in a format you can reuse elsewhere',
                            'claim damages if it is misused',
                        ] as $right)
                            <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span>{{ $right }}</span></li>
                        @endforeach
                        <li class="flex gap-3"><span class="{{ $dot }}" aria-hidden="true"></span><span>file a complaint with the National Privacy Commission (<a href="https://privacy.gov.ph" class="link-underline text-lagoon-900" rel="noopener" target="_blank">privacy.gov.ph</a>)</span></li>
                    </ul>
                </section>

                <section id="contact" class="scroll-mt-28">
                    <h2 class="{{ $h2 }}"><span class="sounding text-coral-600">08</span>Contact us</h2>
                    <p class="mt-3">
                        For any privacy question or request, contact the resort’s data protection officer: <x-placeholder>their name, email address and phone number</x-placeholder>.
                    </p>
                    @if ($contact)
                        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                            @isset($contact['phone'])
                                <dt class="text-gray-500">Resort phone</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="link-underline text-lagoon-900">{{ $contact['phone'] }}</a></dd>
                            @endisset
                            @isset($contact['email'])
                                <dt class="text-gray-500">Resort email</dt>
                                <dd><a href="mailto:{{ $contact['email'] }}" class="link-underline text-lagoon-900">{{ $contact['email'] }}</a></dd>
                            @endisset
                            @if (isset($contact['facebook']) && Str::startsWith($contact['facebook'], ['https://', 'http://']))
                                <dt class="text-gray-500">Facebook</dt>
                                <dd><a href="{{ $contact['facebook'] }}" class="link-underline text-lagoon-900" rel="noopener" target="_blank">Message the resort</a></dd>
                            @endif
                        </dl>
                    @endif
                </section>

                <p class="border-t border-lagoon-950/10 pt-6 text-sm text-gray-500">
                    Last updated: <x-placeholder>date</x-placeholder>. Any change to this notice will be posted on this page.
                </p>
            </div>
        </div>
    </section>
</x-public-layout>
