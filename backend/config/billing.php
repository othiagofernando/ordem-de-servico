<?php

return [
    'gateway' => env('BILLING_GATEWAY', 'asaas'),
    'asaas' => [
        'sandbox' => (bool) env('ASAAS_SANDBOX', true),
        'base_url' => env(
            'ASAAS_BASE_URL',
            (bool) env('ASAAS_SANDBOX', true)
                ? 'https://sandbox.asaas.com/api/v3'
                : 'https://api.asaas.com/v3',
        ),
        'api_key' => env('ASAAS_API_KEY'),
        'wallet_id' => env('ASAAS_WALLET_ID'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'default_cycle_days' => (int) env('BILLING_DEFAULT_CYCLE_DAYS', 30),
        'force_remote_in_tests' => (bool) env('ASAAS_FORCE_REMOTE_IN_TESTS', false),
    ],
];
