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

    /*
    |--------------------------------------------------------------------------
    | Admin emails
    |--------------------------------------------------------------------------
    |
    | Users whose email is listed here get access to app-wide operational views
    | (like the incoming Fireflies webhook log). There is no full admin panel;
    | this is a lightweight gate. Override via the TODAI_ADMIN_EMAILS env var
    | (comma-separated).
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TODAI_ADMIN_EMAILS', 'danny@krafters.nl')),
    ))),

];
