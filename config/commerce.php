<?php

return [
    'order_prefix' => env('COMMERCE_ORDER_PREFIX', 'HBL'),

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'cooldown_seconds' => (int) env('OTP_COOLDOWN_SECONDS', 60),
        'expose_code' => (bool) env('OTP_EXPOSE_CODE', false),
    ],

    'whatsapp' => [
        // "log" is deliberately the default until a provider is selected.
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'endpoint' => env('WHATSAPP_ENDPOINT'),
        'token' => env('WHATSAPP_TOKEN'),
        'from' => env('WHATSAPP_FROM', 'Hablun CCTV'),
        'admin_number' => env('WHATSAPP_ADMIN_NUMBER'),
    ],

    'payment' => [
        // Sandbox never contacts a payment provider and is intended for local
        // development until a provider (Midtrans/Xendit/etc.) is selected.
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
        'gateway_name' => env('PAYMENT_GATEWAY_NAME', 'sandbox'),
        'pending_minutes' => (int) env('PAYMENT_PENDING_MINUTES', 30),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'allowed_methods' => [
            'qris',
            'bank_transfer',
            'gopay',
            'ovo',
            'shopeepay',
        ],
    ],
];
