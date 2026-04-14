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
                'Authorization'   => 'Bearer ' . env('PETSURE_ACCESS_TOKEN'),
                'Accept'          => 'application/json, */*',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Accept-Language' => 'en-US,en-GB;q=0.9',
                'Content-Type'    => 'application/json',
                'Origin'          => 'https://surehub.io',
                'Referer'         => 'https://surehub.io',
            ]
        ],
        'auth'         => [
            'username' => env('PETSURE_USERNAME'),
            'password' => env('PETSURE_PASSWORD')
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
    ],

    'peloton' => [
        'client'  => [
            'base_uri' => 'https://api.onepeloton.co.uk/api/',
            'headers'  => [
                'authority'        => 'api.onepeloton.co.uk',
                'accept'           => 'application/json, text/plain, */*',
                'peloton-platform' => 'web',
                'accept-language'  => 'en-GB',
                'user-agent'       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.85 Safari/537.36',
                'origin'           => 'https://members.onepeloton.co.uk',
                'referer'          => 'https://members.onepeloton.co.uk/'
            ],
        ],
        'auth'    => [
            'username' => env('PELOTON_USERNAME'),
            'password' => env('PELOTON_PASSWORD')
        ],
        'user_id' => env('PELOTON_USER_ID'),
    ],

    'withings' => [
        'client_id'         => env('WITHINGS_CLIENT_ID'),
        'client_secret'     => env('WITHINGS_CLIENT_SECRET'),
        'redirect_url'      => env('WITHINGS_REDIRECT_URL'),
        'authorization_url' => 'https://account.withings.com/oauth2_user/authorize2?response_type=code&client_id=%s&scope=user.info,user.metrics,user.activity&redirect_uri=%s&state=%s',

        'client' => [
            'base_uri' => 'https://wbsapi.withings.net/v2/',
        ]
    ]

];
