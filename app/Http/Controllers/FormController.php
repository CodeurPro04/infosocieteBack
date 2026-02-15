<?php

namespace App\Http\Controllers;

use App\Models\Cancellation;
use App\Models\Claim;
use App\Models\Contact;
use App\Models\KbisRequest;
use App\Models\Payment;
use App\Services\StripeService;
use App\Models\UserCustomer;
use App\Services\SubmissionStore;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(
        private SubmissionStore $store,
        private StripeService $stripe
    )
    {
    }

    public function contact(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'company' => 'nullable|string|max:160',
            'subject' => 'nullable|string|max:180',
            'message' => 'required|string|max:4000',
            'source_path' => 'nullable|string|max:255',
        ]);

        Contact::create($payload);
        $this->store->append('contact', $payload); // legacy feed for existing admin UI

        return response()->json(['status' => 'received']);
    }

    public function cancel(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'source_path' => 'nullable|string|max:255',
        ]);

        $stripeResult = $this->cancelStripeSubscriptionsByEmail($payload['email']);

        $cancellation = Cancellation::create([
            ...$payload,
            'stripe_status' => $stripeResult['status'],
            'stripe_cancelled_count' => $stripeResult['cancelled_count'],
            'stripe_details' => $stripeResult,
        ]);

        $this->store->append('cancellation', [
            ...$payload,
            'stripe_status' => $stripeResult['status'],
            'stripe_cancelled_count' => $stripeResult['cancelled_count'],
            'cancellation_id' => $cancellation->id,
        ]);

        return response()->json([
            'status' => 'received',
            'stripe_status' => $stripeResult['status'],
            'cancelled_count' => $stripeResult['cancelled_count'],
        ]);
    }

    public function claim(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'order' => 'nullable|string|max:120',
            'subject' => 'nullable|string|max:180',
            'message' => 'required|string|max:4000',
            'source_path' => 'nullable|string|max:255',
        ]);

        Claim::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'order_ref' => $payload['order'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'message' => $payload['message'],
            'source_path' => $payload['source_path'] ?? null,
        ]);
        $this->store->append('claim', $payload);

        return response()->json(['status' => 'received']);
    }

    public function signup(Request $request)
    {
        $payload = $request->validate([
            'profile' => 'required|string|in:entreprise,auto,auto-entrepreneur,particulier',
            'siret_or_siren' => 'required|string|max:20',
            'company_name' => 'required|string|max:180',
            'address' => 'required|string|max:255',
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'required|email',
            'phone' => 'required|string|max:40',
            'source_path' => 'nullable|string|max:255',
        ]);

        $customer = UserCustomer::updateOrCreate(
            [
                'email' => $payload['email'],
                'siret_or_siren' => $payload['siret_or_siren'],
            ],
            [
                'profile' => $payload['profile'],
                'company_name' => $payload['company_name'],
                'address' => $payload['address'],
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'phone' => $payload['phone'],
            ]
        );

        $payload['user_customer_id'] = $customer->id;
        $this->store->append('signup', $payload);

        return response()->json(['status' => 'received']);
    }

    public function kbisRequest(Request $request)
    {
        $payload = $request->validate([
            'siret_or_siren' => 'required|string|max:20',
            'profile' => 'required|string|in:entreprise,auto-entrepreneur,particulier',
            'company_name' => 'nullable|string|max:180',
            'address' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'required|email',
            'phone' => 'required|string|max:40',
            'consent' => 'nullable|boolean',
            'source_path' => 'nullable|string|max:255',
        ]);

        $customer = $this->resolveCustomer([
            'profile' => $payload['profile'],
            'siret_or_siren' => $payload['siret_or_siren'],
            'company_name' => $payload['company_name'] ?? null,
            'address' => $payload['address'] ?? null,
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
        ]);

        $kbis = KbisRequest::create([
            'user_customer_id' => $customer?->id,
            ...$payload,
        ]);

        $payload['kbis_request_id'] = $kbis->id;
        $payload['user_customer_id'] = $customer?->id;
        $this->store->append('kbis_request', $payload);

        return response()->json(['status' => 'received']);
    }

    public function payment(Request $request)
    {
        $payload = $request->validate([
            'siret_or_siren' => 'required|string|max:20',
            'amount' => 'required|string|max:20',
            'holder_name' => 'required|string|max:160',
            'profile' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:180',
            'address' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'card_last4' => 'nullable|string|max:4',
            'exp' => 'nullable|string|max:7',
            'email' => 'nullable|email',
            'consent' => 'nullable|boolean',
            'source_path' => 'nullable|string|max:255',
            'stripe_intent_id' => 'nullable|string|max:100',
        ]);

        $customer = $this->resolveCustomer([
            'profile' => $payload['profile'] ?? null,
            'siret_or_siren' => $payload['siret_or_siren'],
            'company_name' => $payload['company_name'] ?? null,
            'address' => $payload['address'] ?? null,
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
        ]);

        $latestKbisRequest = KbisRequest::query()
            ->when($customer, fn ($q) => $q->where('user_customer_id', $customer->id))
            ->where('siret_or_siren', $payload['siret_or_siren'])
            ->latest()
            ->first();

        // If the user comes from signup flow, create a kbis request record at payment time
        // when none exists yet for this identifier/customer.
        if (!$latestKbisRequest) {
            $hasRequiredKbisFields = !empty($payload['profile'])
                && !empty($payload['first_name'])
                && !empty($payload['last_name'])
                && !empty($payload['email'])
                && !empty($payload['phone']);

            if ($hasRequiredKbisFields) {
                $latestKbisRequest = KbisRequest::create([
                    'user_customer_id' => $customer?->id,
                    'siret_or_siren' => $payload['siret_or_siren'],
                    'profile' => $payload['profile'],
                    'company_name' => $payload['company_name'] ?? null,
                    'address' => $payload['address'] ?? null,
                    'first_name' => $payload['first_name'],
                    'last_name' => $payload['last_name'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'consent' => (bool) ($payload['consent'] ?? false),
                    'source_path' => $payload['source_path'] ?? '/paiement',
                ]);
            }
        }

        $paymentData = [
            'user_customer_id' => $customer?->id,
            'kbis_request_id' => $latestKbisRequest?->id,
            'status' => 'succeeded',
            'amount' => (float) $payload['amount'],
            'currency' => 'eur',
            'holder_name' => $payload['holder_name'],
            'email' => $payload['email'] ?? null,
            'siret_or_siren' => $payload['siret_or_siren'],
            'profile' => $payload['profile'] ?? null,
            'company_name' => $payload['company_name'] ?? null,
            'address' => $payload['address'] ?? null,
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'source_path' => $payload['source_path'] ?? null,
            'metadata' => [
                'consent' => (bool) ($payload['consent'] ?? false),
                'card_last4' => $payload['card_last4'] ?? null,
                'exp' => $payload['exp'] ?? null,
            ],
        ];

        $subscriptionData = [];
        if (!empty($payload['stripe_intent_id'])) {
            $subscriptionData = $this->createRecurringSubscriptionFromIntent($payload);
            $paymentData['metadata'] = array_merge(
                $paymentData['metadata'],
                array_filter($subscriptionData, fn ($value) => $value !== null && $value !== '')
            );

            if (empty($subscriptionData['subscription_id'])) {
                $paymentData['status'] = 'succeeded_without_subscription';
            }
        }

        if (!empty($payload['stripe_intent_id'])) {
            Payment::updateOrCreate(
                ['stripe_intent_id' => $payload['stripe_intent_id']],
                $paymentData
            );
        } else {
            Payment::create($paymentData);
        }

        $this->store->append('payment', [
            ...$payload,
            'subscription_status' => $subscriptionData['subscription_status'] ?? null,
            'subscription_id' => $subscriptionData['subscription_id'] ?? null,
            'subscription_error' => $subscriptionData['subscription_error'] ?? null,
        ]);

        if (!empty($payload['stripe_intent_id']) && empty($subscriptionData['subscription_id'])) {
            $reason = $subscriptionData['subscription_error'] ?? 'unknown_error';
            return response()->json([
                'message' => 'Paiement validé, mais abonnement 72h→49,99 €/mois non créé automatiquement.',
                'subscription_error' => $reason,
            ], 422);
        }

        return response()->json([
            'status' => 'received',
            'subscription_id' => $subscriptionData['subscription_id'] ?? null,
            'subscription_status' => $subscriptionData['subscription_status'] ?? null,
        ]);
    }

    private function createRecurringSubscriptionFromIntent(array $payload): array
    {
        if (empty($payload['stripe_intent_id'])) {
            return [];
        }

        try {
            $intent = $this->stripe->client()->paymentIntents->retrieve(
                $payload['stripe_intent_id'],
                ['expand' => ['payment_method', 'customer']]
            );
        } catch (\Throwable $e) {
            return ['subscription_error' => 'intent_fetch_failed: '.$e->getMessage()];
        }

        if (($intent->status ?? '') !== 'succeeded') {
            return ['subscription_error' => 'intent_not_succeeded'];
        }

        $customerId = $intent->customer?->id ?? null;
        $paymentMethodId = $intent->payment_method?->id ?? null;

        try {
            if (!$customerId) {
                $createdCustomer = $this->stripe->client()->customers->create([
                    'email' => $payload['email'] ?? null,
                    'name' => $payload['holder_name'] ?? null,
                    'metadata' => [
                        'siret_or_siren' => $payload['siret_or_siren'] ?? '',
                    ],
                ]);
                $customerId = $createdCustomer->id;
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
            return ['subscription_error' => 'customer_or_pm_failed: '.$e->getMessage()];
        }

        $priceId = $this->resolveRecurringPriceId();
        if (!$priceId) {
            return ['subscription_error' => 'price_not_available'];
        }

        try {
            $existing = $this->stripe->client()->subscriptions->all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 20,
            ]);
            foreach ($existing->data as $subscription) {
                $subStatus = $subscription->status ?? '';
                if (!in_array($subStatus, ['active', 'trialing', 'past_due', 'incomplete'], true)) {
                    continue;
                }
                foreach (($subscription->items->data ?? []) as $item) {
                    if (($item->price->id ?? null) === $priceId) {
                        return [
                            'stripe_customer_id' => $customerId,
                            'subscription_id' => $subscription->id,
                            'subscription_status' => $subscription->status,
                            'subscription_trial_end' => $subscription->trial_end ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            return ['subscription_error' => 'subscription_list_failed: '.$e->getMessage()];
        }

        $trialHours = max((int) config('stripe.trial_hours', 72), 1);
        $trialEnd = now()->timestamp + ($trialHours * 3600);

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
                    'siret_or_siren' => $payload['siret_or_siren'] ?? '',
                    'email' => $payload['email'] ?? '',
                    'source_path' => $payload['source_path'] ?? '/paiement',
                ],
            ]);
        } catch (\Throwable $e) {
            return ['subscription_error' => 'subscription_create_failed: '.$e->getMessage()];
        }

        return [
            'stripe_customer_id' => $customerId,
            'subscription_id' => $subscription->id ?? null,
            'subscription_status' => $subscription->status ?? null,
            'subscription_trial_end' => $subscription->trial_end ?? null,
        ];
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
            return null;
        }
    }

    private function resolveCustomer(array $data): ?UserCustomer
    {
        if (empty($data['siret_or_siren']) && empty($data['email'])) {
            return null;
        }

        $query = UserCustomer::query();
        if (!empty($data['email'])) {
            $query->where('email', $data['email']);
        }
        if (!empty($data['siret_or_siren'])) {
            $query->where('siret_or_siren', $data['siret_or_siren']);
        }

        $customer = $query->first();
        if ($customer) {
            $customer->fill($data);
            $customer->save();
            return $customer;
        }

        return UserCustomer::create($data);
    }

    private function cancelStripeSubscriptionsByEmail(string $email): array
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $cancellableStatuses = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'];
        $seenSubscriptions = [];
        $seenCustomers = [];

        $result = [
            'status' => 'noop',
            'email' => $normalizedEmail,
            'customers' => [],
            'cancelled_subscription_ids' => [],
            'scheduled_subscription_ids' => [],
            'already_closed_subscription_ids' => [],
            'failed_subscription_ids' => [],
            'errors' => [],
            'cancelled_count' => 0,
        ];

        if (!config('stripe.secret')) {
            $result['status'] = 'stripe_not_configured';
            return $result;
        }

        // 1) First fallback from our local payments table (most reliable when Stripe customer email is missing)
        $payments = Payment::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->latest()
            ->limit(50)
            ->get();

        foreach ($payments as $payment) {
            $metadata = is_array($payment->metadata ?? null) ? $payment->metadata : [];
            $subscriptionId = $metadata['subscription_id'] ?? null;
            $customerId = $metadata['stripe_customer_id'] ?? null;

            if ($customerId && !in_array($customerId, $seenCustomers, true)) {
                $seenCustomers[] = $customerId;
                $result['customers'][] = $customerId;
            }

            if ($subscriptionId && !in_array($subscriptionId, $seenSubscriptions, true)) {
                $seenSubscriptions[] = $subscriptionId;
                try {
                    $subscription = $this->stripe->client()->subscriptions->retrieve($subscriptionId, []);
                    $status = $subscription->status ?? null;

                    if (!$status || !in_array($status, $cancellableStatuses, true)) {
                        $result['already_closed_subscription_ids'][] = $subscriptionId;
                        continue;
                    }

                    if (!empty($subscription->cancel_at_period_end)) {
                        $result['scheduled_subscription_ids'][] = $subscriptionId;
                        continue;
                    }

                    $this->stripe->client()->subscriptions->update($subscriptionId, [
                        'cancel_at_period_end' => true,
                    ]);
                    $result['scheduled_subscription_ids'][] = $subscriptionId;
                } catch (\Throwable $e) {
                    $result['failed_subscription_ids'][] = $subscriptionId;
                    $result['errors'][] = "subscription_cancel_failed:{$subscriptionId}:".$e->getMessage();
                }
            }
        }

        // 2) Then scan Stripe customers by email and cancel remaining subscriptions
        try {
            $customers = $this->stripe->client()->customers->all([
                'email' => $normalizedEmail,
                'limit' => 100,
            ]);
        } catch (\Throwable $e) {
            $result['status'] = 'stripe_customer_fetch_failed';
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        foreach (($customers->data ?? []) as $customer) {
            $customerId = $customer->id ?? null;
            if (!$customerId) {
                continue;
            }

            if (!in_array($customerId, $seenCustomers, true)) {
                $seenCustomers[] = $customerId;
                $result['customers'][] = $customerId;
            }

            try {
                $subscriptions = $this->stripe->client()->subscriptions->all([
                    'customer' => $customerId,
                    'status' => 'all',
                    'limit' => 100,
                ]);
            } catch (\Throwable $e) {
                $result['errors'][] = "subscriptions_list_failed:{$customerId}:".$e->getMessage();
                continue;
            }

            foreach (($subscriptions->data ?? []) as $subscription) {
                $subscriptionId = $subscription->id ?? null;
                $status = $subscription->status ?? null;
                if (!$subscriptionId || !$status || in_array($subscriptionId, $seenSubscriptions, true)) {
                    continue;
                }

                $seenSubscriptions[] = $subscriptionId;

                if (!in_array($status, $cancellableStatuses, true)) {
                    $result['already_closed_subscription_ids'][] = $subscriptionId;
                    continue;
                }

                try {
                    if (!empty($subscription->cancel_at_period_end)) {
                        $result['scheduled_subscription_ids'][] = $subscriptionId;
                        continue;
                    }

                    $this->stripe->client()->subscriptions->update($subscriptionId, [
                        'cancel_at_period_end' => true,
                    ]);
                    $result['scheduled_subscription_ids'][] = $subscriptionId;
                } catch (\Throwable $e) {
                    $result['failed_subscription_ids'][] = $subscriptionId;
                    $result['errors'][] = "subscription_cancel_failed:{$subscriptionId}:".$e->getMessage();
                }
            }
        }

        $result['cancelled_count'] = count($result['scheduled_subscription_ids']);

        if ($result['cancelled_count'] > 0) {
            $result['status'] = 'scheduled_cancellation';
        } elseif (count($result['errors']) > 0) {
            $result['status'] = 'partial_error';
        } elseif (!empty($result['customers']) || !empty($seenSubscriptions)) {
            $result['status'] = 'no_cancellable_subscription';
        } elseif ($payments->isNotEmpty()) {
            $result['status'] = 'no_subscription_linked';
        } else {
            $result['status'] = 'no_customer';
        }

        return $result;
    }
}
