<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'statement_descriptor' => env('STRIPE_STATEMENT_DESCRIPTOR', 'INFOSOCIETE'),
];
