<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'statement_descriptor' => env('STRIPE_STATEMENT_DESCRIPTOR', 'INFOSOCIETE'),
    'recurring_price_id' => env('STRIPE_RECURRING_PRICE_ID'),
    'recurring_amount' => (float) env('STRIPE_RECURRING_AMOUNT', 49.99),
    'trial_hours' => (int) env('STRIPE_TRIAL_HOURS', 72),
    'recurring_lookup_key' => env('STRIPE_RECURRING_LOOKUP_KEY', 'infosociete-premium-4999-eur-monthly'),
    'recurring_product_name' => env('STRIPE_RECURRING_PRODUCT_NAME', 'Infosociete Premium Monthly'),
];
