<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class StripeTrialFeePriceResolver
{
    public function __construct(
        private StripeService $stripe
    ) {
    }

    public function resolve(): ?string
    {
        $configured = trim((string) config('stripe.trial_fee_price_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $currency = strtolower((string) config('stripe.currency', 'eur'));
        $lookupKey = (string) config('stripe.trial_fee_lookup_key', 'docsflow-trial-149-eur-once');
        $amountCents = (int) round(((float) config('stripe.trial_fee_amount', 1.49)) * 100);
        $productName = (string) config('stripe.trial_fee_product_name', 'DOCSFLOW Essai 72h');

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
                    'type' => 'trial_fee',
                ],
            ]);

            $price = $this->stripe->client()->prices->create([
                'product' => $product->id,
                'unit_amount' => $amountCents,
                'currency' => $currency,
                'lookup_key' => $lookupKey,
            ]);

            return $price->id;
        } catch (\Throwable $e) {
            Log::error('stripe_resolve_trial_fee_price_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

