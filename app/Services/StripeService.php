<?php

namespace App\Services;

use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $secret = config('stripe.secret');
        $this->client = new StripeClient($secret ?: '');
    }

    public function client(): StripeClient
    {
        return $this->client;
    }
}
