<?php

/*
|--------------------------------------------------------------------------
| BookEase booking rules
|--------------------------------------------------------------------------
| Changeover times come from the resort (12:00 NN check-out, 2:00 PM
| check-in). The limits below are system safeguards; confirm the values
| with the owner and adjust them through .env if needed.
*/

return [

    'check_in_time' => env('BOOKEASE_CHECK_IN_TIME', '14:00'),

    'check_out_time' => env('BOOKEASE_CHECK_OUT_TIME', '12:00'),

    // Longest stay accepted in one online booking.
    'max_nights' => (int) env('BOOKEASE_MAX_NIGHTS', 30),

    // How far ahead (in days) a check-in date may be booked.
    'booking_window_days' => (int) env('BOOKEASE_BOOKING_WINDOW_DAYS', 365),

    /*
    | Staff/owner console: how often an open console tab asks for new alerts.
    | Raise it on hosting with a daily hit limit (InfinityFree: 90 or more).
    */
    'console' => [
        'poll_seconds' => max(15, (int) env('BOOKEASE_BELL_POLL_SECONDS', 30)),
    ],

    /*
    | Hosting without cron (e.g. InfinityFree): run the daily clean-up from the
    | first page visit of each day instead of the scheduler.
    */
    'maintenance' => [
        'on_requests' => (bool) env('BOOKEASE_CLEANUP_ON_REQUESTS', false),
    ],

    // Unconfirmed requests one guest may have waiting at once. Pending requests
    // hold their dates until staff act, so this stops a single account from
    // blocking the resort. Requests whose dates have passed don't count.
    'max_pending_per_guest' => (int) env('BOOKEASE_MAX_PENDING_PER_GUEST', 3),

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    | driver: "fake"     – local sandbox simulator (no API keys, no real money)
    |         "paymongo" – PayMongo Checkout (use TEST keys; sandbox only)
    |         "none"     – pay at the resort: no online checkout; staff record
    |                      cash at the front desk (hosting without webhooks)
    |
    | The down-payment percentage is a resort policy: confirm it with the owner.
    */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),

        // Whether guests can pay online. Views and services read this flag;
        // it is false only for PAYMENT_DRIVER=none.
        'online' => env('PAYMENT_DRIVER', 'fake') !== 'none',

        'down_payment_percent' => (int) env('BOOKEASE_DOWN_PAYMENT_PERCENT', 50),

        // Reject webhooks whose signed timestamp is older than this (replay protection).
        'webhook_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),

        // Sandbox only: live-mode events are refused until this is explicitly enabled.
        'allow_live_mode' => (bool) env('PAYMENT_ALLOW_LIVE_MODE', false),

        // Signing secret used by the fake gateway (falls back to APP_KEY).
        'fake_webhook_secret' => env('FAKE_GATEWAY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inquiry chatbot (RAG over live resort records)
    |--------------------------------------------------------------------------
    | Without GEMINI_API_KEY every question gets the fixed contact message.
    */
    'chatbot' => [
        'enabled' => (bool) env('CHATBOT_ENABLED', true),

        'max_question_length' => 500,

        // Per signed-in user, or per IP address for visitors.
        'per_minute' => (int) env('CHATBOT_PER_MINUTE', 10),
        'per_day' => (int) env('CHATBOT_PER_DAY', 150),

        // How many days of booked nights (from today) go into the context.
        'availability_days' => (int) env('CHATBOT_AVAILABILITY_DAYS', 120),

        // Earlier exchanges of the same chat sent back to the model for follow-ups.
        'history_turns' => 3,
        'history_minutes' => 30,

        // The assembled context is cached briefly and cleared whenever a
        // cottage or booking changes, so availability is never minutes stale.
        'cache_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resort contact details
    |--------------------------------------------------------------------------
    | Shown in the chatbot's fallback message and given to the model. Leave a
    | value empty rather than guessing; the chatbot never invents contacts.
    */
    'contact' => [
        'phone' => env('RESORT_CONTACT_PHONE'),
        'email' => env('RESORT_CONTACT_EMAIL'),
        'facebook' => env('RESORT_CONTACT_FACEBOOK'),
    ],

];
