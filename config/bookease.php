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

];
