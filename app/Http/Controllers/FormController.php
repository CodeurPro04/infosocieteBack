<?php

namespace App\Http\Controllers;

use App\Models\Cancellation;
use App\Models\Claim;
use App\Models\Contact;
use App\Models\KbisRequest;
use App\Models\Payment;
use App\Models\UserCustomer;
use App\Services\SubmissionStore;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private SubmissionStore $store)
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

        Cancellation::create($payload);
        $this->store->append('cancellation', $payload);

        return response()->json(['status' => 'received']);
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

        if (!empty($payload['stripe_intent_id'])) {
            Payment::updateOrCreate(
                ['stripe_intent_id' => $payload['stripe_intent_id']],
                $paymentData
            );
        } else {
            Payment::create($paymentData);
        }

        $this->store->append('payment', $payload);

        return response()->json(['status' => 'received']);
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
}
