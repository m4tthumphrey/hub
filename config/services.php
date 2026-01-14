<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'petsure' => [
        'client'       => [
            'base_uri' => env('PETSURE_URL'),
            'headers'  => [
                'Authorization'   => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIzMTM5MDY5MzY0IiwianRpIjoiODdkYTQ5MmYtMzAzMC00NjQ5LTg1M2YtY2QwYjhlNWIyYjQ0IiwiaWF0IjoxNzY4MzM2Nzk0LCJjbGllbnRfdWlkIjoid2ViIiwiZGV2aWNlX2lkIjoid2ViIiwiaHR0cDovL3NjaGVtYXMueG1sc29hcC5vcmcvd3MvMjAwNS8wNS9pZGVudGl0eS9jbGFpbXMvZW1haWxhZGRyZXNzIjoibGVzbGV5d2F0dHMwMUBob3RtYWlsLmNvbSIsIm5iZiI6MTc2ODMzNjc5NCwiZXhwIjoxNzk5ODcyNzk0fQ.H9M4NqxeVyy_HCY3yw2-FHTQVwCzsw-WuuC5iiGeo50',
                'Accept'          => 'application/json, */*',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Accept-Language' => 'en-US,en-GB;q=0.9',
                'Content-Type'    => 'application/json',
                'Origin'          => 'https://surehub.io',
                'Referer'         => 'https://surehub.io',
            ]
        ],
        'auth'         => [
            'username' => '',
            'password' => ''
        ],
        'household_id' => env('PETSURE_HOUSEHOLD_ID')
    ],

    'pushover' => [
        'client' => [
            'base_uri' => env('PUSHOVER_URL'),
        ],
        'token'  => env('PUSHOVER_TOKEN'),
        'user'   => env('PUSHOVER_USER'),
        'device' => env('PUSHOVER_DEVICE'),
    ]

];
