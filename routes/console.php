<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Payment;
use App\Services\StripeService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:repair-subscriptions {--email=} {--limit=100} {--dry-run}', function () {
    $email = trim((string) $this->option('email'));
    $limit = max((int) $this->option('limit'), 1);
    $dryRun = (bool) $this->option('dry-run');
    $currency = strtolower((string) config('stripe.currency', 'eur'));
    $lookupKey = (string) config('stripe.recurring_lookup_key', 'infosociete-premium-4999-eur-monthly');
    $amountCents = (int) round(((float) config('stripe.recurring_amount', 49.99)) * 100);
    $productName = (string) config('stripe.recurring_product_name', 'Infosociete Premium Monthly');
    $trialHours = max((int) config('stripe.trial_hours', 72), 1);

    if (!config('stripe.secret')) {
        $this->error('STRIPE_SECRET manquante.');
        return;
    }

    $stripe = app(StripeService::class)->client();

    $resolvePriceId = function () use ($stripe, $lookupKey, $amountCents, $currency, $productName) {
        $configured = trim((string) config('stripe.recurring_price_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $prices = $stripe->prices->all([
            'lookup_keys' => [$lookupKey],
            'active' => true,
            'limit' => 1,
        ]);
        if (!empty($prices->data)) {
            return $prices->data[0]->id;
        }

        $product = $stripe->products->create([
            'name' => $productName,
            'metadata' => [
                'app' => 'infosociete',
                'type' => 'premium_subscription',
            ],
        ]);

        $price = $stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amountCents,
            'currency' => $currency,
            'recurring' => ['interval' => 'month'],
            'lookup_key' => $lookupKey,
        ]);

        return $price->id;
    };

    try {
        $priceId = $resolvePriceId();
    } catch (\Throwable $e) {
        $this->error('Impossible de résoudre le prix récurrent Stripe: '.$e->getMessage());
        return;
    }

    $query = Payment::query()
        ->whereNotNull('stripe_intent_id')
        ->where(function ($q) {
            $q->where('status', 'succeeded_without_subscription')
                ->orWhere(function ($qq) {
                    $qq->where('status', 'succeeded')
                        ->where(function ($qqq) {
                            $qqq->whereNull('metadata->subscription_id')
                                ->orWhere('metadata->subscription_id', '');
                        });
                });
        })
        ->latest();

    if ($email !== '') {
        $query->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
    }

    $payments = $query->limit($limit)->get();
    if ($payments->isEmpty()) {
        $this->info('Aucun paiement à réparer.');
        return;
    }

    $success = 0;
    $failed = 0;

    foreach ($payments as $payment) {
        $metadata = is_array($payment->metadata ?? null) ? $payment->metadata : [];
        $intentId = $payment->stripe_intent_id;
        $this->line("Traitement payment #{$payment->id} | intent={$intentId}");

        try {
            $intent = $stripe->paymentIntents->retrieve($intentId, ['expand' => ['payment_method', 'customer']]);
            if (($intent->status ?? '') !== 'succeeded') {
                throw new \RuntimeException('intent_not_succeeded:'.$intent->status);
            }

            $customerId = $intent->customer?->id ?? ($metadata['stripe_customer_id'] ?? null);
            $paymentMethodId = $intent->payment_method?->id ?? null;

            if (!$customerId) {
                $createdCustomer = $stripe->customers->create([
                    'email' => $payment->email ?: null,
                    'name' => $payment->holder_name ?: null,
                    'metadata' => [
                        'siret_or_siren' => (string) ($payment->siret_or_siren ?? ''),
                    ],
                ]);
                $customerId = $createdCustomer->id;
            }

            if ($paymentMethodId) {
                try {
                    $stripe->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);
                } catch (\Throwable $e) {
                    $alreadyAttached = str_contains(mb_strtolower($e->getMessage()), 'already attached');
                    if (!$alreadyAttached) {
                        throw $e;
                    }
                }

                $stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }

            $subscription = null;
            $existing = $stripe->subscriptions->all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 30,
            ]);

            foreach ($existing->data as $sub) {
                $subStatus = $sub->status ?? '';
                if (!in_array($subStatus, ['active', 'trialing', 'past_due', 'incomplete', 'unpaid'], true)) {
                    continue;
                }
                foreach (($sub->items->data ?? []) as $item) {
                    if (($item->price->id ?? null) === $priceId) {
                        $subscription = $sub;
                        break 2;
                    }
                }
            }

            if (!$subscription && !$dryRun) {
                $subscription = $stripe->subscriptions->create([
                    'customer' => $customerId,
                    'items' => [
                        ['price' => $priceId],
                    ],
                    'trial_end' => now()->timestamp + ($trialHours * 3600),
                    'collection_method' => 'charge_automatically',
                    'default_payment_method' => $paymentMethodId,
                    'metadata' => [
                        'siret_or_siren' => (string) ($payment->siret_or_siren ?? ''),
                        'email' => (string) ($payment->email ?? ''),
                        'source_path' => (string) ($payment->source_path ?? '/paiement'),
                        'repair_command' => 'true',
                    ],
                ]);
            }

            $newMetadata = array_merge($metadata, [
                'stripe_customer_id' => $customerId,
                'subscription_id' => $subscription?->id ?? null,
                'subscription_status' => $subscription?->status ?? null,
                'subscription_trial_end' => $subscription?->trial_end ?? null,
                'subscription_error' => null,
            ]);

            if (!$dryRun) {
                $payment->update([
                    'status' => 'succeeded',
                    'metadata' => $newMetadata,
                ]);
            }

            $success++;
            $this->info('  ✓ abonnement lié: '.($subscription?->id ?? '[dry-run]'));
        } catch (\Throwable $e) {
            $failed++;
            $newMetadata = array_merge($metadata, [
                'subscription_error' => $e->getMessage(),
            ]);
            if (!$dryRun) {
                $payment->update([
                    'status' => 'succeeded_without_subscription',
                    'metadata' => $newMetadata,
                ]);
            }
            $this->error('  ✗ erreur: '.$e->getMessage());
        }
    }

    $this->newLine();
    $this->info("Terminé. Succès={$success}, Échecs={$failed}, DryRun=".($dryRun ? 'yes' : 'no'));
})->purpose('Répare les abonnements Stripe manquants pour les paiements déjà validés');
