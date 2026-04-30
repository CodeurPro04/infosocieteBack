<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'statement_descriptor' => env('STRIPE_STATEMENT_DESCRIPTOR', 'DOCSFLOW'),
    'recurring_price_id' => env('STRIPE_RECURRING_PRICE_ID'),
    'recurring_amount' => (float) env('STRIPE_RECURRING_AMOUNT', 49.99),
    'trial_hours' => (int) env('STRIPE_TRIAL_HOURS', 72),
    'trial_fee_amount' => (float) env('STRIPE_TRIAL_FEE_AMOUNT', 1.49),
    'trial_fee_price_id' => env('STRIPE_TRIAL_FEE_PRICE_ID'),
    'trial_fee_lookup_key' => env('STRIPE_TRIAL_FEE_LOOKUP_KEY', 'docsflow-trial-149-eur-once'),
    'trial_fee_product_name' => env('STRIPE_TRIAL_FEE_PRODUCT_NAME', 'DOCSFLOW Essai 72h'),
    'recurring_lookup_key' => env('STRIPE_RECURRING_LOOKUP_KEY', 'docsflow-premium-4999-eur-monthly'),
    'recurring_product_name' => env('STRIPE_RECURRING_PRODUCT_NAME', 'DOCSFLOW Premium Monthly'),
];
