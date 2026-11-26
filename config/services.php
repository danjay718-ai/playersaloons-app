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

    'stripe' => [
        'key' => env('STRIPE_KEY', env('STRIPE_PUBLIC_KEY')),
        'secret' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'payop' => [
        'public_key' => env('PAYOP_PUBLIC_KEY'),
        'secret' => env('PAYOP_SECRET_KEY'),
        'payout_token' => env('PAYOP_PAYOUT_TOKEN'),
        'payout_certificate' => env('PAYOP_PAYOUT_CERTIFICATE'),
    ],
    'youtube' => ['api_key' => env('YOUTUBE_API_KEY')],
    'twitch' => ['client_id' => env('TWITCH_CLIENT_ID'), 'access_token' => env('TWITCH_ACCESS_TOKEN')],
    'facebook' => ['access_token' => env('FACEBOOK_ACCESS_TOKEN')],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'playstrategy' => [
        'base_url' => rtrim((string) env('PLAYSTRATEGY_BASE_URL', 'https://playstrategy.org'), '/'),
        'client_id' => (string) env('PLAYSTRATEGY_CLIENT_ID', 'gamersrival.com'),
        'redirect_uri' => env('PLAYSTRATEGY_REDIRECT_URI'),
        'scopes' => (string) env('PLAYSTRATEGY_OAUTH_SCOPES', 'challenge:write'),
        'timeout_seconds' => (int) env('PLAYSTRATEGY_TIMEOUT_SECONDS', 10),
    ],

    'gemini' => [
        'api_key' => (string) env('GEMINI_API_KEY', ''),
        'dispute_model' => (string) env('GEMINI_DISPUTE_MODEL', 'gemini-2.5-flash'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
