<?php

namespace App\Services;

use App\Mail\PaymentConfirmationMail;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentFulfillmentService
{
    public function __construct(
        private StripeService $stripe
    ) {
    }

    public function fulfill(Payment $payment): array
    {
        $metadata = $this->metadata($payment);
        $result = [
            'subscription_id' => $metadata['subscription_id'] ?? null,
            'subscription_status' => $metadata['subscription_status'] ?? null,
            'subscription_trial_end' => $metadata['subscription_trial_end'] ?? null,
            'subscription_error' => null,
            'confirmation_email_sent' => !empty($metadata['confirmation_email_sent_at']),
            'confirmation_email_error' => null,
        ];

        if (!empty($payment->stripe_intent_id)) {
            $subscriptionResult = $this->ensureSubscription($payment);
            $result = array_merge($result, $subscriptionResult);
            $payment->refresh();
        }

        $emailResult = $this->sendConfirmationEmail($payment);
        $result = array_merge($result, $emailResult);

        return $result;
    }

    private function ensureSubscription(Payment $payment): array
    {
        $metadata = $this->metadata($payment);
        if (!empty($metadata['subscription_id'])) {
            return [
                'subscription_id' => $metadata['subscription_id'],
                'subscription_status' => $metadata['subscription_status'] ?? null,
                'subscription_trial_end' => $metadata['subscription_trial_end'] ?? null,
                'subscription_error' => null,
            ];
        }

        try {
            $intent = $this->stripe->client()->paymentIntents->retrieve(
                $payment->stripe_intent_id,
                ['expand' => ['payment_method', 'customer']]
            );
        } catch (\Throwable $e) {
            $error = 'intent_fetch_failed: '.$e->getMessage();
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error]);
            return ['subscription_error' => $error];
        }

        if (($intent->status ?? '') !== 'succeeded') {
            $error = 'intent_not_succeeded';
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error]);
            return ['subscription_error' => $error];
        }

        $customerId = $intent->customer?->id ?? ($metadata['stripe_customer_id'] ?? null);
        $paymentMethodId = $intent->payment_method?->id ?? null;

        try {
            if (!$customerId) {
                $customer = $this->stripe->client()->customers->create([
                    'email' => $payment->email ?: null,
                    'name' => $payment->holder_name ?: null,
                    'metadata' => [
                        'siret_or_siren' => (string) ($payment->siret_or_siren ?? ''),
                    ],
                ]);
                $customerId = $customer->id;
            }

            if ($paymentMethodId) {
                try {
                    $this->stripe->client()->paymentMethods->attach($paymentMethodId, [
                        'customer' => $customerId,
                    ]);
                } catch (\Throwable $e) {
                    $alreadyAttached = str_contains(mb_strtolower($e->getMessage()), 'already attached');
                    if (!$alreadyAttached) {
                        throw $e;
                    }
                }

                $this->stripe->client()->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            $error = 'customer_or_pm_failed: '.$e->getMessage();
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error]);
            return ['subscription_error' => $error];
        }

        $priceId = $this->resolveRecurringPriceId();
        if (!$priceId) {
            $error = 'price_not_available';
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error]);
            return ['subscription_error' => $error];
        }

        try {
            $existing = $this->stripe->client()->subscriptions->all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 20,
            ]);
            foreach ($existing->data as $subscription) {
                $subStatus = $subscription->status ?? '';
                if (!in_array($subStatus, ['active', 'trialing', 'past_due', 'incomplete', 'unpaid'], true)) {
                    continue;
                }
                foreach (($subscription->items->data ?? []) as $item) {
                    if (($item->price->id ?? null) === $priceId) {
                        $payload = [
                            'stripe_customer_id' => $customerId,
                            'subscription_id' => $subscription->id,
                            'subscription_status' => $subscription->status,
                            'subscription_trial_end' => $subscription->trial_end ?? null,
                            'subscription_error' => null,
                        ];
                        $this->updatePaymentMetadata($payment, $payload, 'succeeded');
                        return $payload;
                    }
                }
            }
        } catch (\Throwable $e) {
            $error = 'subscription_list_failed: '.$e->getMessage();
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error]);
            return ['subscription_error' => $error];
        }

        $trialEnd = now()->timestamp + (max((int) config('stripe.trial_hours', 72), 1) * 3600);

        try {
            $subscription = $this->stripe->client()->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $priceId],
                ],
                'trial_end' => $trialEnd,
                'collection_method' => 'charge_automatically',
                'default_payment_method' => $paymentMethodId,
                'metadata' => [
                    'siret_or_siren' => (string) ($payment->siret_or_siren ?? ''),
                    'email' => (string) ($payment->email ?? ''),
                    'source_path' => (string) ($payment->source_path ?? '/paiement'),
                ],
            ]);
        } catch (\Throwable $e) {
            $error = 'subscription_create_failed: '.$e->getMessage();
            $this->updatePaymentMetadata($payment, ['subscription_error' => $error], 'succeeded_without_subscription');
            return ['subscription_error' => $error];
        }

        $payload = [
            'stripe_customer_id' => $customerId,
            'subscription_id' => $subscription->id ?? null,
            'subscription_status' => $subscription->status ?? null,
            'subscription_trial_end' => $subscription->trial_end ?? null,
            'subscription_error' => null,
        ];

        $this->updatePaymentMetadata($payment, $payload, 'succeeded');

        return $payload;
    }

    private function sendConfirmationEmail(Payment $payment): array
    {
        $metadata = $this->metadata($payment);
        if (empty($payment->email)) {
            return [
                'confirmation_email_sent' => false,
                'confirmation_email_error' => 'missing_email',
            ];
        }

        if (!empty($metadata['confirmation_email_sent_at'])) {
            return ['confirmation_email_sent' => true, 'confirmation_email_error' => null];
        }

        try {
            Mail::to($payment->email)->send(new PaymentConfirmationMail($payment));
        } catch (\Throwable $e) {
            Log::error('payment_confirmation_email_failed', [
                'payment_id' => $payment->id,
                'intent_id' => $payment->stripe_intent_id,
                'email' => $payment->email,
                'error' => $e->getMessage(),
            ]);

            $this->updatePaymentMetadata($payment, [
                'confirmation_email_error' => $e->getMessage(),
            ]);

            return [
                'confirmation_email_sent' => false,
                'confirmation_email_error' => $e->getMessage(),
            ];
        }

        $this->updatePaymentMetadata($payment, [
            'confirmation_email_sent_at' => now()->toIso8601String(),
            'confirmation_email_error' => null,
        ]);

        return ['confirmation_email_sent' => true, 'confirmation_email_error' => null];
    }

    private function resolveRecurringPriceId(): ?string
    {
        $configured = trim((string) config('stripe.recurring_price_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $currency = strtolower((string) config('stripe.currency', 'eur'));
        $lookupKey = (string) config('stripe.recurring_lookup_key', 'infosociete-premium-4999-eur-monthly');
        $amountCents = (int) round(((float) config('stripe.recurring_amount', 49.99)) * 100);
        $productName = (string) config('stripe.recurring_product_name', 'Infosociete Premium Monthly');

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
                    'app' => 'infosociete',
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

    private function updatePaymentMetadata(Payment $payment, array $extra, ?string $status = null): void
    {
        $metadata = array_merge($this->metadata($payment), array_filter($extra, fn ($value) => $value !== ''));

        $updates = ['metadata' => $metadata];
        if ($status !== null) {
            $updates['status'] = $status;
        }

        $payment->update($updates);
        $payment->refresh();
    }

    private function metadata(Payment $payment): array
    {
        return is_array($payment->metadata ?? null) ? $payment->metadata : [];
    }
}
