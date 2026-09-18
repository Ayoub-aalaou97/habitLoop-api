<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('MAIL_FROM_ADDRESS', 'mailto:reminders@habitloop.local')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
