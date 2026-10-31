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

    'openpix' => [
        'url' => env('OPENPIX_URL'),
        'appid' => env('OPENPIX_APPID'),
        'webhook_token' => env('OPENPIX_WEBHOOK_TOKEN'),
    ],

    // Provedor de depósito PIX: 'novus' (padrão) ou 'openpix' (Woovi)
    'pix_provider' => env('PIX_PROVIDER', 'novus'),
 'novus' => [
    'url' => env('NOVUS_URL'),
    'token' => env('NOVUS_TOKEN'),

    'webhook_token' => env('NOVUS_WEBHOOK_TOKEN'),

    'postback_url' => env('NOVUS_POSTBACK'),
    'postback_out_url' => env('NOVUS_POSTBACK_OUT'),
],

];
