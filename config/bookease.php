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
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    | driver: "fake"     – local sandbox simulator (no API keys, no real money)
    |         "paymongo" – PayMongo Checkout (use TEST keys; sandbox only)
    |
    | The down-payment percentage is a resort policy: confirm it with the owner.
    */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),

        'down_payment_percent' => (int) env('BOOKEASE_DOWN_PAYMENT_PERCENT', 50),

        // Reject webhooks whose signed timestamp is older than this (replay protection).
        'webhook_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),

        // Sandbox only: live-mode events are refused until this is explicitly enabled.
        'allow_live_mode' => (bool) env('PAYMENT_ALLOW_LIVE_MODE', false),

        // Signing secret used by the fake gateway (falls back to APP_KEY).
        'fake_webhook_secret' => env('FAKE_GATEWAY_WEBHOOK_SECRET'),
    ],

];
