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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),        // Your Google Client ID
        'client_secret' => env('GOOGLE_CLIENT_SECRET'), // Your Google Client Secret
        'redirect' => env('GOOGLE_REDIRECT_URL'),      // Your Google Redirect URL
    ],
    'squad' => [
    'secret_key'     => env('SQUAD_SECRET_KEY'),
    'public_key'     => env('SQUAD_PUBLIC_KEY'),
    'base_url'       => env('SQUAD_BASE_URL', 'https://api.squadco.com'),
    'webhook_secret' => env('SQUAD_WEBHOOK_SECRET'),
],
    
    'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
],

    
];
