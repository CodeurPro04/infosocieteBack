<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentFulfillmentService;
use App\Services\StripeService;
use App\Services\StripeRecurringPriceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PaymentFulfillmentService $fulfillment,
        private StripeRecurringPriceResolver $recurringPriceResolver
    ) {
    }

    public function createIntent(Request $request)
    {
        $payload = $request->validate([
            'amount' => 'required|numeric|min:1',
            'siret_or_siren' => 'required|string|max:20',
            'email' => 'nullable|email',
            'description' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $currency = config('stripe.currency', 'eur');
        $customerId = null;

        if (!empty($payload['email'])) {
            try {
                $existingCustomers = $this->stripe->client()->customers->all([
                    'email' => $payload['email'],
                    'limit' => 1,
                ]);
                if (!empty($existingCustomers->data)) {
                    $customerId = $existingCustomers->data[0]->id;
                } else {
                    $createdCustomer = $this->stripe->client()->customers->create([
                        'email' => $payload['email'],
                    ]);
                    $customerId = $createdCustomer->id;
                }
            } catch (\Throwable $e) {
                // Do not block intent creation if customer lookup fails.
                $customerId = null;
            }
        }

        try {
            $priceId = $this->recurringPriceResolver->resolve();
            if (!$priceId) {
                return response()->json(['message' => 'Stripe price not configured'], 422);
            }

            if (!$customerId) {
                $customer = $this->stripe->client()->customers->create([
                    'email' => $payload['email'] ?? null,
                    'metadata' => [
                        'siret_or_siren' => (string) $payload['siret_or_siren'],
                    ],
                ]);
                $customerId = $customer->id;
            }

            $trialHours = max((int) config('stripe.trial_hours', 72), 1);
            $trialEnd = now()->timestamp + ($trialHours * 3600);
            $trialFeeCents = (int) round(((float) $payload['amount']) * 100);

            $subscription = $this->stripe->client()->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $priceId],
                ],
                'trial_end' => $trialEnd,
                'collection_method' => 'charge_automatically',
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'add_invoice_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $trialFeeCents,
                            'product_data' => [
                                'name' => 'Essai 72h DOCSFLOW',
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'metadata' => array_merge(
                    ['siret_or_siren' => $payload['siret_or_siren']],
                    $payload['metadata'] ?? []
                ),
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            $intent = $subscription->latest_invoice?->payment_intent ?? null;
            if (!$intent || empty($intent->client_secret) || empty($intent->id)) {
                return response()->json(['message' => 'Stripe subscription payment intent missing'], 422);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        Payment::updateOrCreate(
            ['stripe_intent_id' => $intent->id],
            [
                'status' => $intent->status ?? 'requires_payment_method',
                'amount' => $payload['amount'],
                'currency' => $currency,
                'email' => $payload['email'] ?? null,
                'siret_or_siren' => $payload['siret_or_siren'],
                'source_path' => $payload['source_path'] ?? '/paiement',
                'metadata' => array_merge(
                    $payload['metadata'] ?? [],
                    array_filter([
                        'stripe_customer_id' => $customerId,
                        'subscription_id' => $subscription->id ?? null,
                        'subscription_status' => $subscription->status ?? null,
                        'subscription_trial_end' => $subscription->trial_end ?? null,
                    ], fn ($v) => !empty($v))
                ),
            ]
        );

        return response()->json([
            'client_secret' => $intent->client_secret,
            'intent_id' => $intent->id,
        ]);
    }

    public function webhook(Request $request)
    {
        $secret = config('stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();

        if (!$secret || !$signature) {
            Log::error('stripe_webhook_missing_configuration', [
                'has_secret' => (bool) $secret,
                'has_signature' => (bool) $signature,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['message' => 'Missing webhook configuration'], 400);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            Log::error('stripe_webhook_invalid_signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $type = $event->type ?? '';
        $data = $event->data->object ?? null;
        $paymentIntentId = $this->resolvePaymentIntentId($data);

        app()->terminating(function () use ($type, $data, $paymentIntentId): void {
            try {
                $this->processWebhookEvent($type, $data, $paymentIntentId);
            } catch (\Throwable $e) {
                Log::error('stripe_webhook_processing_failed', [
                    'event_type' => $type,
                    'payment_intent_id' => $paymentIntentId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return response()->json(['received' => true], 200);
    }

    private function processWebhookEvent(string $type, mixed $data, ?string $paymentIntentId): void
    {
        if (!$data || !$paymentIntentId) {
            Log::warning('stripe_webhook_payment_not_resolved', [
                'event_type' => $type,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $payment = Payment::query()->where('stripe_intent_id', $paymentIntentId)->first();
        if (!$payment) {
            Log::warning('stripe_webhook_payment_not_found', [
                'event_type' => $type,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $metadata = is_array($payment->metadata ?? null) ? $payment->metadata : [];
        $payment->update([
            'status' => $data->status ?? $payment->status ?? $type,
            'metadata' => array_merge($metadata, [
                'webhook_event' => $type,
                'webhook_received_at' => now()->toIso8601String(),
            ]),
        ]);

        $objectType = is_object($data) ? (string) ($data->object ?? '') : '';
        $shouldFulfill = $type === 'payment_intent.succeeded'
            || $type === 'invoice.payment_succeeded'
            || $type === 'invoice.paid'
            || ($objectType === 'payment_intent' && ($data->status ?? null) === 'succeeded')
            || ($objectType === 'invoice' && (bool) ($data->paid ?? false) === true);

        if ($shouldFulfill) {
            try {
                $this->fulfillment->fulfill($payment->fresh());
            } catch (\Throwable $e) {
                Log::error('stripe_webhook_fulfillment_failed', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $paymentIntentId,
                    'event_type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolvePaymentIntentId(mixed $data): ?string
    {
        if (!$data || !is_object($data)) {
            return null;
        }

        $objectType = (string) ($data->object ?? '');
        $id = (string) ($data->id ?? '');
        $paymentIntent = $data->payment_intent ?? null;

        if ($objectType === 'payment_intent' && $id !== '') {
            return $id;
        }

        if (is_string($paymentIntent) && $paymentIntent !== '') {
            return $paymentIntent;
        }

        if (is_object($paymentIntent) && !empty($paymentIntent->id)) {
            return (string) $paymentIntent->id;
        }

        if (str_starts_with($id, 'pi_')) {
            return $id;
        }

        return null;
    }
}
