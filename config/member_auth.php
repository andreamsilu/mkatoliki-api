<?php

return [
    'password_login_enabled' => (bool) env('MEMBER_PASSWORD_LOGIN_ENABLED', false),
    'country_code' => env('MEMBER_PHONE_COUNTRY_CODE', '255'),
    'code_ttl_seconds' => 300,
    'resend_seconds' => 60,
    'max_attempts' => 5,
    'sms' => [
        'driver' => env('MEMBER_SMS_DRIVER', 'preview'),
        'url' => env('MEMBER_SMS_WEBHOOK_URL'),
        'token' => env('MEMBER_SMS_WEBHOOK_TOKEN'),
        'sender' => env('MEMBER_SMS_SENDER', 'Mkatolik'),
    ],
];
