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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('AUTH_GOOGLE_CLIENT_ID'),
        'client_secret' => env('AUTH_GOOGLE_CLIENT_SECRET'),
        'redirect' => env('AUTH_GOOGLE_REDIRECT'),
    ],

    'github' => [
        'client_id' => env('AUTH_GITHUB_CLIENT_ID'),
        'client_secret' => env('AUTH_GITHUB_CLIENT_SECRET'),
        'redirect' => env('AUTH_GITHUB_REDIRECT'),
    ],

    'discord' => [
        'client_id' => env('AUTH_DISCORD_CLIENT_ID'),
        'client_secret' => env('AUTH_DISCORD_CLIENT_SECRET'),
        'redirect' => env('AUTH_DISCORD_REDIRECT'),
    ],

    'reddit' => [
        'client_id' => env('AUTH_REDDIT_CLIENT_ID'),
        'client_secret' => env('AUTH_REDDIT_CLIENT_SECRET'),
        'redirect' => env('AUTH_REDDIT_REDIRECT'),
    ],

];
