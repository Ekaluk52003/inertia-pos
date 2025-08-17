<?php

return [

    /*
    |--------    'slipok' => [
        'api_key' => env('SLIPOK_API_KEY'),
        'branch_id' => env('SLIPOK_BRANCH_ID'),
        // If SLIPOK_OK_USE=false OR SLIPOK_DEV_MODE=true we simulate success
        'dev_mode' => env('SLIPOK_DEV_MODE', ! env('SLIPOK_OK_USE', true)) || ! env('SLIPOK_OK_USE', true),
        // Whether to verify SSL peer/host (set SLIPOK_VERIFY_SSL=false in local dev if you lack CA bundle)
        'verify_ssl' => env('SLIPOK_VERIFY_SSL', true),
        'cacert_path' => env('SLIPOK_CACERT_PATH'),
        'ok_use' => env('SLIPOK_OK_USE', true),
    ],---------------------------------------------------------
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

    'slipok' => [
        'api_key' => env('SLIPOK_API_KEY'),
        'branch_id' => env('SLIPOK_BRANCH_ID'),
        // If SLIPOK_OK_USE=false OR SLIPOK_DEV_MODE=true we simulate success
        'dev_mode' => env('SLIPOK_DEV_MODE', ! env('SLIPOK_OK_USE', true)) || ! env('SLIPOK_OK_USE', true),
        // Whether to verify SSL peer/host (set SLIPOK_VERIFY_SSL=false in local dev if you lack CA bundle)
        'verify_ssl' => env('SLIPOK_VERIFY_SSL', true),
        'ok_use' => env('SLIPOK_OK_USE', true),
    ],

];
