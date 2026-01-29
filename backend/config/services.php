<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'admin_ids' => array_filter(
            array_map('trim', explode(',', env('TELEGRAM_ADMIN_IDS', '')))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zibal Payment Gateway
    |--------------------------------------------------------------------------
    */

    'zibal' => [
        'merchant_id' => env('ZIBAL_MERCHANT_ID', 'zibal'),
        'callback_url' => env('ZIBAL_CALLBACK_URL', env('APP_URL') . '/api/payments/callback'),
        'sandbox' => env('ZIBAL_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marzban VPN Panel
    |--------------------------------------------------------------------------
    */

    'marzban' => [
        'default_inbounds' => env('MARZBAN_DEFAULT_INBOUNDS', 'VLESS TCP REALITY,VLESS_TCP'),
        'reality_public_key' => env('MARZBAN_REALITY_PUBLIC_KEY'),
        'reality_short_id' => env('MARZBAN_REALITY_SHORT_ID'),
        'reality_sni' => env('MARZBAN_REALITY_SNI'),
    ],

];
