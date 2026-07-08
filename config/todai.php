<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily reminders
    |--------------------------------------------------------------------------
    |
    | Configuration for the morning task reminder (todai:send-daily-reminders).
    | "send_empty" controls whether users with nothing due still receive a
    | reminder; "hour" is the local hour the scheduled command runs.
    |
    */

    'reminders' => [
        'send_empty' => env('TODAI_REMINDERS_SEND_EMPTY', false),
        'hour' => env('TODAI_REMINDERS_HOUR', 7),
    ],

];
