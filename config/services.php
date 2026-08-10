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

    // Expo Push API — the delivery channel for the mobile app. Overridable via
    // env for local testing against a mock server.
    'expo' => [
        'push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
    ],

    // Web Push (VAPID) — the delivery channel for the web storefront. The
    // PUBLIC key is shared with the browser (GET /api/push/vapid-public-key)
    // and used to create the subscription; the PRIVATE key is used by
    // PushNotificationService to sign and encrypt outgoing pushes and NEVER
    // leaves the server. Both are required for web push to work — when either
    // is empty, the service silently skips web rows (the storefront still
    // works without push). Generate with:
    //   OPENSSL_CONF=/etc/ssl/openssl.cnf php8.4 -r "require 'vendor/autoload.php';
    //   print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
    // (OPENSSL_CONF only needed on boxes whose PHP CLI loads no php.ini.)
    'push' => [
        'vapid_subject' => env('VAPID_SUBJECT', 'mailto:no-reply@localhost'),
        'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
