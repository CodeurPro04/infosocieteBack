<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentFulfillmentService;
use App\Services\StripeService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PaymentFulfillmentService $fulfillment
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
            $intent = $this->stripe->client()->paymentIntents->create([
                'amount' => (int) round($payload['amount'] * 100),
                'currency' => $currency,
                'receipt_email' => $payload['email'] ?? null,
                'description' => $payload['description'] ?? 'Paiement Infosociete',
                'customer' => $customerId,
                'setup_future_usage' => 'off_session',
                'metadata' => array_merge(
                    ['siret_or_siren' => $payload['siret_or_siren']],
                    $payload['metadata'] ?? []
                ),
                'payment_method_types' => ['card'],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        Payment::updateOrCreate(
            ['stripe_intent_id' => $intent->id],
            [
                'status' => $intent->status,
                'amount' => $payload['amount'],
                'currency' => $currency,
                'email' => $payload['email'] ?? null,
                'siret_or_siren' => $payload['siret_or_siren'],
                'source_path' => $payload['source_path'] ?? '/paiement',
                'metadata' => array_merge(
                    $payload['metadata'] ?? [],
                    array_filter([
                        'stripe_customer_id' => $customerId,
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
            return response()->json(['message' => 'Missing webhook configuration'], 400);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $type = $event->type ?? '';
        $data = $event->data->object ?? null;

        if ($data && !empty($data->id)) {
            $payment = Payment::query()->where('stripe_intent_id', $data->id)->first();

            if ($payment) {
                $metadata = is_array($payment->metadata ?? null) ? $payment->metadata : [];
                $payment->update([
                    'status' => $data->status ?? $type,
                    'metadata' => array_merge($metadata, [
                        'webhook_event' => $type,
                    ]),
                ]);

                if (($type === 'payment_intent.succeeded' || ($data->status ?? null) === 'succeeded')) {
                    $this->fulfillment->fulfill($payment->fresh());
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
