<?php

namespace App\Http\Controllers;

use App\Models\Cancellation;
use App\Models\Claim;
use App\Models\Contact;
use App\Models\KbisRequest;
use App\Models\Payment;
use App\Models\UserCustomer;
use App\Services\ContentStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(private ContentStore $contentStore)
    {
    }

    public function login(Request $request)
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $expectedEmail = (string) env('ADMIN_EMAIL', 'admin@infosociete.pro');
        $expectedPassword = (string) env('ADMIN_PASSWORD', 'Infosociete2026!');

        if (!hash_equals($expectedEmail, $email) || !hash_equals($expectedPassword, $password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = Str::random(48);
        $ttl = now()->addHours(8);
        Cache::put("admin_token:{$token}", true, $ttl);

        return response()->json([
            'token' => $token,
            'expires_at' => $ttl->toDateTimeString(),
        ]);
    }

    public function updateContent(Request $request)
    {
        $payload = $request->all();

        if (!is_array($payload) || empty($payload)) {
            return response()->json(['message' => 'Invalid content'], 422);
        }

        $this->contentStore->write($payload);

        return response()->json(['status' => 'ok']);
    }

    public function submissions()
    {
        return response()->json([
            'submissions' => $this->collectSubmissions(),
        ]);
    }

    public function submissionsByType(string $type)
    {
        $allowed = ['contact', 'cancellation', 'claim', 'signup', 'kbis_request', 'payment'];

        if (!in_array($type, $allowed, true)) {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $items = array_values(array_filter($this->collectSubmissions(), fn ($item) => ($item['type'] ?? '') === $type));

        return response()->json([
            'submissions' => $items,
        ]);
    }

    private function collectSubmissions(): array
    {
        $items = [];

        foreach (Contact::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'CON-'.$row->id,
                'type' => 'contact',
                'payload' => $row->only(['name', 'email', 'company', 'subject', 'message', 'source_path']),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        foreach (Cancellation::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'RES-'.$row->id,
                'type' => 'cancellation',
                'payload' => $row->only([
                    'email',
                    'source_path',
                    'stripe_status',
                    'stripe_cancelled_count',
                    'stripe_details',
                ]),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        foreach (Claim::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'REC-'.$row->id,
                'type' => 'claim',
                'payload' => $row->only(['name', 'email', 'order_ref', 'subject', 'message', 'source_path']),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        foreach (UserCustomer::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'SIGN-'.$row->id,
                'type' => 'signup',
                'payload' => $row->only([
                    'profile',
                    'siret_or_siren',
                    'company_name',
                    'address',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                ]),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        foreach (KbisRequest::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'KBIS-'.$row->id,
                'type' => 'kbis_request',
                'payload' => $row->only([
                    'siret_or_siren',
                    'profile',
                    'company_name',
                    'address',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'source_path',
                    'consent',
                ]),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        foreach (Payment::query()->latest()->get() as $row) {
            $items[] = [
                'id' => 'PAY-'.$row->id,
                'type' => 'payment',
                'payload' => $row->only([
                    'stripe_intent_id',
                    'status',
                    'amount',
                    'currency',
                    'holder_name',
                    'email',
                    'siret_or_siren',
                    'profile',
                    'company_name',
                    'address',
                    'first_name',
                    'last_name',
                    'phone',
                    'source_path',
                ]),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $items;
    }
}
