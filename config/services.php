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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE', 'https://generativelanguage.googleapis.com/v1beta'),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),
        // Free-tier requests-per-day limits, per model (from Google AI Studio's rate-limit dashboard).
        'daily_limits' => [
            'gemini-3.1-flash-lite' => 500,
            'gemini-3.5-flash-lite' => 500,
            'gemini-3.6-flash' => 20,
        ],
    ],

    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', 'ffmpeg'),
    ],

    // Re-seeded on every boot (see DatabaseSeeder) so the one allowed login
    // always exists even on hosts with no persistent disk, where the
    // database resets on every restart/redeploy.
    'admin_seed' => [
        'email' => env('ADMIN_SEED_EMAIL'),
        'name' => env('ADMIN_SEED_NAME', 'Admin'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

];
