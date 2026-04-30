<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class StripeRecurringPriceResolver
{
    public function __construct(
        private StripeService $stripe
    ) {
    }

    public function resolve(): ?string
    {
        $configured = trim((string) config('stripe.recurring_price_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $currency = strtolower((string) config('stripe.currency', 'eur'));
        $lookupKey = (string) config('stripe.recurring_lookup_key', 'docsflow-premium-4999-eur-monthly');
        $amountCents = (int) round(((float) config('stripe.recurring_amount', 49.99)) * 100);
        $productName = (string) config('stripe.recurring_product_name', 'DOCSFLOW Premium Monthly');

        try {
            $prices = $this->stripe->client()->prices->all([
                'lookup_keys' => [$lookupKey],
                'active' => true,
                'limit' => 1,
            ]);
            if (!empty($prices->data)) {
                return $prices->data[0]->id;
            }

            $product = $this->stripe->client()->products->create([
                'name' => $productName,
                'metadata' => [
                    'app' => 'docsflow',
                    'type' => 'premium_subscription',
                ],
            ]);

            $price = $this->stripe->client()->prices->create([
                'product' => $product->id,
                'unit_amount' => $amountCents,
                'currency' => $currency,
                'recurring' => ['interval' => 'month'],
                'lookup_key' => $lookupKey,
            ]);

            return $price->id;
        } catch (\Throwable $e) {
            Log::error('stripe_resolve_recurring_price_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

